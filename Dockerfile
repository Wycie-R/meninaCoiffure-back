FROM php:8.4-fpm

RUN apt-get update && apt-get install -y git curl zip unzip libpq-dev libzip-dev libpng-dev libonig-dev libxml2-dev && docker-php-ext-install pdo pdo_pgsql pgsql zip mbstring exif pcntl bcmath gd && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

RUN groupadd -g 1000 laravel && useradd -u 1000 -ms /bin/bash -g laravel laravel
USER laravel

EXPOSE 9000

CMD ["php-fpm"]