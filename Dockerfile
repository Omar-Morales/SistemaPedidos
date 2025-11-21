FROM composer:2 AS composer-build
WORKDIR /app

# Copia dependencias PHP y las instala
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

FROM node:20 AS node-build
WORKDIR /app

COPY package.json package-lock.json vite.config.js tailwind.config.js postcss.config.js ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM php:8.2-cli
WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libzip-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql zip gd \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer-build /usr/bin/composer /usr/local/bin/composer

COPY . .

COPY --from=composer-build /app/vendor ./vendor
COPY --from=node-build /app/node_modules ./node_modules

ENV APP_ENV=production \
    APP_DEBUG=false \
    PYTHONUNBUFFERED=1

CMD ["sh", "-c", "php artisan migrate --force && php artisan config:cache && php artisan storage:link || true && php artisan serve --host 0.0.0.0 --port ${PORT:-8000}"]
