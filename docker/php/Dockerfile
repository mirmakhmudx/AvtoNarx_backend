FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    oniguruma-dev \
    icu-dev \
    libxml2-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    supervisor \
    chromium \
    chromium-chromedriver

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        mbstring \
        zip \
        exif \
        pcntl \
        bcmath \
        gd \
        intl \
        opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV PANTHER_NO_SANDBOX=1
ENV PANTHER_CHROME_ARGUMENTS="--headless --disable-gpu --disable-dev-shm-usage"
ENV PANTHER_CHROME_DRIVER_BINARY=/usr/bin/chromedriver

WORKDIR /var/www/html

RUN addgroup -g 1000 avtonarx && adduser -G avtonarx -u 1000 -D avtonarx
USER avtonarx

EXPOSE 9000

CMD ["php-fpm"]
