FROM php:8.4-apache

# -----------------------------
# Dépendances système
# -----------------------------
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    curl \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pgsql \
        mbstring \
        bcmath \
        gd \
        zip \
        opcache \
    && rm -rf /var/lib/apt/lists/*

# -----------------------------
# Apache
# -----------------------------
RUN a2enmod rewrite

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri \
    -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

# -----------------------------
# Composer
# -----------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader

COPY . .

RUN composer dump-autoload --optimize

# -----------------------------
# Permissions
# -----------------------------
RUN mkdir -p storage/framework/cache \
    storage/framework/views \
    storage/framework/sessions \
    bootstrap/cache

RUN chown -R www-data:www-data storage bootstrap/cache

RUN chmod -R 775 storage bootstrap/cache

# -----------------------------
# Entrypoint
# -----------------------------
COPY docker/entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]

CMD ["apache2-foreground"]