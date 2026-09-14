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

# exec, not a plain call: this replaces the shell rather than spawning a
# child, so Apache becomes PID 1 and receives SIGTERM when the platform
# stops the container. Without exec, shutdown signals go to this script,
# Apache never hears them, and the platform waits out its timeout before
# killing it — slow deploys, and connections dropped mid-request.
exec "$@"
