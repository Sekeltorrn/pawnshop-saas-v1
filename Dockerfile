# Use PHP 8.3 to match your Codespace version
FROM php:8.3-apache

RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Copy your files into the web server
COPY . /var/www/html/

# Tell Render to use Port 80
EXPOSE 80