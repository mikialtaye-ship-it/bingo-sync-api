FROM php:8.2-apache

# ለ SQLite የሚያስፈልጉ ላይብረሪዎችን መጫን
RUN apt-get update && apt-get install -y libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite

# ፋይሎችን ኮፒ ማድረግ
COPY . /var/www/html/

# Apache ዳታቤዙን ለመጻፍ እና ሴቭ ለማድረግ ሙሉ ፈቃድ መስጠት
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 777 /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
