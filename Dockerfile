FROM php:8.2-apache

# Install PostgreSQL driver for Supabase
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

COPY . /var/www/html/
EXPOSE 80
