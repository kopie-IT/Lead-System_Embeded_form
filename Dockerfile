# Use the official PHP 8.2 image with Apache
FROM php:8.2-apache

# Enable Apache mod_rewrite for friendly URLs
RUN a2enmod rewrite

# Update global Apache config to allow .htaccess overrides everywhere
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Write a custom virtual host: root serves index.html, .html files run as PHP
RUN { \
    echo '<VirtualHost *:80>'; \
    echo '    DocumentRoot /var/www/html'; \
    echo '    DirectoryIndex index.html index.php'; \
    echo '    <Directory /var/www/html>'; \
    echo '        AllowOverride All'; \
    echo '        Require all granted'; \
    echo '        <FilesMatch "\.html$">'; \
    echo '            SetHandler application/x-httpd-php'; \
    echo '        </FilesMatch>'; \
    echo '    </Directory>'; \
    echo '    ErrorLog ${APACHE_LOG_DIR}/error.log'; \
    echo '    CustomLog ${APACHE_LOG_DIR}/access.log combined'; \
    echo '</VirtualHost>'; \
} > /etc/apache2/sites-available/000-default.conf

# Install required PHP extensions for database connectivity and zip
RUN apt-get update && apt-get install -y libzip-dev zip unzip \
    && docker-php-ext-install pdo pdo_mysql mysqli zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy the website files to the Apache document root
COPY . /var/www/html/

# Create backups directory and set appropriate permissions
RUN mkdir -p /var/www/html/backups \
    && chown -R www-data:www-data /var/www/html/ \
    && chmod -R 755 /var/www/html/ \
    && chmod -R 775 /var/www/html/backups

# Expose port 80
EXPOSE 80
