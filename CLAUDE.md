# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Notur is an extension framework for Pterodactyl Panel v1 that enables community-built extensions (plugins, themes, tools) without forking the panel. It's a dual-stack system: PHP/Laravel backend for extension lifecycle management and TypeScript/React frontend for runtime component injection via slots.

Extensions ship pre-built JS bundles loaded at runtime — no per-extension rebuilds of the panel are needed.

## Commands

### PHP
```bash
composer install
./vendor/bin/phpunit                        # All tests (SQLite in-memory)
./vendor/bin/phpunit --testsuite Unit       # Unit tests only
./vendor/bin/phpunit --testsuite Integration
./vendor/bin/phpunit tests/Unit/SomeTest.php  # Single test file
```

### Frontend (workspaces: bridge + sdk)
Use npm, yarn, pnpm, or bun:
```bash
npm install
npm run build              # Build bridge + sdk
npm run build:bridge       # Build bridge runtime (webpack)
npm run build:sdk          # Build SDK (tsc)
npm run dev:bridge         # Watch mode for bridge
npm run test:frontend      # Jest tests
```

## Architecture

### PHP Backend (`src/`)

**Entry point:** `NoturServiceProvider` — Laravel service provider registered via composer.json `extra.laravel.providers`. Boots extension loading, migrations, views, routes, and artisan commands.

**Core flow:**
1. `ExtensionManager` (singleton) reads `notur/extensions.json`, loads enabled extension manifests (YAML via `ExtensionManifest`), resolves load order via `DependencyResolver` (topological sort with circular dependency detection), registers PSR-4 autoloading per extension, delegates entrypoint resolution to `EntrypointResolver`, then boots them in order. Lifecycle events are logged via Laravel's `Log` facade.
2. Extensions extend `NoturExtension` base class (which auto-reads metadata from `extension.yaml`) and optionally mix in capability contracts from `Contracts/` (`HasRoutes`, `HasMigrations`, `HasCommands`, `HasMiddleware`, `HasEventListeners`, `HasBladeViews`, `HasHealthChecks`). `HasFrontendSlots` is **deprecated** — define slots in frontend code via `createExtension()`.
3. `MigrationManager` handles per-extension DB migrations tracked in `notur_migrations`.
4. `PermissionBroker` scopes permissions as `notur.{ext-id}.{permission}`.
5. Custom exceptions (`src/Exceptions/`): `NoturException` base, `ExtensionNotFoundException`, `ManifestException`, `DependencyResolutionException`, `ExtensionBootException`.

**Key support classes:**

- `EntrypointResolver` — resolves extension PHP entrypoint classes via manifest, composer.json, convention, or filesystem discovery.
- `ScaffoldGenerator` — generates all files for `notur:new` scaffolding (used by `NewCommand`).
- `VerifyServerAccess` middleware — verifies authenticated user has access to a server (owner or subuser). Use in extension routes: `->middleware('notur.server-access')`.

**Route groups** are auto-prefixed per extension:
- `api-client` → `/api/client/notur/{ext-id}/`
- `admin` → `/admin/notur/{ext-id}/`
- `web` → `/notur/{ext-id}/`

**Database tables:** `notur_extensions`, `notur_migrations`, `notur_settings`, `notur_activity_logs` (migrations in `database/migrations/`).

**Artisan commands** (17 total in `src/Console/Commands/`): `notur:install`, `notur:remove`, `notur:enable`, `notur:disable`, `notur:list`, `notur:update`, `notur:dev`, `notur:dev:pull`, `notur:build`, `notur:export`, `notur:registry:sync`, `notur:uninstall`, `notur:new`, `notur:validate`, `notur:status`, `notur:keygen`, `notur:registry:status`.

### Frontend Bridge (`bridge/src/`)

Exposes `window.__NOTUR__` global API on page load. Core components:

- **PluginRegistry** — Event-emitter-based registry for slots, routes, and extensions. Extensions call `registerSlot()` / `registerRoute()` to inject themselves.
- **SlotRenderer** — React component that renders registered slot components via portals into `<div id="notur-slot-*">` containers.
- **Hooks:** `useSlot` (get components for a slot), `useExtensionApi` (scoped HTTP client), `useExtensionState` (shared state), `useNoturTheme`.
- **Diagnostics:** `recordDiagnosticError()` — bounded error recording (max 100 entries) exposed on `window.__NOTUR__`.
- **Validation:** Slot registration warns on unknown slot IDs and blocks duplicate registrations from the same extension.
- **Cleanup:** `window.__NOTUR__.cleanup()` disconnects MutationObservers and unmounts all slot renderers.

### SDK (`sdk/src/`)

`createExtension()` factory that extension developers use to register slots, routes, and lifecycle hooks (`onInit`, `onDestroy`).

### Installer (`installer/`)

`install.sh` automates: composer require, applying React patches to Pterodactyl Panel v1.11 (26 patches in `installer/patches/v1.11/`), migrations, directory setup, and bridge build.

## Code Style

- PHP: PSR-12, strict types, PHP 8.2+ features (readonly, enums)
- TypeScript: strict mode enabled
- Namespace: `Notur\` for PHP, autoloaded from `src/`
- Test namespace: `Notur\Tests\` from `tests/`

## Testing

- PHP tests use Orchestra Testbench with SQLite in-memory DB
- Frontend tests use Jest + React Testing Library
- Test suites: `Unit` and `Integration` (phpunit.xml), `Frontend` (jest.config.js in tests/Frontend/)
