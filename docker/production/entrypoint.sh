#!/usr/bin/env sh
set -eu

cd /var/www/html

php artisan config:clear || true
php artisan cache:clear || true
php artisan route:clear || true
php artisan view:clear || true

# Ensure Passport keys are always available after deploy.
if [ -n "${PASSPORT_PRIVATE_KEY:-}" ] && [ -n "${PASSPORT_PUBLIC_KEY:-}" ]; then
  printf "%b" "$PASSPORT_PRIVATE_KEY" > storage/oauth-private.key
  printf "%b" "$PASSPORT_PUBLIC_KEY" > storage/oauth-public.key
fi

if [ ! -s storage/oauth-private.key ] || [ ! -s storage/oauth-public.key ]; then
  php artisan passport:keys --force
fi

chown www-data:www-data storage/oauth-private.key storage/oauth-public.key
chmod 640 storage/oauth-private.key storage/oauth-public.key

php artisan migrate --force || true

exec /usr/bin/supervisord -c /etc/supervisord.conf
