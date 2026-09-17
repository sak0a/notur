# Installing Notur

## Automated Installation

The easiest way to install Notur into a Pterodactyl Panel:

```bash
curl -fsSL https://docs.notur.site/install.sh | bash -s -- /var/www/pterodactyl
```

Replace `/var/www/pterodactyl` with your panel root path if different. Run inside the panel container for Docker installations.

Before starting, provide PHP 8.2+, Composer, a supported panel version, a reachable configured database, and write access to the panel directory. Node 22+ is required for the panel; building this Notur release from source requires `^22.22.2 || ^24.15.0 || >=26.0.0`. Use Node 24.15+ for both. The installer checks the installed Notur package's own engine range when building its assets. Bash must already exist to launch the script (minimal Alpine images may need `apk add --no-cache bash curl`).

The installer bootstraps missing system build tools through apt/apk/dnf/yum/pacman; this needs root privileges. PHP and Composer must already be installed. Missing Node installation is interactive; provision a compatible Node version in the image for unattended deployments. Existing tools are reused. Build dependencies are installed even when `NODE_ENV=production`.

Panel lockfiles select the package manager from the panel directory, regardless of where the installer was launched. `PKG_MANAGER=npm` (or `yarn`, `pnpm`, `bun`) overrides selection. Yarn Classic is bootstrapped when needed for a Yarn panel. Notur's runtime assets use its own lockfile separately. Extension archives must contain their compiled JS/CSS; installing an extension does not rebuild the panel.

## Repeat installation, upgrades, and backups

Run the installer again to reconcile or upgrade an existing installation. Applied patches and extension state are recognized. Conflicting patches, missing runtime assets, migration failures, and failed builds stop installation with a nonzero exit status.

Before Composer changes, patches, or builds, the installer saves a private file snapshot under `storage/notur/backups/install-*/files.tar.gz`. Extension replacement/removal and framework uninstall also retain private file snapshots there. These include previous source and public assets; **they do not include database data**. Take a matching database backup before upgrades or removal. Backups are retained until you explicitly prune them.

Recovery is manual: stop serving traffic, inspect/extract the snapshot into a temporary directory, restore the affected files (removing newly introduced files if needed), restore the matching database backup when migrations ran, run `composer install` against the restored lockfile, and clear Laravel caches. A failed migration can leave database changes even when extension files are restored.

## Coolify and Docker

Persist these paths, adjusted for your panel root (`/app` in some images):

```yaml
volumes:
  - notur-data:/var/www/pterodactyl/notur
  - notur-public:/var/www/pterodactyl/public/notur
  - notur-backups:/var/www/pterodactyl/storage/notur
```

Declare the named volumes in your Compose file. Preserve the panel's existing storage and database mounts too. In Coolify, configure equivalent persistent storage and verify ownership matches the PHP/web process user.

A container restart preserves its writable layer, but a redeploy/image replacement does not. These mounts alone do not preserve `vendor/`, Composer manifests, patched `resources/`, or built `public/assets/`. Include the framework, patches and frontend build in a custom image, or rerun the installer against a reachable database before routing traffic after every replacement. The complete installer runs database migrations, so it is not a database-independent Docker build step. Refresh persisted `public/notur` assets on each framework upgrade to avoid old volume contents masking new image assets.

See [Coolify persistent storage](https://coolify.io/docs/applications/configuration/persistent-storage). Persistent storage is not a database backup.

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

The patch set adds slot containers and dynamic route merging to the panel's React source. The automated installer selects the right patch version (`v1.12` or `v1.15`) automatically. For manual installation, pick the folder that matches your panel version and apply the core patches:

```bash
cd /var/www/pterodactyl

# Choose the matching patch set for your panel version:
# Use v1.12 for Panel 1.12.x; v1.15 for Panel 1.15.0–1.15.1.
PATCH_SET=v1.15

# Apply core patches (required for basic functionality)
patch -p1 < vendor/notur/notur/installer/patches/${PATCH_SET}/routes.ts.patch
patch -p1 < vendor/notur/notur/installer/patches/${PATCH_SET}/ServerRouter.tsx.patch
patch -p1 < vendor/notur/notur/installer/patches/${PATCH_SET}/DashboardRouter.tsx.patch
patch -p1 < vendor/notur/notur/installer/patches/${PATCH_SET}/DashboardContainer.tsx.patch
patch -p1 < vendor/notur/notur/installer/patches/${PATCH_SET}/NavigationBar.tsx.patch
patch -p1 < vendor/notur/notur/installer/patches/${PATCH_SET}/ServerTerminal.tsx.patch
patch -p1 < vendor/notur/notur/installer/patches/${PATCH_SET}/FileManager.tsx.patch

# For full functionality, apply all patches from installer/patches/${PATCH_SET}/
# (excluding *.reverse.patch files)
```

**What the core patches do:**

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

One-time rebuild — not needed again when installing/removing extensions.

::: code-group
```bash [npm]
npm install
npm run build:production
```

```bash [yarn]
yarn install
yarn run build:production
```

```bash [pnpm]
pnpm install
pnpm run build:production
```

```bash [bun]
bun install
bun run build:production
```
:::

### Step 5: Run Migrations

```bash
php artisan migrate
```

This creates five tables:
- `notur_extensions` — installed extension records
- `notur_migrations` — per-extension migration tracking
- `notur_settings` — per-extension key-value settings
- `notur_activity_logs` — extension activity audit trail
- `notur_remote_push_keys` — remote push API keys

### Step 6: Set Up Directories

```bash
mkdir -p notur/extensions
mkdir -p public/notur/extensions
test -f notur/extensions.json || echo '{"extensions":{}}' > notur/extensions.json
```

### Step 7: Install Frontend Runtime Assets

Build the bridge JS and the shared Tailwind CSS, then place them in the panel's public directory:

::: code-group
```bash [npm]
cd vendor/notur/notur
npm install
npm run build:bridge
npm run build:tailwind
cp bridge/dist/bridge.js /var/www/pterodactyl/public/notur/bridge.js
cp bridge/dist/tailwind.css /var/www/pterodactyl/public/notur/tailwind.css
```

```bash [yarn]
cd vendor/notur/notur
yarn install
yarn run build:bridge
yarn run build:tailwind
cp bridge/dist/bridge.js /var/www/pterodactyl/public/notur/bridge.js
cp bridge/dist/tailwind.css /var/www/pterodactyl/public/notur/tailwind.css
```

```bash [pnpm]
cd vendor/notur/notur
pnpm install
pnpm run build:bridge
pnpm run build:tailwind
cp bridge/dist/bridge.js /var/www/pterodactyl/public/notur/bridge.js
cp bridge/dist/tailwind.css /var/www/pterodactyl/public/notur/tailwind.css
```

```bash [bun]
cd vendor/notur/notur
bun install
bun run build:bridge
bun run build:tailwind
cp bridge/dist/bridge.js /var/www/pterodactyl/public/notur/bridge.js
cp bridge/dist/tailwind.css /var/www/pterodactyl/public/notur/tailwind.css
```
:::

## Verifying the Installation

1. Visit your panel — the page should load normally
2. Check the page source — you should see `window.__NOTUR__` and `bridge.js` script tags
3. Run `php artisan notur:list` — should show no extensions installed
4. Open the browser console — you should see `[Notur] Bridge runtime vX.X.X initialized`

## Extension lifecycle

```bash
php artisan notur:add vendor/name
php artisan notur:add ./extension.notur
php artisan notur:update --check
php artisan notur:update vendor/name
php artisan notur:update --force --no-interaction
php artisan notur:disable vendor/name
php artisan notur:enable vendor/name
php artisan notur:remove vendor/name --force
# Keep settings and database tables instead of rolling migrations back:
php artisan notur:remove vendor/name --force --keep-data
```

An existing extension requires `notur:update` or `notur:add --force` to replace it. Upgrades preserve its enabled/disabled state. Compatibility and extension dependencies are checked before replacement. Composer requirements in an extension must be satisfied by the panel runtime; install compatible third-party dependencies in the panel first. Archives exclude `vendor/` and `node_modules/`; the installer does not silently modify panel Composer dependencies on an extension's behalf.

A rollback failure stops removal with files and registration retained and the extension disabled. Missing migration files keep their tracking records for recovery. `--keep-data` is the explicit way to remove code while preserving database data.

## Uninstalling Notur

Take a database backup, then run:

```bash
php artisan notur:framework:uninstall --confirm --no-interaction
```

This retains a file snapshot, reverses patches, rebuilds the panel, removes extensions in dependency order with migration rollback, removes framework tables and its exact migration records, cleans the Blade include/configuration/caches, and removes the Composer package. Patch conflicts, failed builds, or failed extension rollback stop the operation. Check the exit status; a stopped uninstall may have completed earlier steps. Backups remain under `storage/notur/backups`.

Do not use a generic `migrate:rollback` to uninstall: the latest migration batch may contain unrelated panel migrations.

## Compatibility

| Component | Supported Versions |
|---|---|
| Pterodactyl Panel | 1.12.x, 1.15.0–1.15.1 |
| PHP | 8.2–8.5 (8.4+ recommended) |
| Node.js | 24 LTS recommended; see development requirements |
| Package Manager | npm, Yarn, pnpm, or Bun |
| MySQL | 8.4 LTS |
| MariaDB | 11.4 LTS |
