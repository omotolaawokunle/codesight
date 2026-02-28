#!/bin/sh
set -e

# Clear any config/route/view cache that was baked in at build time,
# then rebuild it using the runtime environment variables injected by Docker.
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
