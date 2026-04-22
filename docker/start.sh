#!/usr/bin/env sh
set -eu

cd /var/www/html

php artisan storage:link || true
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

php-fpm -D

exec nginx -g "daemon off;"
