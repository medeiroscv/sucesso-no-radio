# Sucesso no Rádio — EasyPanel
# PHP 8.2 + Apache + PostgreSQL
FROM php:8.2-apache-bookworm

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libwebp-dev \
        libzip-dev \
        libonig-dev \
        libicu-dev \
        libpq-dev \
        pkg-config \
        unzip \
        curl \
    ; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        gd \
        exif \
        zip \
        intl \
        mbstring \
        opcache \
    ; \
    a2enmod rewrite headers; \
    rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

RUN { \
    echo 'upload_max_filesize=64M'; \
    echo 'post_max_size=80M'; \
    echo 'memory_limit=256M'; \
    echo 'max_execution_time=120'; \
    echo 'date.timezone=America/Sao_Paulo'; \
    echo 'display_errors=0'; \
    echo 'log_errors=1'; \
    echo 'session.save_path=/var/www/html/data/sessions'; \
    echo 'session.gc_maxlifetime=1209600'; \
    echo 'auto_prepend_file=/var/www/html/includes/session_bootstrap.php'; \
  } > /usr/local/etc/php/conf.d/sucesso-radio.ini

WORKDIR /var/www/html
COPY . /var/www/html/

RUN mkdir -p \
      /var/www/html/config \
      /var/www/html/data/sessions \
      /var/www/html/uploads/programas \
      /var/www/html/uploads/conteudos \
      /var/www/html/uploads/banners \
      /var/www/html/uploads/demos \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 \
      /var/www/html/config \
      /var/www/html/data \
      /var/www/html/uploads

VOLUME ["/var/www/html/uploads", "/var/www/html/data", "/var/www/html/config"]

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh \
    && sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh

# Allow .htaccess rewrite
RUN printf '%s\n' \
  '<Directory /var/www/html>' \
  '  AllowOverride All' \
  '  Require all granted' \
  '</Directory>' \
  > /etc/apache2/conf-available/sucesso-radio.conf \
  && a2enconf sucesso-radio

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
