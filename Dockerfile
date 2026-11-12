FROM php:8.3-apache
RUN apt-get update \
 && apt-get install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev libpq-dev libcurl4-openssl-dev libonig-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql pdo_pgsql mbstring fileinfo gd curl \
 && a2enmod rewrite headers \
 && sed -i 's/Listen 80/Listen 10000/' /etc/apache2/ports.conf \
 && sed -i 's/<VirtualHost \\*:80>/<VirtualHost *:10000>/' /etc/apache2/sites-available/000-default.conf \
 && sed -i 's#DocumentRoot /var/www/html#DocumentRoot /var/www/html/website#' /etc/apache2/sites-available/000-default.conf \
 && printf "upload_max_filesize=6M\npost_max_size=32M\nmax_file_uploads=20\n" > /usr/local/etc/php/conf.d/hendy-uploads.ini \
 && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY . /var/www/html/
RUN mkdir -p /var/www/html/website/uploads/screenshots /var/www/html/website/app-store/uploads/screenshots \
 && chown -R www-data:www-data /var/www/html/website/uploads /var/www/html/website/app-store/uploads \
 && find /var/www/html -type d -exec chmod 755 {} \; \
 && find /var/www/html -type f -exec chmod 644 {} \;
EXPOSE 10000
CMD ["apache2-foreground"]
