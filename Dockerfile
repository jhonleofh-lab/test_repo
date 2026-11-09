
FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql mysqli \
    && a2enmod rewrite headers

# Set the correct public directory
RUN sed -ri \
    's!/var/www/html!/var/www/html/public!g' \
    /etc/apache2/sites-available/000-default.conf \
    /etc/apache2/apache2.conf

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
