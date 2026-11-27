# TastyIgniter image - multi-arch (works on Raspberry Pi 4 / linux/arm64 and amd64)
FROM php:8.3-fpm

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip curl default-mysql-client \
        libpng-dev libjpeg-dev libfreetype6-dev libwebp-dev \
        libzip-dev libicu-dev libonig-dev libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j2 gd zip intl pdo_mysql mysqli exif bcmath opcache pcntl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini /usr/local/etc/php/conf.d/tastyigniter.ini

WORKDIR /var/www/html

# Install TastyIgniter (latest stable release)
RUN rm -rf /var/www/html/* \
    && composer create-project tastyigniter/tastyigniter . --no-interaction --prefer-dist \
    && mkdir -p storage/app/public storage/framework/cache storage/framework/sessions \
        storage/framework/views storage/logs extensions themes bootstrap/cache \
    && composer clear-cache \
    && cp -a public /usr/local/share/tastyigniter-public

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && chown -R www-data:www-data /var/www/html \
    && printf '[www]\nclear_env = no\n' > /usr/local/etc/php-fpm.d/zz-environment.conf

EXPOSE 9000
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
