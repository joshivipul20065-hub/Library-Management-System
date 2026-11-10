FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev \
    && docker-php-ext-install pdo_mysql zip opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && printf "ServerTokens Prod\nServerSignature Off\n" > /etc/apache2/conf-enabled/zz-security.conf \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf "expose_php=Off\nupload_max_filesize=5M\npost_max_size=8M\nmemory_limit=256M\nopcache.enable=1\nopcache.enable_cli=0\nopcache.memory_consumption=128\nopcache.max_accelerated_files=20000\nopcache.validate_timestamps=1\nopcache.revalidate_freq=2\n" > "$PHP_INI_DIR/conf.d/zz-app.ini"

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

# The code is baked into the image (no bind mount): reading PHP files through a Windows/macOS shared
# folder made every request take seconds. Rebuild after changing PHP code: docker compose up -d --build api
# Dev dependencies are included so the test suite and static analysis can run inside the container.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --optimize \
    && mkdir -p logs public/uploads/covers \
    && chown -R www-data:www-data logs public/uploads
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
