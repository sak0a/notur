# Notur Changelog

All notable changes to the Notur Extension Library are documented here.

## [Unreleased] - Phase 5 (In Progress)

### Added
- **E2E test suite** -- Docker-based end-to-end testing with Pterodactyl Panel, MySQL, and the hello-world extension. Covers install, enable, routes, disable, and remove lifecycle. (`docker/e2e/`, `tests/E2E/`, `.github/workflows/e2e.yml`)
- **Compatibility matrix testing** -- CI now tests PHP 8.2/8.3 with both MySQL 8.0 and MariaDB 10.6, and frontend builds on Node 18, 20, and 22. Unit tests run separately from integration tests to enable proper DB-dependent testing.
- **Documentation site** -- Comprehensive documentation for administrators (`docs/ADMIN-GUIDE.md`), PHP API reference (`docs/API-REFERENCE.md`), frontend SDK reference (`docs/FRONTEND-SDK.md`), registry documentation (`docs/REGISTRY.md`), and this changelog.

### Not Yet Implemented
- Signature verification (Ed25519 on `.notur` archives)
- Admin Blade UI (extension management page)
- Theme extensions (full theme override support)

---

## Phase 4: Registry + Distribution

### Added
- **`RegistryClient`** -- Fetch extension metadata from the GitHub-backed registry. Supports search by ID, name, description, and tags. Includes local caching with configurable TTL.
- **`notur:registry:sync` command** -- Sync the remote registry index to a local cache. Supports `--search` for inline queries and `--force` to bypass cache TTL.
- **`.notur` archive format** -- Extensions are packaged as gzipped tar archives with SHA-256 checksums and optional Ed25519 signatures.
- **`notur:export` command** -- Package an extension directory into a `.notur` archive with checksum generation. Supports `--sign` for signature creation and `--output` for custom output paths.
- **Registry index builder** -- `registry/tools/build-index.php` generates `registry.json` from local directories or GitHub repositories.
- **JSON schema validation** -- Shipped schemas for `extension.yaml` manifests (`registry/schema/extension-manifest.schema.json`) and the registry index (`registry/schema/registry-index.schema.json`).

---

## Phase 3: Frontend Slots + SDK

### Added
- **All slot renderers** -- Nine predefined slots wired up: `navbar`, `server.subnav`, `server.page`, `server.terminal.buttons`, `server.files.actions`, `dashboard.widgets`, `dashboard.page`, `account.page`, `account.subnav`.
- **Additional React patches** -- `ServerRouter.tsx` for server subnav/page slots, terminal button and file manager toolbar slots.
- **Bridge hooks** -- `useSlot(slotId)` for reactive slot subscriptions, `useExtensionApi({ extensionId })` for scoped HTTP client with CSRF handling, `useExtensionState(extensionId, initialState)` for shared cross-component state.
- **Theme system** -- CSS custom property extraction from the live Pterodactyl DOM. Three-strategy approach: mapped `--ptero-*` variables, computed style probes on known elements, and stylesheet scanning. `ThemeProvider` wiring with 23 default CSS custom properties.
- **`notur:new` command** -- Scaffold new extensions from templates. Generates `extension.yaml`, PHP entrypoint, route file, frontend entry, and webpack config.
- **SDK webpack config** -- Base webpack configuration for extension builds. Externalizes React/ReactDOM, configures TypeScript, outputs UMD bundles.
- **SDK type exports** -- Published `@notur/sdk` TypeScript types: `ExtensionConfig`, `SlotConfig`, `RouteConfig`, `ExtensionDefinition`, `NoturApi`.
- **SDK hooks** -- `useServerContext()`, `useUserContext()`, `usePermission()` for accessing Pterodactyl panel context from extension components.

---

## Phase 2: CLI + Extension Lifecycle

### Added
- **`notur:uninstall` command** -- Complete framework removal: restores patched files, rolls back migrations, removes Blade injection, deletes directories, removes Composer package, triggers frontend rebuild.
- **Reverse patches** -- Shipped reverse `.patch` files for each React patch to enable clean uninstallation without relying on backup copies.
- **`notur:install` command** -- Install extensions from the registry (by ID) or from local `.notur` archive files. Supports `--force` for reinstallation and `--no-migrate` to skip migrations.
- **`notur:remove` command** -- Remove an installed extension: disables it, rolls back its migrations (unless `--keep-data`), deletes files, and updates the manifest.
- **`notur:enable` / `notur:disable` commands** -- Toggle extensions on/off without removing files or data. Dispatches `ExtensionEnabled` / `ExtensionDisabled` events and clears the cache.
- **`notur:update` command** -- Check for and install extension updates from the registry. Supports `--check` for dry-run mode and updating individual or all extensions.
- **`MigrationManager` integration testing** -- Per-extension migration tracking via the `notur_migrations` table. Verified migrate/rollback against real database.
- **`PermissionBroker` enforcement** -- Middleware-level permission checks on extension routes based on the permissions declared in `extension.yaml`.

---

## Phase 1: Core Architecture

### Added
- **`NoturServiceProvider`** -- Laravel service provider: loads config, registers migrations/views/routes, registers artisan commands, boots the `ExtensionManager`.
- **`ExtensionManager`** -- Core extension lifecycle: reads `extensions.json`, loads manifests, resolves dependencies via topological sort (`DependencyResolver`), registers PSR-4 autoloading, boots extensions.
- **Extension contracts** -- Eight PHP interfaces: `ExtensionInterface`, `HasRoutes`, `HasMigrations`, `HasCommands`, `HasMiddleware`, `HasEventListeners`, `HasBladeViews`, `HasFrontendSlots`.
- **Database schema** -- Three tables: `notur_extensions` (installed extensions), `notur_migrations` (per-extension migration tracking), `notur_settings` (key-value settings).
- **Installer** -- `installer/install.sh` script for automated Notur setup. Validates prerequisites, installs via Composer, applies 4 React patches, injects Blade include, runs migrations, builds bridge.
- **React patches** -- Four patches for Pterodactyl v1.11: `routes.ts` (dynamic route merging), `ServerRouter.tsx` (server slots), `DashboardRouter.tsx` (dashboard slots), `NavigationBar.tsx` (navbar slot).
- **Bridge runtime** -- TypeScript bridge (`bridge/src/`): `PluginRegistry`, `SlotRenderer` (React portal-based), event system, and global `window.__NOTUR__` API.
- **Hello-world example** -- Minimal reference extension (`examples/hello-world/`) demonstrating PHP entrypoint, API routes, frontend bundle, and slot registration.
- **CI pipeline** -- GitHub Actions workflow for PHP tests (8.2/8.3), frontend builds, and patch validation.
- **`notur:list` command** -- List installed extensions with status table.
- **`notur:dev` command** -- Symlink-based development mode for local extension testing.
- **`notur:build` command** -- Build extension frontend assets.
- **Scoped namespacing** -- Routes, permissions, migrations, config, and frontend bundles are all namespaced per-extension to prevent collisions.
