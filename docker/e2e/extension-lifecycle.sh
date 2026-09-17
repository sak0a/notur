#!/usr/bin/env bash
# Run only in the disposable E2E panel after installing Notur.
set -euo pipefail
cd /var/www/pterodactyl
mkdir -p /tmp/audit-extension/frontend
printf 'id: audit/demo\nname: Audit demo\nversion: 1.0.0\nfrontend:\n  bundle: frontend/main.js\n' > /tmp/audit-extension/extension.yaml
printf '/* audit v1 */' > /tmp/audit-extension/frontend/main.js
php -r 'require "vendor/autoload.php"; \Notur\Support\NoturArchive::pack("/tmp/audit-extension", "/tmp/audit-v1.notur");'
php artisan notur:add /tmp/audit-v1.notur --no-interaction
if php artisan notur:add /tmp/audit-v1.notur --no-interaction; then exit 1; fi
php artisan notur:disable audit/demo
sed -i 's/1.0.0/2.0.0/' /tmp/audit-extension/extension.yaml
printf '/* audit v2 */' > /tmp/audit-extension/frontend/main.js
php -r 'require "vendor/autoload.php"; \Notur\Support\NoturArchive::pack("/tmp/audit-extension", "/tmp/audit-v2.notur");'
php artisan notur:add /tmp/audit-v2.notur --force --no-interaction
php -r '$state=json_decode(file_get_contents("notur/extensions.json"),true); if ($state["extensions"]["audit/demo"]["enabled"] !== false || $state["extensions"]["audit/demo"]["version"] !== "2.0.0") exit(1);'
php artisan notur:enable audit/demo
php artisan notur:remove audit/demo --force --no-interaction
test ! -d notur/extensions/audit/demo
test ! -d public/notur/extensions/audit/demo
test -n "$(find storage/notur/backups -path '*/extension/extension.yaml')"
echo 'Extension install/reinstall detection/disabled upgrade/enable/remove/retained backup passed.'
