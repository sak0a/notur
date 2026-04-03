<p align="center">
  <img src="notur-logo.png" alt="Notur" height="80">
</p>

# Notur Extension Library

![Version](https://img.shields.io/badge/version-1.3.2-blue)
![Status](https://img.shields.io/badge/status-stable-brightgreen)

A standalone extension framework for [Pterodactyl Panel](https://pterodactyl.io/) v1. Enables community-built extensions (plugins, themes, tools) that modify panel functionality without forking the source.

## Key Features

- **No per-extension rebuilds** — extensions ship pre-built JS bundles loaded at runtime
- **Clean architecture** — no sed-based injection or file patching per extension
- **One-time install** — patches React files + rebuilds once during Notur setup
- **Full lifecycle management** — install, enable, disable, update, remove via artisan
- **Interactive CLI** — beautiful terminal UI with search, wizards, and status dashboard
- **Frontend slot system** — React portal-based rendering into predefined panel locations
- **Scoped namespacing** — routes, permissions, migrations, and config are all extension-scoped
- **Registry support** — GitHub-backed extension registry with optional Ed25519 signatures

## Requirements

- Pterodactyl Panel v1 (canary/1.11+)
- PHP 8.2+
- Node.js 22+ (matches panel requirement)
- Composer 2.x
- Package manager: npm, Yarn, pnpm, or Bun

## Current Status (v1.3.0 — 2026-04-02)

Notur is feature-complete for extension lifecycle, registry distribution, admin management, and theming.

v1.3.0 highlights:

- Custom exception hierarchy for typed error handling
- `VerifyServerAccess` middleware for extension server-scoped routes
- Lifecycle logging via Laravel Log facade
- Frontend slot validation and duplicate registration guard
- Bounded diagnostics with `recordDiagnosticError()`
- Bridge cleanup/teardown support (`window.__NOTUR__.cleanup()`)
- Bridge/SDK version compatibility checking
- Major internal refactors: `EntrypointResolver`, `ScaffoldGenerator`, `ManagesFilesystem` trait

See the [Changelog](CHANGELOG.md) for release details.

## Example Extensions

- `examples/hello-world` -- minimal starter.
- `examples/full-extension` -- full-stack reference (backend routes, admin view, migration, frontend slot/route, tests).

## Roadmap (Next)

Near-term focus areas:

- Frontend test coverage expansion (SlotRenderWhen, CssVariables, ThemeProvider)
- Command integration tests (InstallCommand, BuildCommand)
- Extension dev hot-reload (file-watcher + auto-rebuild)
- Pelican Panel compatibility investigation

See `ROADMAP.md` for the full backlog.

## Installation (into a Pterodactyl Panel)

```bash
# Option 1: Automated installer
curl -sSL https://docs.notur.site/install.sh | bash -s -- /path/to/pterodactyl

# Option 2: Manual
cd /path/to/pterodactyl
composer require notur/notur
php artisan migrate
# Then apply patches and rebuild frontend (see https://docs.notur.site/getting-started/installing)
```

## Development Setup (working on Notur itself)

```bash
# Install PHP dependencies
composer install

# Install frontend dependencies (using npm, yarn, pnpm, or bun)
npm install

# Build the bridge runtime
npm run build:bridge

# Build the SDK
npm run build:sdk

# Run PHP tests
./vendor/bin/phpunit

# Run frontend tests
npm run test:frontend
```

## Architecture

```
Panel Request
    └─> Laravel boots NoturServiceProvider
        └─> ExtensionManager discovers enabled extensions
            └─> Loads in dependency order (topological sort)
            └─> Registers routes, middleware, events, views, commands
            └─> Collects frontend slot data

Panel Response (HTML)
    └─> wrapper.blade.php includes notur::scripts
        └─> Outputs window.__NOTUR__ config JSON
        └─> Loads bridge.js (PluginRegistry + SlotRenderer)
        └─> Loads each extension's JS bundle
            └─> Extensions register components into slots
            └─> Bridge renders via React portals into <div id="notur-slot-*">
```

## Extension Lifecycle

```bash
php artisan notur:install acme/server-analytics   # Install from registry
php artisan notur:enable acme/server-analytics     # Enable
php artisan notur:disable acme/server-analytics    # Disable
php artisan notur:remove acme/server-analytics     # Uninstall + rollback migrations
php artisan notur:list                             # Show all installed
php artisan notur:update                           # Check for updates
php artisan notur:status                           # System status dashboard
```

## Project Structure

| Directory | Contents |
|---|---|
| `src/` | PHP runtime — Laravel service provider, extension manager, models, commands |
| `bridge/` | Frontend bridge runtime — PluginRegistry, SlotRenderer, hooks, theme |
| `sdk/` | Extension developer SDK — createExtension factory, types, scaffolding |
| `installer/` | Installer script + React patches for Pterodactyl |
| `registry/` | JSON schemas + registry build tools |
| `database/migrations/` | 4 tables: `notur_extensions`, `notur_migrations`, `notur_settings`, `notur_activity_logs` |
| `tests/` | Unit, integration, and frontend tests |

## Creating an Extension

See the [Extension Development Guide](https://docs.notur.site/extensions/guide) for the full guide.

Quick start:

```bash
php artisan notur:new acme/server-analytics
```

### Preset Definitions

- `standard`: frontend + API routes (default)
- `backend`: API routes only
- `full`: frontend + API routes + admin UI + migrations + tests
- `minimal`: backend-only scaffolding with no routes or frontend

Manual steps:
1. Create an `extension.yaml` manifest
2. Extend `NoturExtension` base class in PHP
3. Build a frontend bundle using `@notur/sdk`
4. Export with `php artisan notur:export`

## License

MIT
