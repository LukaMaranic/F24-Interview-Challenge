FROM php:8.4-fpm-bookworm

RUN docker-php-ext-install pdo_mysql

WORKDIR /var/www/html

COPY . .

EXPOSE 9000

CMD ["php-fpm"]
