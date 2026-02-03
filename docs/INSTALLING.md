# Installing Notur

## Automated Installation

The easiest way to install Notur into a Pterodactyl Panel:

```bash
curl -sSL https://docs.notur.site/install.sh | bash -s -- /var/www/pterodactyl
```

Replace `/var/www/pterodactyl` with your panel root path if different. This performs all steps below automatically.

## Manual Installation

### Step 1: Add Composer Package

```bash
cd /var/www/pterodactyl
composer require notur/notur
```

### Step 2: Patch the Blade Layout

Notur needs to inject its scripts into the panel's HTML. Edit `resources/views/layouts/scripts.blade.php` and add:

```blade
@include('notur::scripts')
```

This file is a minimal binder — adding the include here is the cleanest approach.

### Step 3: Apply React Patches

Four small patches add slot containers and dynamic route merging to the panel's React source:

```bash
cd /var/www/pterodactyl

# Apply each patch
patch -p1 < vendor/notur/notur/installer/patches/v1.11/routes.ts.patch
patch -p1 < vendor/notur/notur/installer/patches/v1.11/ServerRouter.tsx.patch
patch -p1 < vendor/notur/notur/installer/patches/v1.11/DashboardRouter.tsx.patch
patch -p1 < vendor/notur/notur/installer/patches/v1.11/DashboardContainer.tsx.patch
patch -p1 < vendor/notur/notur/installer/patches/v1.11/NavigationBar.tsx.patch
patch -p1 < vendor/notur/notur/installer/patches/v1.11/ServerTerminal.tsx.patch
patch -p1 < vendor/notur/notur/installer/patches/v1.11/FileManager.tsx.patch
```

**What the patches do:**

| File | Change |
|---|---|
| `routes.ts` | Adds `getNoturRoutes()` function that reads extension routes from `window.__NOTUR__` |
| `ServerRouter.tsx` | Adds slot containers for server navigation + renders extension server routes |
| `DashboardRouter.tsx` | Adds slot containers for dashboard/account navigation + renders extension dashboard/account routes |
| `DashboardContainer.tsx` | Adds dashboard header/footer and server list slots |
| `NavigationBar.tsx` | Adds slot containers in the top navigation bar |
| `ServerTerminal.tsx` | Adds console page slots + terminal button slot |
| `FileManager.tsx` | Adds file manager toolbar/header/footer slots |

### Step 4: Rebuild Frontend

```bash
# One-time rebuild — not needed again when installing/removing extensions
bun install
bun run build:production
```

### Step 5: Run Migrations

```bash
php artisan migrate
```

This creates three tables:
- `notur_extensions` — installed extension records
- `notur_migrations` — per-extension migration tracking
- `notur_settings` — per-extension key-value settings

### Step 6: Set Up Directories

```bash
mkdir -p notur/extensions
mkdir -p public/notur/extensions
echo '{"extensions":{}}' > notur/extensions.json
```

### Step 7: Install Bridge Runtime

The bridge JS must be built and placed in the panel's public directory:

```bash
cd vendor/notur/notur/bridge
bun install
bun run build
cp dist/bridge.js /var/www/pterodactyl/public/notur/bridge.js
```

## Verifying the Installation

1. Visit your panel — the page should load normally
2. Check the page source — you should see `window.__NOTUR__` and `bridge.js` script tags
3. Run `php artisan notur:list` — should show no extensions installed
4. Open the browser console — you should see `[Notur] Bridge runtime v1.0.0 initialized`

## Uninstalling Notur

1. Remove all extensions: `php artisan notur:list` then `php artisan notur:remove` for each
2. Restore backed-up files (the installer creates `.notur-backup` copies)
3. Rebuild frontend: `bun run build:production`
4. Remove Notur tables: `php artisan migrate:rollback` (the 3 notur tables)
5. Remove composer package: `composer remove notur/notur`
6. Remove directories: `rm -rf notur/ public/notur/`

## Compatibility

| Component | Supported Versions |
|---|---|
| Pterodactyl Panel | v1 canary / 1.11.x |
| PHP | 8.2, 8.3 |
| Node.js | 22+ |
| MySQL | 8.0+ |
| MariaDB | 10.6+ |
