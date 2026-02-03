# Notur E2E Test Environment
# Based on PHP 8.2 + Node 22, installs Pterodactyl Panel + Notur + test extension
FROM php:8.2-fpm

ARG NODE_VERSION=22
ARG PANEL_VERSION=v1.11.7

# System dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    unzip \
    zip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libsodium-dev \
    libcurl4-openssl-dev \
    mariadb-client \
    patch \
    ca-certificates \
    gnupg \
    nginx \
    supervisor \
    && docker-php-ext-install \
    pdo_mysql \
    mbstring \
    zip \
    bcmath \
    sodium \
    gd \
    xml \
    && rm -rf /var/lib/apt/lists/*

# Install Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# Install Bun
RUN curl -fsSL https://bun.sh/install | bash \
    && ln -s /root/.bun/bin/bun /usr/local/bin/bun \
    && ln -s /root/.bun/bin/bunx /usr/local/bin/bunx

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Clone Pterodactyl Panel
RUN git clone --depth 1 --branch ${PANEL_VERSION} \
    https://github.com/pterodactyl/panel.git /var/www/pterodactyl

WORKDIR /var/www/pterodactyl

# Install panel PHP dependencies
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Install panel frontend dependencies and build (panel uses yarn)
RUN npm install -g yarn \
    && yarn install --frozen-lockfile \
    && yarn run build:production \
    || true

# Set up panel environment
RUN cp .env.example .env \
    && php artisan key:generate --force

# Fix storage permissions for www-data (PHP-FPM user)
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Copy Notur source into vendor-like location for testing
COPY . /opt/notur

# Nginx configuration for serving the panel
RUN echo 'server { \n\
    listen 80; \n\
    root /var/www/pterodactyl/public; \n\
    index index.php; \n\
    server_name _; \n\
    \n\
    location / { \n\
        try_files $uri $uri/ /index.php?$query_string; \n\
    } \n\
    \n\
    location ~ \.php$ { \n\
        fastcgi_pass 127.0.0.1:9000; \n\
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; \n\
        include fastcgi_params; \n\
    } \n\
}' > /etc/nginx/sites-available/default

# Supervisor to run PHP-FPM + Nginx together
RUN echo '[supervisord] \n\
nodaemon=true \n\
\n\
[program:php-fpm] \n\
command=/usr/local/sbin/php-fpm --nodaemonize \n\
autostart=true \n\
autorestart=true \n\
\n\
[program:nginx] \n\
command=/usr/sbin/nginx -g "daemon off;" \n\
autostart=true \n\
autorestart=true' > /etc/supervisor/conf.d/panel.conf

# Expose HTTP
EXPOSE 80

# Default: start supervisor (PHP-FPM + Nginx)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
