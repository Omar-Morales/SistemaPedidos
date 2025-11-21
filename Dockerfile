FROM php:8.2-cli AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libzip-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        nodejs \
        npm \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql zip gd \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html

FROM php-base AS composer-build
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

FROM node:20 AS node-build
WORKDIR /app
COPY package.json package-lock.json vite.config.js tailwind.config.js postcss.config.js ./
RUN npm ci
COPY resources ./resources
COPY public ./public
RUN npm run build

FROM php-base AS runtime
WORKDIR /var/www/html

COPY . .
COPY --from=composer-build /var/www/html/vendor ./vendor
COPY --from=node-build /app/public/build ./public/build

ENV APP_ENV=production \
    APP_DEBUG=false

CMD ["sh", "-c", "php artisan migrate --force && php artisan config:cache && php artisan storage:link || true && php artisan serve --host 0.0.0.0 --port ${PORT:-8000}"]
