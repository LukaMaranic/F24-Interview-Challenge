FROM php:8.4-fpm-bookworm

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    && docker-php-ext-install pdo_mysql

WORKDIR /var/www/html

COPY . .

EXPOSE 9000

CMD ["php-fpm"]
