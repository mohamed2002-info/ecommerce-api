# ---- Laravel API image for Render (Docker runtime) ----
FROM php:8.2-cli

# System deps + PHP extensions Laravel needs (pdo_mysql, mbstring, zip, gd, bcmath)
RUN apt-get update && apt-get install -y \
        git unzip libzip-dev libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql mbstring zip gd bcmath \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer (copied from the official composer image)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install PHP dependencies first (better layer caching)
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copy the rest of the application
COPY . .

# Finish composer setup + make storage writable
RUN composer dump-autoload --optimize \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Render injects $PORT; default to 8000 locally.
ENV PORT=8000
EXPOSE 8000

# Make the entrypoint executable (it may lose the +x bit on Windows checkouts).
RUN chmod +x docker-entrypoint.sh

# Startup: migrations + serve public/ on $PORT (binds reliably for Render).
CMD ["./docker-entrypoint.sh"]
