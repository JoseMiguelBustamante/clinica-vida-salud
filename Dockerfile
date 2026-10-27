FROM composer:2 AS composer

FROM php:8.3-apache-bookworm

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl unzip libpq-dev libonig-dev libxml2-dev libicu-dev libcurl4-openssl-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql mbstring dom xml intl curl opcache \
    && a2enmod rewrite \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY deploy/apache.conf /etc/apache2/sites-available/000-default.conf
COPY deploy/php.ini /usr/local/etc/php/conf.d/clinic.ini
COPY . .

# The context allowlist excludes environment files and host-generated caches.
# Do not boot the application while runtime secrets are unavailable.
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader \
    && mkdir -p storage/app/private storage/app/public \
        storage/framework/cache/data storage/framework/cache/locks \
        storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R u+rwX,g+rwX storage bootstrap/cache

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl --fail --silent --show-error --max-time 4 http://127.0.0.1/up || exit 1

CMD ["apache2-foreground"]
