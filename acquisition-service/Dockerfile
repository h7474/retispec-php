# Use official PHP FPM image
FROM php:8.2-fpm

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /var/www/html

# Create upload directory within the container (will be mounted over by volume)
RUN mkdir -p /uploads && chown www-data:www-data /uploads

# Copy source code
COPY ./src /var/www/html/

# Expose port (though FPM typically communicates via socket or TCP within Docker network)
EXPOSE 9002

# Start PHP-FPM
CMD ["php-fpm"]