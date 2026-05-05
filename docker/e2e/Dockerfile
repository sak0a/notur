# Notur E2E Test Environment
# Depends on the reusable base built from docker/e2e/Dockerfile.base.
ARG E2E_BASE_IMAGE=notur/e2e-base:php8.2-node22-panel1.12.2

FROM ${E2E_BASE_IMAGE} AS notur-node-deps

WORKDIR /opt/notur

COPY package.json package-lock.json ./

RUN npm ci --no-audit --no-fund \
    && npx playwright install chromium

FROM ${E2E_BASE_IMAGE} AS panel-base

COPY . /opt/notur

FROM panel-base AS app

WORKDIR /var/www/pterodactyl

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

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]

FROM notur-node-deps AS test-runner

WORKDIR /opt/notur

COPY . /opt/notur

WORKDIR /var/www/pterodactyl
