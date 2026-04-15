# ── WEI Tanzania — PHP/Apache Docker Image ────────────────────────────────────
FROM php:8.2-apache

# ── System packages ───────────────────────────────────────────────────────────
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libzip-dev \
    libonig-dev \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# ── PHP extensions ────────────────────────────────────────────────────────────
RUN docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install \
      pdo \
      pdo_sqlite \
      pdo_mysql \
      gd \
      fileinfo \
      mbstring \
      zip \
      exif

# ── Apache: enable mod_rewrite and mod_headers ────────────────────────────────
RUN a2enmod rewrite headers

# ── Apache virtual host: document root = /var/www/html ───────────────────────
# The root .htaccess routes all non-static requests to php/index.php
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html|' \
        /etc/apache2/sites-available/000-default.conf \
 && sed -i 's|<Directory /var/www/>|<Directory /var/www/html/>|' \
        /etc/apache2/apache2.conf \
 && sed -i 's/AllowOverride None/AllowOverride All/' \
        /etc/apache2/apache2.conf

# ── PHP configuration ─────────────────────────────────────────────────────────
RUN echo "upload_max_filesize = 100M\n\
post_max_size = 100M\n\
max_execution_time = 120\n\
memory_limit = 256M\n\
expose_php = Off\n\
display_errors = Off\n\
log_errors = On" > /usr/local/etc/php/conf.d/wei.ini

# ── Copy application files ────────────────────────────────────────────────────
WORKDIR /var/www/html
COPY . .

# ── Writable directories ──────────────────────────────────────────────────────
RUN mkdir -p php/uploads/images php/uploads/receipts php/uploads/videos \
             php/uploads/docs php/data \
 && chown -R www-data:www-data php/uploads php/data \
 && chmod -R 755 php/uploads php/data

# ── Remove development files from image ───────────────────────────────────────
RUN rm -f php/.env php/data/wei.db 2>/dev/null || true

EXPOSE 80

# ── Entrypoint: write .env from environment variables, then start Apache ──────
COPY docker-entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
