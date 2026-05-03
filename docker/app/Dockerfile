FROM php:8.5-cli

ARG NODE_VERSION=22

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip curl ca-certificates gnupg zip \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions \
        pdo_pgsql pgsql intl zip bcmath exif gd opcache pcntl

RUN curl -sL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/* \
    && npm install -g pnpm@10

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app
