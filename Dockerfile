FROM php:8.3-cli-alpine

# Set working directory
WORKDIR /var/www/html

# Install system dependencies and build libraries
RUN apk add --no-cache \
    curl \
    git \
    bash \
    zip \
    unzip \
    libpng-dev \
    libxml2-dev \
    libzip-dev \
    postgresql-dev \
    oniguruma-dev \
    nodejs \
    npm \
    autoconf \
    build-base \
    linux-headers

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    mbstring \
    bcmath \
    gd \
    zip \
    pcntl

# Install and enable Redis extension
RUN pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy project files
COPY . .

# Expose ports for Laravel Artisan and Vite
EXPOSE 8000 5173

# Default startup command
CMD ["sh", "-c", "php artisan serve --host=0.0.0.0 --port=8000"]
