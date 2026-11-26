FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip libzip-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql zip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Serve backend/public as document root, route all unknown paths to Laravel's front controller
# and pass the Authorization header (API tokens) on to PHP
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && echo 'FallbackResource /index.php' > /etc/apache2/conf-enabled/fallback.conf \
    && echo 'SetEnvIf Authorization "(.+)" HTTP_AUTHORIZATION=$1' > /etc/apache2/conf-enabled/authorization.conf \
    && cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    && printf 'upload_max_filesize=20M\npost_max_size=25M\n' > "$PHP_INI_DIR/conf.d/uploads.ini"

COPY . /var/www/html
RUN composer install --no-interaction --no-progress \
    && chown -R www-data:www-data storage bootstrap/cache
