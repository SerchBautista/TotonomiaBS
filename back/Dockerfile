FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts

FROM node:22-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    nginx \
    postgresql-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    nano \
    tzdata \
    && cp /usr/share/zoneinfo/America/Mexico_City /etc/localtime \
    && echo "America/Mexico_City" > /etc/timezone \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_pgsql zip pcntl opcache

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/start.sh /usr/local/bin/start.sh

RUN rm -f bootstrap/cache/*.php \
    && php artisan package:discover --ansi

# --- Hardening: non-root user ---
RUN addgroup -S appgroup && adduser -S -G appgroup appuser

COPY docker/php-fpm/www.conf /usr/local/etc/php-fpm.d/www.conf

RUN mkdir -p /var/cache/nginx /var/log/nginx /var/lib/nginx/logs /var/lib/nginx/tmp /var/run /run/nginx /run/php \
    && chmod +x /usr/local/bin/start.sh \
    && chown -R appuser:appgroup /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache \
    && chown -R appuser:appgroup /var/cache/nginx /var/log/nginx /var/lib/nginx /var/run /run/nginx /run/php

EXPOSE 8080 9000

USER appuser

CMD ["/usr/local/bin/start.sh"]
