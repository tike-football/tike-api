#!/usr/bin/env sh
set -eu

cd /var/www/html

mkdir -p \
  storage/framework/cache \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache \
  /run/nginx \
  /var/lib/nginx/tmp

php artisan config:clear || true
php artisan cache:clear || true
php artisan route:clear || true
php artisan view:clear || true

# Ensure runtime writable paths are owned by the same user as php-fpm workers.
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

# nginx workers run as "nginx" in this image.
chown -R nginx:nginx /run/nginx /var/lib/nginx

# Keep Passport keys stable across deploys.
if [ -z "${PASSPORT_PRIVATE_KEY:-}" ] || [ -z "${PASSPORT_PUBLIC_KEY:-}" ]; then
  echo "ERROR: Missing PASSPORT_PRIVATE_KEY or PASSPORT_PUBLIC_KEY from environment."
  exit 1
fi

printf "%b" "$PASSPORT_PRIVATE_KEY" > storage/oauth-private.key
printf "%b" "$PASSPORT_PUBLIC_KEY" > storage/oauth-public.key

if [ ! -s storage/oauth-private.key ] || [ ! -s storage/oauth-public.key ]; then
  echo "ERROR: Passport key files were not written correctly."
  exit 1
fi

chown www-data:www-data storage/oauth-private.key storage/oauth-public.key
chmod 640 storage/oauth-private.key storage/oauth-public.key

php artisan migrate --force || true
php artisan schedule:clear-cache || true
php artisan football-data:cache-fixtures || true

exec /usr/bin/supervisord -c /etc/supervisord.conf
