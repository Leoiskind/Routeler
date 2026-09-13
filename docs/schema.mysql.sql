-- Routeler schema — MySQL 8.0.16 or later
--
-- 8.0.16 is a hard requirement: before that version MySQL parsed CHECK
-- constraints and silently ignored them, so every validation below would
-- be decorative. Aiven's free MySQL is well past this.
--
-- Character set is utf8mb4 throughout. MySQL's "utf8" is a three-byte
-- imitation that cannot store emoji or much of CJK; utf8mb4 is real UTF-8.
-- Collation utf8mb4_0900_ai_ci is accent- and case-insensitive, which is
-- what you want for usernames (see the note on users.username).

-- ---------------------------------------------------------------------
-- users — one row per account at one OAuth provider
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,

    -- Identity comes from the provider, never from email. Emails change
    -- and get reused; a GitHub account and a Google account sharing an
    -- address are still two different identities.
    provider      VARCHAR(32)  NOT NULL,          -- 'github' | 'google'
    provider_uid  VARCHAR(255) NOT NULL,          -- their stable ID at that provider

    username      VARCHAR(30)  NOT NULL,          -- the public @handle
    display_name  VARCHAR(120) NOT NULL,          -- see APP NOTE below
    bio           TEXT         NULL,
    location      VARCHAR(120) NULL,
    avatar_url    VARCHAR(512) NULL,              -- provider's CDN URL, not stored bytes

    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY users_username_uq (username),
    UNIQUE KEY users_provider_uq (provider, provider_uid),

    -- Allowed characters and length only. Case is NOT enforced here:
    -- under a _ci collation 'LEO' REGEXP '^[a-z]+$' is true, so a
    -- lowercase-only pattern would be a lie. Lowercase in the application
    -- before insert. The _ci collation on the unique key is deliberate —
    -- it stops @Leo and @leo existing as two different people.
    CONSTRAINT users_username_format
        CHECK (username REGEXP '^[A-Za-z0-9_]{3,30}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- APP NOTE: GitHub's user API returns null for `name` when the user has
-- not set one. display_name is NOT NULL, so the application must fall
-- back — provider login, then username — rather than passing null through.


-- ---------------------------------------------------------------------
-- routes — one row per saved route
-- ---------------------------------------------------------------------
CREATE TABLE routes (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id      BIGINT UNSIGNED NOT NULL,

    name         VARCHAR(160) NOT NULL,
    description  TEXT         NULL,

    -- Metres, as an integer, exactly as Mapbox returns it. Divide at
    -- display time. Nullable because a route saved before the Map
    -- Matching response arrives is still a valid route.
    -- UNSIGNED already forbids negatives, so no CHECK is needed.
    distance_m   INT UNSIGNED NULL,

    is_public    BOOLEAN  NOT NULL DEFAULT TRUE,  -- BOOLEAN is TINYINT(1); PDO returns "1"/"0"

    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                                   ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT routes_user_fk FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE,

    CONSTRAINT routes_name_not_blank
        CHECK (CHAR_LENGTH(TRIM(name)) > 0),

    KEY routes_user_id_idx    (user_id),
    KEY routes_created_at_idx (created_at DESC)   -- descending indexes need MySQL 8.0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- route_points — the ordered vertices of a route
-- ---------------------------------------------------------------------
CREATE TABLE route_points (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    route_id   BIGINT UNSIGNED NOT NULL,

    -- Draw order. Without this a route is an unordered bag of points:
    -- SQL guarantees no row order unless you ORDER BY. Always
    -- ORDER BY position when reading.
    position   INT UNSIGNED NOT NULL,

    -- CAREFUL: GeoJSON positions are [longitude, latitude] — that order,
    -- which is the reverse of how people say it. Mapbox GL Draw emits
    -- GeoJSON. Name your variables $lat/$lng and convert once, at the
    -- point JSON becomes PHP.
    latitude   DOUBLE NOT NULL,
    longitude  DOUBLE NOT NULL,

    CONSTRAINT route_points_route_fk FOREIGN KEY (route_id)
        REFERENCES routes(id) ON DELETE CASCADE,

    CONSTRAINT route_points_latitude_chk  CHECK (latitude  BETWEEN  -90 AND  90),
    CONSTRAINT route_points_longitude_chk CHECK (longitude BETWEEN -180 AND 180),

    -- One point per position per route, and this doubles as the index
    -- that makes "fetch this route's points in order" fast.
    UNIQUE KEY route_points_order_uq (route_id, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- follows — directed follower → followee edges
-- ---------------------------------------------------------------------
CREATE TABLE follows (
    follower_id BIGINT UNSIGNED NOT NULL,
    followee_id BIGINT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- The pair is the identity, so no surrogate id. Also makes Follow
    -- idempotent: clicking twice fails rather than duplicating.
    PRIMARY KEY (follower_id, followee_id),

    CONSTRAINT follows_follower_fk FOREIGN KEY (follower_id)
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT follows_followee_fk FOREIGN KEY (followee_id)
        REFERENCES users(id) ON DELETE CASCADE,

    CONSTRAINT follows_no_self CHECK (follower_id <> followee_id),

    -- Needed because of the leftmost-prefix rule: the primary key is
    -- sorted by follower_id first, so it answers "who does X follow?"
    -- but cannot answer "who follows X?" without a scan.
    KEY follows_followee_idx (followee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- ratings — one score per user per route
-- ---------------------------------------------------------------------
CREATE TABLE ratings (
    user_id    BIGINT UNSIGNED  NOT NULL,
    route_id   BIGINT UNSIGNED  NOT NULL,
    score      TINYINT UNSIGNED NOT NULL,
    created_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (user_id, route_id),

    CONSTRAINT ratings_user_fk  FOREIGN KEY (user_id)
        REFERENCES users(id)  ON DELETE CASCADE,
    CONSTRAINT ratings_route_fk FOREIGN KEY (route_id)
        REFERENCES routes(id) ON DELETE CASCADE,

    CONSTRAINT ratings_score_chk CHECK (score BETWEEN 1 AND 5),

    -- Same leftmost-prefix reason: "all ratings for this route" needs it.
    KEY ratings_route_idx (route_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
