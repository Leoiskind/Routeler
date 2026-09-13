-- Routeler schema (MySQL 8)

CREATE TABLE users (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    provider      VARCHAR(32)  NOT NULL,
    provider_uid  VARCHAR(191) NOT NULL,
    username      VARCHAR(30)  NOT NULL,
    display_name  VARCHAR(120) NOT NULL,
    bio           TEXT,
    location      VARCHAR(120),
    avatar_url    VARCHAR(512),
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY users_username_uq (username),
    UNIQUE KEY users_provider_uq (provider, provider_uid),
    CONSTRAINT users_username_format CHECK (username REGEXP '^[a-z0-9_]{3,30}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE routes (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id      BIGINT UNSIGNED NOT NULL,
    name         VARCHAR(160) NOT NULL,
    description  TEXT,
    distance_m   INT UNSIGNED,
    is_public    BOOLEAN   NOT NULL DEFAULT TRUE,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT routes_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT routes_name_not_blank CHECK (CHAR_LENGTH(TRIM(name)) > 0),
    KEY routes_user_id_idx (user_id),
    KEY routes_created_at_idx (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE route_points (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    route_id   BIGINT UNSIGNED NOT NULL,
    position   INT    NOT NULL,
    latitude   DOUBLE NOT NULL,
    longitude  DOUBLE NOT NULL,
    CONSTRAINT route_points_route_fk FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE,
    CONSTRAINT route_points_position_chk  CHECK (position >= 0),
    CONSTRAINT route_points_latitude_chk  CHECK (latitude  BETWEEN  -90 AND  90),
    CONSTRAINT route_points_longitude_chk CHECK (longitude BETWEEN -180 AND 180),
    UNIQUE KEY route_points_order_uq (route_id, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE follows (
    follower_id BIGINT UNSIGNED NOT NULL,
    followee_id BIGINT UNSIGNED NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (follower_id, followee_id),
    CONSTRAINT follows_follower_fk FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT follows_followee_fk FOREIGN KEY (followee_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT follows_no_self CHECK (follower_id <> followee_id),
    KEY follows_followee_idx (followee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE ratings (
    user_id    BIGINT UNSIGNED NOT NULL,
    route_id   BIGINT UNSIGNED NOT NULL,
    score      TINYINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, route_id),
    CONSTRAINT ratings_user_fk  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    CONSTRAINT ratings_route_fk FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE,
    CONSTRAINT ratings_score_chk CHECK (score BETWEEN 1 AND 5),
    KEY ratings_route_idx (route_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
