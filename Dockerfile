# PHP 8.4 with Laravel dependencies for CLI
# For web serving, we use Octane (which uses FrankenPHP via artisan octane:start)
FROM php:8.4-cli-alpine

# Install system dependencies for building extensions
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libzip-dev \
    libxml2-dev \
    postgresql-dev \
    redis \
    supervisor \
    zip \
    unzip \
    autoconf \
    g++ \
    make \
    pcre-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    icu-dev \
    oniguruma-dev

# Install PHP extensions that are bundled with PHP
RUN docker-php-ext-install -j1 pdo_pgsql
RUN docker-php-ext-install -j1 zip
RUN docker-php-ext-install -j1 bcmath
RUN docker-php-ext-install -j1 gd
RUN docker-php-ext-install -j1 intl
RUN docker-php-ext-install -j1 pcntl

# Install Redis extension via PECL (requires hiredis-dev)
RUN apk add --no-cache hiredis-dev && \
    pecl install redis && \
    docker-php-ext-enable redis && \
    apk del hiredis-dev autoconf g++ make

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Create working directory
WORKDIR /var/www/html

# Set permissions (www-data group/user may already exist)
RUN addgroup -S -g 1000 www-data 2>/dev/null || true && \
    adduser -S -u 1000 -G www-data www-data 2>/dev/null || true && \
    mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache && \
    chown -R www-data:www-data /var/www/html

# Expose port 9000
EXPOSE 9000

# Start supervisor to manage processes
COPY docker/php/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
