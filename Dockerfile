FROM php:8.5-cli

WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        zip \
        libzip-dev \
        libssl-dev \
    && docker-php-ext-install zip \
    && pecl install mongodb pcov \
    && docker-php-ext-enable mongodb pcov \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1

CMD ["bash"]
