#!/usr/bin/env sh
set -eu

cd /var/www/html

php artisan config:clear || true
php artisan cache:clear || true
php artisan route:clear || true
php artisan view:clear || true

php artisan migrate --force || true

exec /usr/bin/supervisord -c /etc/supervisord.conf
