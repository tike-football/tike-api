FROM php:8.4-fpm-alpine

ARG APP_ENV=staging
ENV APP_ENV=${APP_ENV}
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apk add --no-cache \
    $PHPIZE_DEPS \
    nginx \
    supervisor \
    redis \
    curl \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    mysql-client \
    bash \
    git \
    unzip \
    zip \
    shadow \
    tzdata

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql bcmath gd intl opcache zip \
    && pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN addgroup -g 1000 tike \
    && adduser -D -u 1000 -G tike tike

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --optimize-autoloader

COPY . .

RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && mkdir -p /var/www/html/storage/logs /run/nginx /var/lib/nginx/tmp /var/log/supervisor \
    && chown -R tike:tike /var/www/html/storage /var/www/html/bootstrap/cache /run/nginx /var/lib/nginx /var/log/supervisor

COPY docker/production/nginx.conf /etc/nginx/nginx.conf
COPY docker/production/default.conf /etc/nginx/http.d/default.conf
COPY docker/production/supervisord.conf /etc/supervisord.conf
COPY docker/production/entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh

EXPOSE 80

CMD ["/entrypoint.sh"]
