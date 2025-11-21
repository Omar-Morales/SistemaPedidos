FROM php:8.2-cli

# Instala dependencias del sistema
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
    && npm install -g npm@10 \
    && rm -rf /var/lib/apt/lists/*

# Copia Composer desde la imagen oficial
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

# Instala dependencias de PHP
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

# Instala dependencias de Node y build
COPY package.json package-lock.json vite.config.js postcss.config.js tailwind.config.js ./
RUN npm ci

# Copia el resto del código
COPY . .

RUN npm run build && rm -rf node_modules

ENV APP_ENV=production \
    APP_DEBUG=false \
    WEB_PORT=${PORT:-8000}

CMD ["sh", "-c", "php artisan config:cache && php artisan migrate --force && php artisan storage:link && php artisan serve --host 0.0.0.0 --port ${PORT:-8000}"]
