#!/usr/bin/env bash
set -euo pipefail

cd /var/www/pterodactyl

panel_available() {
    local retries=0
    local max_retries=60

    until curl -fsS --max-time 10 http://127.0.0.1/auth/login >/dev/null \
        || curl -fsS --max-time 10 http://127.0.0.1/ >/dev/null; do
        retries=$((retries + 1))
        if [ "$retries" -ge "$max_retries" ]; then
            echo "[E2E] Panel did not become available within ${max_retries} attempts." >&2
            return 1
        fi
        sleep 2
    done
}

mysql_notur_tables() {
    mysql --ssl=0 \
        -h "${DB_HOST}" \
        -P "${DB_PORT}" \
        -u "${DB_USERNAME}" \
        -p"${DB_PASSWORD}" \
        "${DB_DATABASE}" \
        -N -B \
        -e "SHOW TABLES LIKE 'notur_%';"
}

echo "[E2E] Verifying Notur is installed before uninstall..."
panel_available

if ! php artisan route:list | grep -q "admin/notur/extensions"; then
    echo "[E2E] Expected Notur admin routes before uninstall, but they were missing." >&2
    exit 1
fi

if ! grep -R "notur::scripts" resources/views >/dev/null 2>&1; then
    echo "[E2E] Expected Notur Blade script injection before uninstall, but it was missing." >&2
    exit 1
fi

if [ ! -d notur ] || [ ! -d public/notur ]; then
    echo "[E2E] Expected Notur runtime directories before uninstall, but one was missing." >&2
    exit 1
fi

if ! mysql_notur_tables | grep -q "notur_extensions"; then
    echo "[E2E] Expected Notur database tables before uninstall, but notur_extensions was missing." >&2
    exit 1
fi

echo "[E2E] Running non-interactive Notur uninstall..."
COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_DISABLE_AUDIT=1 php artisan notur:framework:uninstall --confirm --no-interaction

echo "[E2E] Verifying panel still responds after Notur uninstall..."
panel_available

echo "[E2E] Verifying Notur framework state was removed..."
if php artisan route:list | grep -q "admin/notur"; then
    echo "[E2E] Notur admin routes are still registered after uninstall." >&2
    php artisan route:list | grep -i notur >&2 || true
    exit 1
fi

if grep -R "notur::scripts" resources/views >/dev/null 2>&1; then
    echo "[E2E] Notur Blade script injection is still present after uninstall." >&2
    grep -R "notur::scripts" resources/views >&2 || true
    exit 1
fi

if [ -d notur ] || [ -d public/notur ]; then
    echo "[E2E] Notur runtime directories still exist after uninstall." >&2
    ls -ld notur public/notur 2>/dev/null >&2 || true
    exit 1
fi

remaining_tables="$(mysql_notur_tables)"
if [ -n "${remaining_tables}" ]; then
    echo "[E2E] Notur database tables still exist after uninstall:" >&2
    echo "${remaining_tables}" >&2
    exit 1
fi

if composer show notur/notur >/dev/null 2>&1; then
    echo "[E2E] Composer still reports notur/notur as installed after uninstall." >&2
    exit 1
fi

echo "[E2E] Install/uninstall lifecycle verification passed."
