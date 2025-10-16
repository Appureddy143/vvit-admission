# Use the official PHP image with Apache web server
FROM php:8.2-apache

# Install system dependencies required for PHP extensions
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/*

# Configure and install the gd and zip extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install pdo pdo_pgsql zip

# Install Composer for PHP dependency management
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Set the working directory for our application
WORKDIR /var/www/html

# Copy composer files and install dependencies
COPY composer.json .
RUN composer install

# Copy the rest of the application source code
COPY src/ .

# Ensure the uploads directory is writable by the web server
RUN mkdir -p uploads && chown www-data:www-data uploads

