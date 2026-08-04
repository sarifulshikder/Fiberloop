# PHP 8.4 with Laravel dependencies
FROM php:8.4-fpm-alpine

# Install system dependencies
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
    oniguruma-dev \
    pcre-dev

# Install PHP extensions
RUN docker-php-ext-configure gd --with-png
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    redis \
    zip \
    bcmath \
    ctype \
    fileinfo \
    gd \
    mbstring \
    tokenizer \
    xml \
    dom \
    simplexml

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Create working directory
WORKDIR /var/www/html

# Set permissions
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 775 /var/www/html/storage
RUN chmod -R 775 /var/www/html/bootstrap/cache

# Copy existing application directory contents
COPY . .

# Install PHP dependencies
RUN if [ -f "composer.json" ]; then \
    composer install --no-interaction --optimize-autoloader --no-dev; \
    fi

# Expose port 9000 for FPM
EXPOSE 9000

# Start supervisor to manage processes
COPY docker/php/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
