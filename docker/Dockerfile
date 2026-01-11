# =============================================================================
# AtoM AHG Framework - Dockerfile
# =============================================================================

# Stage 1: Builder
FROM php:8.3-fpm-bookworm AS builder

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev libjpeg-dev libwebp-dev \
    libfreetype6-dev libxml2-dev libxslt1-dev libicu-dev libmemcached-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) pdo_mysql mysqli zip gd xml xsl intl opcache pcntl \
    && pecl install apcu memcached && docker-php-ext-enable apcu memcached \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /build

ARG ATOM_VERSION=2.10
RUN git clone -b stable/${ATOM_VERSION} --depth 1 https://github.com/artefactual/atom.git /build/atom

WORKDIR /build/atom
RUN composer install --no-dev --optimize-autoloader --no-interaction

ARG FRAMEWORK_BRANCH=main
RUN git clone -b ${FRAMEWORK_BRANCH} --depth 1 \
    https://github.com/ArchiveHeritageGroup/atom-framework.git /build/atom/atom-framework
    
WORKDIR /build/atom/atom-framework
RUN composer install --no-dev --optimize-autoloader --no-interaction

RUN git clone -b ${FRAMEWORK_BRANCH} --depth 1 \
    https://github.com/ArchiveHeritageGroup/atom-ahg-plugins.git /build/atom/atom-ahg-plugins

# Stage 2: Production
FROM php:8.3-fpm-bookworm AS production

LABEL maintainer="The Archive and Heritage Group <info@theahg.co.za>"

RUN apt-get update && apt-get install -y \
    nginx supervisor curl imagemagick ghostscript poppler-utils ffmpeg \
    libzip4 libpng16-16 libjpeg62-turbo libwebp7 libfreetype6 libxml2 libxslt1.1 libicu72 libmemcached11 \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) pdo_mysql mysqli zip gd xml xsl intl opcache pcntl \
    && pecl install apcu memcached && docker-php-ext-enable apcu memcached

RUN echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/atom.ini \
    && echo "max_execution_time = 120" >> /usr/local/etc/php/conf.d/atom.ini \
    && echo "upload_max_filesize = 100M" >> /usr/local/etc/php/conf.d/atom.ini \
    && echo "post_max_size = 100M" >> /usr/local/etc/php/conf.d/atom.ini \
    && echo "date.timezone = Africa/Johannesburg" >> /usr/local/etc/php/conf.d/atom.ini

RUN sed -i 's/listen = .*/listen = \/run\/php\/php-fpm.sock/' /usr/local/etc/php-fpm.d/www.conf \
    && mkdir -p /run/php

COPY --from=builder /build/atom /usr/share/nginx/atom

WORKDIR /usr/share/nginx/atom
RUN mkdir -p cache log uploads && chown -R www-data:www-data cache log uploads

COPY docker/nginx/atom.conf /etc/nginx/sites-available/default
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s CMD curl -f http://localhost/ || exit 1
ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
