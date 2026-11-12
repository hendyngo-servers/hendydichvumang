FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring fileinfo gd && a2enmod rewrite headers && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY . /var/www/html/
RUN mkdir -p /var/www/html/uploads/screenshots && chown -R www-data:www-data /var/www/html/uploads && find /var/www/html -type d -exec chmod 755 {} \; && find /var/www/html -type f -exec chmod 644 {} \;
EXPOSE 80
CMD ["apache2-foreground"]
