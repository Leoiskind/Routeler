#!/bin/sh
#
# Make Apache listen on the port the host tells us to.
#
# Render (and Cloud Run, and Heroku) hand the container a PORT environment
# variable and expect the process to bind to it. The php:8.3-apache image
# hardcodes 80 in two places, so we rewrite both before starting.
#
# This cannot be done with a RUN line in the Dockerfile: PORT does not
# exist at build time, only at run time. Hence an entrypoint.
#
# Defaults to 80, so `docker compose up` locally is unaffected.

set -e

PORT="${PORT:-80}"

sed -ri "s!^Listen [0-9]+!Listen ${PORT}!g" /etc/apache2/ports.conf
sed -ri "s!<VirtualHost \*:[0-9]+>!<VirtualHost *:${PORT}>!g" /etc/apache2/sites-available/*.conf

echo "[entrypoint] Apache will listen on ${PORT}"

# Dependencies.
#
# The Dockerfile already runs composer install, but compose bind-mounts the
# repository over /var/www/html, and a bind mount hides whatever the image
# had at that path. On a fresh clone there is no vendor/ on the host, so the
# container would start with no autoloader and every page would fatal.
#
# Installing here fixes that with no extra step for whoever cloned it, and
# writes into the mounted repo so the dev tools are available to
# `docker compose exec web vendor/bin/...` too.
#
# On Render there is no bind mount: vendor/ is present from the build and
# this is skipped, so production start-up is unaffected.
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "[entrypoint] no vendor/ found - installing dependencies"
    composer install --no-interaction --no-progress --working-dir=/var/www/html
fi

# exec, not a plain call: this replaces the shell rather than spawning a
# child, so Apache becomes PID 1 and receives SIGTERM when the platform
# stops the container. Without exec, shutdown signals go to this script,
# Apache never hears them, and the platform waits out its timeout before
# killing it — slow deploys, and connections dropped mid-request.
exec "$@"
