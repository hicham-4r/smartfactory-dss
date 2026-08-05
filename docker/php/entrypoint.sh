#!/usr/bin/env sh
set -eu

APP_ROOT="${APP_ROOT:-/var/www/html}"

if [ ! -f "${APP_ROOT}/artisan" ]; then
    echo "SmartFactory container error: artisan was not found under ${APP_ROOT}." >&2
    exit 1
fi

mkdir -p \
    "${APP_ROOT}/storage/app" \
    "${APP_ROOT}/storage/framework/cache/data" \
    "${APP_ROOT}/storage/framework/sessions" \
    "${APP_ROOT}/storage/framework/testing" \
    "${APP_ROOT}/storage/framework/views" \
    "${APP_ROOT}/storage/logs" \
    "${APP_ROOT}/bootstrap/cache"

chown -R www-data:www-data "${APP_ROOT}/storage" "${APP_ROOT}/bootstrap/cache"
chmod -R ug+rwX "${APP_ROOT}/storage" "${APP_ROOT}/bootstrap/cache"

# Migrations, seeding, queue startup, and scheduler startup are intentionally
# not performed by the image entrypoint. They remain explicit deployment steps.
exec "$@"
