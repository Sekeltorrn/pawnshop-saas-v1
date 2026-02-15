# Use PHP 8.3 to match your Codespace version
FROM php:8.3-apache

# Install the PostgreSQL drivers (Crucial for Supabase!)
RUN docker-php-ext-install pdo pdo_pgsql

# Copy your files into the web server
COPY . /var/www/html/

# Tell Render to use Port 80
EXPOSE 80