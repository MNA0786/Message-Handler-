FROM php:8.2-apache

# System dependencies install karo
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    && docker-php-ext-install mysqli pdo pdo_mysql

# Composer install karo
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Apache configuration - headers module enable karo
RUN a2enmod rewrite && a2enmod headers

# Working directory set karo
WORKDIR /var/www/html

# Files copy karo
COPY . .

# File permissions set karo
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod 666 *.json *.txt 2>/dev/null || true

# ServerName set karo to avoid warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Port expose karo
EXPOSE 80

# Apache start karo
CMD ["apache2-foreground"]
