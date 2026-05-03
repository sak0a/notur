# Notur E2E Test Environment
# Based on PHP 8.2 + Node 22, installs Pterodactyl Panel + Notur + test extension
FROM php:8.2-fpm

ARG NODE_VERSION=20
ARG PANEL_VERSION=v1.12.2

# Work around OpenSSL 3 compatibility issues in Node 17+ for webpack builds.
ENV NODE_OPTIONS=--openssl-legacy-provider

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

# Download the Pterodactyl Panel release tarball — same artifact the
# upstream install docs tell operators to fetch:
#   https://pterodactyl.io/panel/1.0/getting_started.html
# The tarball ships with the real version baked into config/app.php
# (no 'canary' placeholder), pre-built public/assets/, and the source
# tree intact. This matches the real install path our installer
# targets and removes the need to "git clone + sed-rewrite the version".
RUN mkdir -p /var/www/pterodactyl \
    && curl -fsSL -o /tmp/panel.tar.gz \
       "https://github.com/pterodactyl/panel/releases/download/${PANEL_VERSION}/panel.tar.gz" \
    && tar -xzf /tmp/panel.tar.gz -C /var/www/pterodactyl \
    && rm /tmp/panel.tar.gz

WORKDIR /var/www/pterodactyl

# Install panel PHP dependencies (vendor/ is not shipped in the tarball).
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Install yarn + frontend deps so the post-patch rebuild in the e2e
# workflow has node_modules ready. Skip the initial build:production:
# the tarball already includes pre-built public/assets/ for the no-patch
# baseline, and the workflow rebuilds anyway after applying React patches,
# so building twice would just burn minutes.
RUN npm install -g yarn \
    && yarn install --frozen-lockfile

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
