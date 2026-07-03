#!/bin/sh

set -e

echo "Waiting for database..."

sleep 5

php artisan storage:link || true

php artisan migrate --force

php artisan optimize:clear

php artisan config:cache

php artisan route:cache

php artisan view:cache

exec "$@"