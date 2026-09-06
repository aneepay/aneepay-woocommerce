FROM php:7.4-apache

# OpenCart 3.0.3.8 needs mod_rewrite + PHP extensions (see cli_install.php checks).
RUN a2enmod rewrite

RUN apt-get update && apt-get install -y --no-install-recommends \
        curl git unzip \
        libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev libzip-dev libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) mysqli pdo_mysql gd zip mbstring \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# The GitHub source tarball ships an empty system/storage/vendor (no submodules),
# so we run `composer install` from the lockfile to restore the bundled deps
# (react/promise, guzzlehttp, twig, scssphp, ...) into upload/system/storage/vendor.
RUN curl -fsSL "https://github.com/opencart/opencart/archive/refs/tags/3.0.3.8.tar.gz" -o /tmp/oc.tgz \
    && tar -xzf /tmp/oc.tgz -C /tmp \
    && cd /tmp/opencart-3.0.3.8 \
    && rm -rf upload/system/storage/vendor \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/tmp --filename=composer \
    && php /tmp/composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && cp -a upload/. /var/www/html/ \
    && rm -rf /tmp/oc.tgz /tmp/opencart-3.0.3.8 /tmp/composer* /tmp/cacert.pem

# Make storage writable for the runtime (the installer chmods the subdirs too).
RUN mkdir -p /var/www/html/system/storage && chmod o+w -R /var/www/html/system/storage /var/www/html/image

EXPOSE 80
