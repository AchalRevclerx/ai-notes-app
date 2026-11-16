FROM php:8.4-fpm-alpine

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql redis pcntl opcache intl pcov \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

# Dependencies first so code edits don't bust the vendor layer.
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-interaction \
    && cp composer.lock vendor/.composer.lock

COPY . .
RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint && chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
