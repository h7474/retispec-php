# Use official PHP FPM image
FROM php:latest

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /var/www/html

# Copy source code
COPY ./src /var/www/html/

# Expose port (though FPM typically communicates via socket or TCP within Docker network)
EXPOSE 9001

# Start PHP-FPM
CMD ["php"]