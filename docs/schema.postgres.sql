-- Routeler schema (PostgreSQL 16)

CREATE TABLE users (
    id            BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    provider      TEXT        NOT NULL,
    provider_uid  TEXT        NOT NULL,
    username      TEXT        NOT NULL UNIQUE,
    display_name  TEXT        NOT NULL,
    bio           TEXT,
    location      TEXT,
    avatar_url    TEXT,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (provider, provider_uid),
    CONSTRAINT users_username_format CHECK (username ~ '^[a-z0-9_]{3,30}$')
);

CREATE TABLE routes (
    id           BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id      BIGINT      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name         TEXT        NOT NULL,
    description  TEXT,
    distance_m   INTEGER     CHECK (distance_m IS NULL OR distance_m >= 0),
    is_public    BOOLEAN     NOT NULL DEFAULT TRUE,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT routes_name_not_blank CHECK (length(btrim(name)) > 0)
);
CREATE INDEX routes_user_id_idx    ON routes (user_id);
CREATE INDEX routes_created_at_idx ON routes (created_at DESC);

CREATE TABLE route_points (
    id         BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    route_id   BIGINT           NOT NULL REFERENCES routes(id) ON DELETE CASCADE,
    position   INTEGER          NOT NULL CHECK (position >= 0),
    latitude   DOUBLE PRECISION NOT NULL CHECK (latitude  BETWEEN  -90 AND  90),
    longitude  DOUBLE PRECISION NOT NULL CHECK (longitude BETWEEN -180 AND 180),
    UNIQUE (route_id, position)
);
CREATE INDEX route_points_route_id_idx ON route_points (route_id, position);

CREATE TABLE follows (
    follower_id BIGINT      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    followee_id BIGINT      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (follower_id, followee_id),
    CONSTRAINT follows_no_self CHECK (follower_id <> followee_id)
);
CREATE INDEX follows_followee_idx ON follows (followee_id);

CREATE TABLE ratings (
    user_id    BIGINT      NOT NULL REFERENCES users(id)  ON DELETE CASCADE,
    route_id   BIGINT      NOT NULL REFERENCES routes(id) ON DELETE CASCADE,
    score      SMALLINT    NOT NULL CHECK (score BETWEEN 1 AND 5),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (user_id, route_id)
);
CREATE INDEX ratings_route_idx ON ratings (route_id);
