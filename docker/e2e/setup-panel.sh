#!/usr/bin/env bash
set -euo pipefail

cd /var/www/pterodactyl

export NODE_OPTIONS="${NODE_OPTIONS:---openssl-legacy-provider}"

composer config platform.php 8.2.30 --no-interaction
composer config audit.block-insecure false --no-interaction
composer config audit.ignore "PKSA-8qx3-n5y5-vvnd,PKSA-w7xr-vk7n-rstm" --no-interaction
composer config minimum-stability dev --no-interaction
composer config prefer-stable true --no-interaction
composer config repositories.notur '{"type":"path","url":"/opt/notur","options":{"symlink":false}}' --no-interaction

if [ -f ".env" ]; then
    set_env_var() {
        local key="$1"
        local value="$2"

        if grep -q "^${key}=" .env; then
            sed -i "s|^${key}=.*|${key}=${value}|" .env
        else
            echo "${key}=${value}" >> .env
        fi
    }

    set_env_var "APP_URL" "${APP_URL}"
    set_env_var "DB_CONNECTION" "mysql"
    set_env_var "DB_HOST" "${DB_HOST}"
    set_env_var "DB_PORT" "${DB_PORT}"
    set_env_var "DB_DATABASE" "${DB_DATABASE}"
    set_env_var "DB_USERNAME" "${DB_USERNAME}"
    set_env_var "DB_PASSWORD" "${DB_PASSWORD}"
    set_env_var "RECAPTCHA_ENABLED" "false"
fi

if ! COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_DISABLE_AUDIT=1 composer require notur/notur:@dev --no-interaction --with-all-dependencies 2>&1; then
    echo "Composer require failed. Retrying once with verbose output..."
    COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_DISABLE_AUDIT=1 composer require notur/notur:@dev --no-interaction --with-all-dependencies -vvv
    exit 1
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --no-interaction
fi

bash vendor/notur/notur/installer/install.sh /var/www/pterodactyl

php artisan package:discover --ansi
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear
php artisan migrate --force
php artisan migrate --path=vendor/notur/notur/database/migrations --force

rm -rf /tmp/hello-world-src /tmp/hello-world.notur
cp -R /opt/notur/examples/hello-world /tmp/hello-world-src
php -r 'require "vendor/autoload.php"; \Notur\Support\NoturArchive::pack("/tmp/hello-world-src", "/tmp/hello-world.notur");'
mkdir -p public/notur/e2e-registry storage/notur
cp /tmp/hello-world.notur public/notur/e2e-registry/hello-world.notur
php <<'PHP'
<?php

$archive = '/tmp/hello-world.notur';
$cachePath = 'storage/notur/registry-cache.json';
$checksum = hash_file('sha256', $archive);

if ($checksum === false) {
    fwrite(STDERR, "Failed to hash {$archive}\n");
    exit(1);
}

$registry = [
    'fetched_at' => gmdate('Y-m-d\TH:i:s\Z'),
    'registry' => [
        'version' => '1.0.0',
        'extensions' => [[
            'id' => 'notur/hello-world',
            'name' => 'Hello World',
            'description' => 'E2E registry fixture for archive-backed installs.',
            'version' => '1.0.0',
            'archive_url' => 'http://127.0.0.1/notur/e2e-registry/hello-world.notur',
            'sha256' => $checksum,
            'tags' => ['e2e', 'fixture'],
        ]],
    ],
];

$json = json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    fwrite(STDERR, 'Failed to encode E2E registry cache: ' . json_last_error_msg() . "\n");
    exit(1);
}

if (file_put_contents($cachePath, $json . PHP_EOL) === false) {
    fwrite(STDERR, "Failed to write {$cachePath}\n");
    exit(1);
}
PHP
php artisan notur:add /tmp/hello-world.notur --force

rm -rf notur/extensions/notur/full-extension
mkdir -p notur/extensions/notur
cp -R /opt/notur/examples/full-extension notur/extensions/notur/full-extension

php /opt/notur/tests/E2E/bootstrap-state.php

php artisan package:discover --ansi
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear

TABLES=$(mysql --ssl=0 -h "${DB_HOST}" -P "${DB_PORT}" -u "${DB_USERNAME}" -p"${DB_PASSWORD}" "${DB_DATABASE}" -N -B -e "SHOW TABLES LIKE 'notur_%';" 2>/tmp/notur-mysql.err || true)
for table in notur_extensions notur_migrations notur_settings; do
    if ! echo "${TABLES}" | grep -q "${table}"; then
        echo "E2E bootstrap failed: missing table ${table}" >&2
        [ -s /tmp/notur-mysql.err ] && cat /tmp/notur-mysql.err >&2
        exit 1
    fi
done

if ! php artisan route:list 2>/tmp/notur-route.err | grep -q "notur/hello-world"; then
    echo "E2E bootstrap failed: hello-world routes are not registered." >&2
    cat /tmp/notur-route.err >&2 || true
    php artisan route:list | grep -i notur >&2 || true
    exit 1
fi

if ! grep -R "notur::scripts" resources/views >/dev/null 2>&1; then
    echo "E2E bootstrap failed: Notur scripts were not injected into panel views." >&2
    exit 1
fi

chown -R www-data:www-data storage bootstrap/cache notur public/notur 2>/dev/null || true
