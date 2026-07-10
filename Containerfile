# Neural OS School — PHP dev/build image (podman-friendly, reusable for prod).
# Node/Vite run natively on the host; this image only carries PHP + Composer.
FROM docker.io/library/php:8.3-cli

# System libs needed to compile the PHP extensions below.
RUN apt-get update && apt-get install -y --no-install-recommends \
      git unzip \
      libzip-dev libicu-dev libonig-dev \
      libpng-dev libjpeg-dev libfreetype6-dev \
      libpq-dev default-mysql-client sqlite3 \
    && rm -rf /var/lib/apt/lists/*

# Extensions Laravel 11 + Filament v3 need that are NOT enabled by default in the
# official php image. (pdo_sqlite, dom/xml, curl, openssl, tokenizer, ctype, json,
# fileinfo are already enabled by default.)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
       mbstring pdo_mysql pdo_pgsql bcmath intl zip gd exif pcntl \
    && docker-php-ext-enable opcache

# Composer (copied from the official composer image).
COPY --from=docker.io/library/composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app
