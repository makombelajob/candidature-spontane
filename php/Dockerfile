FROM php:8.4-apache

# Dépendances système
RUN apt-get update && apt-get install -y \
    curl \
    git \
    unzip \
    zip \
    python3 \
    python3-venv \
    python3-pip \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    libfreetype6-dev \
    libsqlite3-dev \
    sqlite3 \
    && rm -rf /var/lib/apt/lists/*

# Configuration GD
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp

# Extensions PHP
RUN docker-php-ext-install -j"$(nproc)" \
    pdo \
    pdo_mysql \
    pdo_sqlite \
    mysqli \
    intl \
    zip \
    gd \
    exif \
    opcache

# APCu
RUN pecl install apcu \
    && docker-php-ext-enable apcu

# Apache
RUN a2enmod rewrite socache_shmcb

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Symfony CLI
RUN curl -fsSL https://get.symfony.com/cli/installer | bash \
    && mv /root/.symfony*/bin/symfony /usr/local/bin/symfony

# Python environnement dédié
RUN python3 -m venv /opt/python \
    && /opt/python/bin/pip install --upgrade pip \
    && /opt/python/bin/pip install ddgs requests

# Utiliser ce Python par défaut
ENV PATH="/opt/python/bin:$PATH"

WORKDIR /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]