FROM php:8.2-cli

# Cài đặt extension PostgreSQL và các công cụ cơ bản
RUN apt-get update && apt-get install -y \
    libpq-dev \
    unzip \
    git \
    && docker-php-ext-install pdo pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

# Cài đặt Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy source code của backend vào image API
COPY . /app

# Cài dependencies cho môi trường production
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader

EXPOSE 8000
