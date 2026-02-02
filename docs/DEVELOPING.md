# Developing Notur

Guide for contributing to the Notur Extension Library itself.

## Prerequisites

- PHP 8.2+
- Composer 2.x
- Node.js 22+
- Yarn (recommended) or npm

## Setup

```bash
git clone <repo-url> NoturExtensionLib
cd NoturExtensionLib

# Install PHP dependencies
composer install

# Install frontend dependencies
yarn install
```

After running these commands, IDE errors for missing classes/types should resolve.

## Project Layout

```
src/                  PHP runtime (Laravel package)
bridge/               Frontend bridge runtime (TypeScript)
sdk/                  Extension developer SDK (TypeScript)
installer/            Installation scripts + React patches
registry/             JSON schemas + build tools
database/migrations/  Notur's own database tables
resources/views/      Blade templates shipped with Notur
config/               Laravel config file
routes/               Notur's own HTTP routes
tests/                Unit, integration, and frontend tests
docs/                 Documentation
```

## Building

```bash
# Build bridge runtime
yarn build:bridge

# Build SDK
yarn build:sdk

# Build everything
yarn build
```

## Testing

### PHP Tests

```bash
# All tests
./vendor/bin/phpunit

# Unit tests only
./vendor/bin/phpunit --testsuite Unit

# Integration tests only (requires orchestra/testbench)
./vendor/bin/phpunit --testsuite Integration

# With coverage
./vendor/bin/phpunit --coverage-text
```

### Frontend Tests

```bash
yarn test:frontend
```

## How the Pieces Fit Together

### PHP Side

`NoturServiceProvider` is the entry point. Laravel discovers it via the `extra.laravel.providers` key in `composer.json`. On boot:

1. Loads `config/notur.php` defaults
2. Registers Notur's own migrations, views, routes
3. Registers artisan commands
4. Calls `ExtensionManager::boot()` which:
   - Reads `{panel}/notur/extensions.json`
   - Loads each enabled extension's `extension.yaml` manifest
   - Resolves load order via `DependencyResolver` (topological sort)
   - Registers PSR-4 autoloading for each extension
   - Boots each extension (routes, middleware, events, views, commands)
5. A view composer injects frontend data into the `notur::scripts` Blade view

### Frontend Side

The bridge (`bridge/src/index.ts`) runs when `bridge.js` loads in the browser:

1. Creates a `PluginRegistry` instance on `window.__NOTUR__`
2. Applies default CSS custom properties
3. Exposes hooks, SlotRenderer, and ThemeProvider on the global

Extension bundles (loaded after bridge.js) call `window.__NOTUR__.registry.registerSlot()` or use `createExtension()` from the SDK to register their components.

The patched React files contain `<div id="notur-slot-*">` containers. The `SlotRenderer` uses React portals to render extension components into these containers.

## Testing Against a Real Panel

The `pterodactyl-panel/` directory contains a checkout of the panel for reference. To test the installer:

```bash
# Dry-run a patch to verify it applies
cd pterodactyl-panel
patch --dry-run -p1 < ../installer/patches/v1.11/routes.ts.patch
```

## Code Style

- PHP: PSR-12, strict types, PHP 8.2+ features (readonly, enums, etc.)
- TypeScript: strict mode, explicit types
- No unnecessary abstractions — keep it simple

## Patch Development

When the panel updates, patches may need updating. To create a new patch:

```bash
cd pterodactyl-panel

# Make your changes to the React file
# Then generate the patch:
git diff resources/scripts/routers/routes.ts > ../installer/patches/v1.11/routes.ts.patch
```

Always test patches with `--dry-run` first.
