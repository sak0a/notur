# Notur Administrator Guide

This guide is for Pterodactyl Panel administrators who want to install and manage the Notur extension framework.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Installing Notur](#installing-notur)
- [Managing Extensions via CLI](#managing-extensions-via-cli)
- [Managing Extensions via Admin UI](#managing-extensions-via-admin-ui)
- [Configuration](#configuration)
- [Directory Layout](#directory-layout)
- [Updating Notur](#updating-notur)
- [Uninstalling Notur](#uninstalling-notur)
- [Troubleshooting](#troubleshooting)

## Prerequisites

| Component | Required Version |
|---|---|
| Pterodactyl Panel | v1 canary / 1.11.x |
| PHP | 8.2 or 8.3 |
| Node.js | 18+ (22+ recommended) |
| Package Manager | npm, Yarn, pnpm, or Bun |
| MySQL | 8.0+ |
| MariaDB | 10.6+ (alternative to MySQL) |
| Composer | 2.x |

Ensure your panel is fully installed and working before adding Notur. Back up your panel files and database before proceeding.

## Installing Notur

### Automated Installation (Recommended)

```bash
curl -sSL https://docs.notur.site/install.sh | bash -s -- /var/www/pterodactyl
```

Replace `/var/www/pterodactyl` with your panel root path if different. The installer performs the following steps automatically:

1. Validates PHP version and panel installation
2. Runs `composer require notur/notur`
3. Backs up the React files that will be patched (creates `.notur-backup` copies)
4. Applies React patches to add slot containers and dynamic route merging
5. Injects `@include('notur::scripts')` into the Blade layout
6. Runs `php artisan migrate` to create Notur's 4 database tables
7. Creates the `notur/extensions` directory and `notur/extensions.json` manifest
8. Builds and deploys the bridge runtime (`public/notur/bridge.js`)
9. Builds and deploys Tailwind CSS (`public/notur/tailwind.css`)
10. Triggers a frontend rebuild

### Manual Installation

If the automated installer does not work for your environment, see [Installing](/getting-started/installing) for step-by-step instructions.

### Verifying the Installation

1. Visit your panel in a browser. It should load normally.
2. View the page source. Look for `window.__NOTUR__` and a `<script>` tag loading `bridge.js`.
3. Open the browser console. You should see `[Notur] Bridge runtime v1.2.2 initialized`.
4. Run `php artisan notur:list`. It should report no extensions installed.

## Managing Extensions via CLI

All extension management is done through Artisan commands in the panel directory (typically `/var/www/pterodactyl`).

### Interactive Mode

When running commands without arguments in an interactive terminal, Notur provides a beautiful TUI experience using Laravel Prompts.

#### Install Extensions

```bash
# Install an extension by ID
php artisan notur:install acme/server-analytics

# Install from a local .notur file
php artisan notur:install /path/to/extension.notur

# Force reinstall an existing extension
php artisan notur:install acme/server-analytics --force
```

#### System Status Dashboard

```bash
# Show full dashboard
php artisan notur:status

# JSON output for scripting
php artisan notur:status --json

# Health checks only
php artisan notur:status --health
```

The dashboard displays:
- System health (PHP, Laravel, Panel versions)
- Installed extensions with status indicators
- Available updates
- Quick action shortcuts

#### Non-Interactive Mode

All commands support `--no-interaction` or `-n` flag for CI/CD pipelines:

```bash
# Install without prompts
php artisan notur:install acme/analytics -n

# Works in CI environments automatically
CI=true php artisan notur:install acme/analytics
```

### Listing Extensions

```bash
# List all installed extensions
php artisan notur:list

# Only enabled extensions
php artisan notur:list --enabled

# Only disabled extensions
php artisan notur:list --disabled
```

Output is a table showing Extension ID, Name, Version, and Status (Enabled/Disabled).

### Installing Extensions

Extensions can be installed from the registry or from a local `.notur` archive file.

```bash
# Install from the Notur registry
php artisan notur:install acme/server-analytics

# Install from a local .notur file
php artisan notur:install /path/to/acme-server-analytics-1.0.0.notur

# Force reinstall (overwrite existing)
php artisan notur:install acme/server-analytics --force

# Install without running migrations
php artisan notur:install acme/server-analytics --no-migrate
```

During installation, Notur will:
- Validate the extension manifest (`extension.yaml`)
- Verify the signature if `notur.require_signatures` is enabled
- Extract files to `notur/extensions/{vendor}/{name}/`
- Register PSR-4 autoloading for the extension
- Run database migrations (unless `--no-migrate`)
- Copy the frontend bundle to `public/notur/extensions/{vendor}/{name}/`
- Update `notur/extensions.json`

### Enabling and Disabling Extensions

```bash
# Enable an extension
php artisan notur:enable acme/server-analytics

# Disable an extension (keeps files and data)
php artisan notur:disable acme/server-analytics
```

Disabling an extension prevents it from loading on the next request. Its files, database tables, and configuration remain intact.

### Removing Extensions

```bash
# Remove an extension (rolls back migrations and deletes files)
php artisan notur:remove acme/server-analytics

# Remove but keep database tables
php artisan notur:remove acme/server-analytics --keep-data
```

This will prompt for confirmation before proceeding. Removal disables the extension first, then rolls back its migrations (unless `--keep-data`), deletes its files, and updates `notur/extensions.json`.

### Updating Extensions

```bash
# Check for available updates
php artisan notur:update --check

# Update a specific extension
php artisan notur:update acme/server-analytics

# Update all extensions
php artisan notur:update
```

The update command checks the registry for newer versions and reinstalls them with `--force`.

### Syncing the Registry

The registry is a remote index of available extensions. Sync it to keep your local cache current.
Cache expiry is controlled by `notur.registry_cache_ttl` (seconds) in `config/notur.php`.

```bash
# Sync registry (respects cache TTL of 1 hour)
php artisan notur:registry:sync

# Force a fresh fetch
php artisan notur:registry:sync --force

# Show cache status (age, size, extensions)
php artisan notur:registry:status

# Search the registry
php artisan notur:registry:sync --search "analytics"
```

### Development Mode

For extension developers testing locally:

```bash
# Link a local extension directory for development
php artisan notur:dev /path/to/my-extension

# Use symlink mode
php artisan notur:dev /path/to/my-extension --link

# Watch frontend bundle and rebuild on changes
php artisan notur:dev /path/to/my-extension --watch

# Also watch the Notur bridge runtime
php artisan notur:dev /path/to/my-extension --watch --watch-bridge
```

### Scaffolding New Extensions

```bash
# Generate extension boilerplate
php artisan notur:new acme/my-extension

# Specify output directory
php artisan notur:new acme/my-extension --path=/home/user/extensions

# Use presets (full includes admin UI, migrations, tests)
php artisan notur:new acme/my-extension --preset=backend
php artisan notur:new acme/my-extension --preset=full
php artisan notur:new acme/my-extension --preset=standard
php artisan notur:new acme/my-extension --preset=minimal

# Toggle features explicitly
php artisan notur:new acme/my-extension --with-api-routes
php artisan notur:new acme/my-extension --no-api-routes
php artisan notur:new acme/my-extension --with-admin-routes
php artisan notur:new acme/my-extension --no-admin-routes
php artisan notur:new acme/my-extension --no-admin --no-migrations --no-tests
php artisan notur:new acme/my-extension --with-admin --with-migrations --with-tests
```

#### Preset Definitions

- `standard`: frontend + API routes (default)
- `backend`: API routes only
- `full`: frontend + API routes + admin UI + migrations + tests
- `minimal`: backend-only scaffolding with no routes or frontend

The extension ID must be in `vendor/name` format using lowercase alphanumeric characters and hyphens. The default preset is `standard`.

### Exporting Extensions

```bash
# Export from the extension directory
cd /path/to/my-extension
php artisan notur:export

# Export from a specific path
php artisan notur:export /path/to/my-extension

# Specify output file
php artisan notur:export --output=/path/to/output.notur

# Sign the archive
php artisan notur:export --sign
```

## Managing Extensions via Admin UI

Notur includes an admin UI accessible at `/admin/notur/extensions`. From this page you can:

- View all installed extensions with their status
- Enable or disable extensions with a toggle
- **Upload and install `.notur` packages** directly from your browser (WordPress-style)
- Install extensions from the registry via a search interface
- Remove extensions with confirmation
- View extension logs and error details
- Configure extension settings (when the extension exposes an admin settings schema)
- Browse the slot catalog and see registered slot usage
- Inspect admin routes and slot registrations per extension
- Review extension health checks and diagnostics

Quick links:
- Extensions: `/admin/notur/extensions`
- Health overview: `/admin/notur/health`
- Diagnostics: `/admin/notur/diagnostics`
- Slot catalog: `/admin/notur/slots`

### Uploading Extensions via Admin UI

The easiest way to install an extension is to upload a `.notur` package directly through the admin panel:

1. Navigate to `/admin/notur/extensions`
2. Click the **Upload** button or use the file upload form
3. Select your `.notur` file
4. The extension will be validated, extracted, and installed automatically
5. Enable the extension with the toggle switch

This is the recommended approach for most users, as it requires no CLI access and works like installing a WordPress plugin.

### Extension Detail Page

Each extension has a detail page with contextual diagnostics, including:
- Settings form (if the extension defines `admin.settings`)
- Registered frontend slots and slot metadata
- Admin routes registered for the extension
- Manifest and migration summaries
- Health check results (if the extension implements `HasHealthChecks`)

### Settings Preview (JSON)

For debugging settings schemas and values, Notur exposes a JSON preview endpoint:

```
GET /admin/notur/extensions/{extension-id}/settings/preview
```

You can access this quickly from the extension detail page via the “Preview JSON” button.

## Configuration

Notur's configuration file is published at `config/notur.php`. The available options are:

### `version`

```php
'version' => '1.2.2',
```

The Notur framework version. Do not modify this manually.

### `extensions_path`

```php
'extensions_path' => 'notur/extensions',
```

The directory where extensions are stored, relative to the panel root. Change this if you want extensions stored elsewhere.

### `require_signatures`

```php
'require_signatures' => false,
```

When `true`, only extensions with valid Ed25519 signatures can be installed. Set to `true` for production environments where you want to ensure extension integrity. Set to `false` for development or trusted environments.

### `registry_url`

```php
'registry_url' => 'https://raw.githubusercontent.com/notur/registry/main',
```

The base URL of the extension registry. Change this to point to a private or self-hosted registry.

### `registry_cache_path`

```php
'registry_cache_path' => storage_path('notur/registry-cache.json'),
```

Where the local copy of the registry index is stored. The cache has a 1-hour TTL by default.

### `registry_cache_ttl`

```php
'registry_cache_ttl' => 3600,
```

The cache TTL in seconds. Set to `0` to disable cache expiry checks.

### `public_key`

```php
'public_key' => env('NOTUR_PUBLIC_KEY', ''),
```

The Ed25519 public key used for verifying extension signatures. Set via the `NOTUR_PUBLIC_KEY` environment variable or directly in the config.

## Directory Layout

After installation, Notur creates the following directories:

```
/var/www/pterodactyl/
  notur/
    extensions.json              # Registry of installed extensions + enabled state
    extensions/
      vendor/
        extension-name/          # Extension files
          extension.yaml         # Extension manifest
          src/                   # PHP source
          resources/             # Views, frontend source
          database/              # Migrations
  public/
    notur/
      bridge.js                  # Notur bridge runtime
      tailwind.css               # Shared Tailwind CSS (v4, no prefix)
      extensions/
        vendor/
          extension-name/
            extension.js         # Pre-built frontend bundle
  storage/
    notur/
      registry-cache.json        # Cached registry index
```

## Updating Notur

To update the Notur framework itself:

```bash
cd /var/www/pterodactyl
composer update notur/notur

# Re-apply patches if needed (the installer handles this)
curl -sSL https://docs.notur.site/install.sh | bash -s -- /var/www/pterodactyl
```

Rebuild the frontend:

::: code-group
```bash [npm]
npm run build:production
```

```bash [yarn]
yarn run build:production
```

```bash [pnpm]
pnpm run build:production
```

```bash [bun]
bun run build:production
```
:::

## Uninstalling Notur

To completely remove Notur from a panel:

```bash
cd /var/www/pterodactyl

# This will remove all extensions, restore patched files, drop Notur tables,
# remove directories, and trigger a frontend rebuild.
php artisan notur:uninstall

# Or skip interactive confirmation
php artisan notur:uninstall --confirm
```

The uninstall command performs:

1. Restores patched React files from `.notur-backup` copies (or applies reverse patches)
2. Rolls back Notur database migrations (drops `notur_extensions`, `notur_migrations`, `notur_settings`, `notur_activity_logs`)
3. Removes the `@include('notur::scripts')` Blade injection
4. Deletes the `notur/` and `public/notur/` directories
5. Runs `composer remove notur/notur`
6. Triggers a frontend rebuild to rebuild without Notur patches

## Troubleshooting

### Panel shows a blank page after installation

This usually means the frontend rebuild failed. Run:

::: code-group
```bash [npm]
cd /var/www/pterodactyl
npm install
npm run build:production
```

```bash [yarn]
cd /var/www/pterodactyl
yarn install
yarn run build:production
```

```bash [pnpm]
cd /var/www/pterodactyl
pnpm install
pnpm run build:production
```

```bash [bun]
cd /var/www/pterodactyl
bun install
bun run build:production
```
:::

Check for JavaScript errors in `resources/scripts/` -- the patches may not have applied cleanly. Verify with:

```bash
cd /var/www/pterodactyl
patch --dry-run -p1 < vendor/notur/notur/installer/patches/v1.11/routes.ts.patch
```

If it says "already applied," the patches are in place. If it fails, the panel version may be incompatible.

### Extensions are not loading

1. Check `notur/extensions.json` to ensure the extension is listed and `enabled` is `true`.
2. Verify the extension's PHP entrypoint class exists and is correctly autoloaded.
3. Check Laravel logs at `storage/logs/laravel.log` for any boot errors.
4. Verify the JS bundle exists at `public/notur/extensions/{vendor}/{name}/{bundle}.js`.

### Extension routes return 404

1. Make sure the extension implements `HasRoutes` and its `getRouteFiles()` returns the correct file paths.
2. Check the route group. Routes are prefixed with:
   - `api-client` routes: `/api/client/notur/{extension-id}/`
   - `admin` routes: `/admin/notur/{extension-id}/`
   - `web` routes: `/notur/{extension-id}/`
3. Run `php artisan route:list | grep notur` to see registered Notur routes.
4. Clear the route cache: `php artisan route:clear`.

### Extension slots are not rendering

1. Verify the bridge script loads: check for `<script src="/notur/bridge.js">` in the page source.
2. Check the browser console for errors.
3. Make sure the extension's frontend bundle calls `createExtension()` with the correct slot IDs.
4. Verify the panel was rebuilt after Notur installation.

### Database migration errors

If migrations fail, check that your database user has CREATE TABLE permissions. You can also run migrations manually:

```bash
php artisan migrate --path=vendor/notur/notur/database/migrations
```

### Registry sync fails

1. Check your network connection.
2. Verify the `registry_url` in `config/notur.php` is reachable.
3. Try with `--force` to bypass cache: `php artisan notur:registry:sync --force`.
4. Check if a proxy or firewall is blocking `raw.githubusercontent.com`.

### Permission denied errors

Ensure the web server user (typically `www-data` or `nginx`) has write access to:

- `notur/`
- `public/notur/`
- `storage/notur/`

```bash
chown -R www-data:www-data notur/ public/notur/ storage/notur/
chmod -R 755 notur/ public/notur/ storage/notur/
```
