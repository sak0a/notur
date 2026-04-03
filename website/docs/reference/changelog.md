# Notur Changelog

All notable changes to the Notur Extension Library are documented here.

## [1.3.2] - 2026-04-03

### Fixed
- **Registry URL** — Default registry URL pointed to non-existent `notur/registry` repo. Updated to `sak0a/notur/master/registry` in config, `RegistryClient`, and `NoturServiceProvider`.
- **Registry index** — Added `notur/cs2-modframework` v1.0.4 entry with `archive_url` pointing to GitHub release asset.
- **Error handling** — Controller endpoints in cs2-modframework now return JSON error messages instead of raw 500s. `VerifyServerAccess` middleware returns structured JSON errors for API routes and logs failures.
- **NoturArchive** — Excludes `.notur`, `.tar.gz`, and `.sig` files from packing to prevent checksum mismatches.
- **Installer version** — Updated installer banner version to 1.3.2.
- **CI** — PHP 8.4 only across all GitHub Actions workflows.

## [1.3.1] - 2026-04-03

### Fixed
- **Security:** Resolve all npm audit vulnerabilities (handlebars, picomatch, yaml, brace-expansion, serialize-javascript).
- **Security:** Update `league/commonmark` to 2.8.2 (CVE-2026-33347, CVE-2026-30838).
- **CI:** Fix Jest `Object.values` error by adding ES2018 lib target to jest config.
- **CI:** Regenerate `routes.ts` reverse patches from actual panel source for v1.11 and v1.12.
- **CI:** Upgrade `jest-environment-jsdom` to v30, update tests to use `history.pushState` for location mocking.

## [1.3.0] - 2026-04-02

### Added
- **Custom exception hierarchy** — `NoturException` base with `ExtensionNotFoundException`, `ManifestException`, `DependencyResolutionException`, `ExtensionBootException` for typed error handling across the framework.
- **`VerifyServerAccess` middleware** — Shared middleware for extension routes to verify authenticated user has server access (owner or subuser). Handles both full and short UUID. Usage: `->middleware('notur.server-access')`.
- **Lifecycle logging** — `ExtensionManager` logs manifest failures, boot summaries, missing entrypoints, and enable/disable state changes via Laravel's `Log` facade.
- **`recordDiagnosticError()` utility** — Bounded error recording (max 100 entries) for frontend diagnostics. Exposed on `window.__NOTUR__`.
- **Slot registration validation** — `PluginRegistry.registerSlot()` warns on unknown slot IDs and skips duplicate registrations from the same extension.
- **Bridge cleanup/teardown** — `window.__NOTUR__.cleanup()` disconnects all pending MutationObservers and unmounts all slot renderers.
- **Bridge version compatibility check** — `createExtension()` warns if SDK major version doesn't match bridge major version.
- **`SlotComponentProps` type** — Exported from `@notur/sdk` for typing extension slot components.
- **Middleware aliases** — `notur.server-access`, `notur.namespace`, `notur.permission` registered in `NoturServiceProvider`.

### Changed
- **`ExtensionManager` refactored** — Entrypoint resolution extracted to `EntrypointResolver` (~300 lines moved). Manager reduced from 803 to 567 lines.
- **`NewCommand` refactored** — File-writing logic extracted to `ScaffoldGenerator` (~690 lines moved). Command reduced from 1,031 to 326 lines.
- **`InstallCommand` / `RemoveCommand`** — Now extend `ExtensionLifecycleCommand` base class with shared `clearNoturCaches()` and `removeExtensionFiles()`.
- **`UninstallCommand`** — Uses `ManagesFilesystem` trait instead of duplicated `deleteDirectory()`.
- **`ExtensionManifest`** — Throws `ManifestException` instead of `InvalidArgumentException`.
- **`DependencyResolver`** — Throws `DependencyResolutionException` instead of `RuntimeException`.
- **`ErrorBoundary`** — Uses `recordDiagnosticError()` instead of direct array push.
- **`useUserContext`** — Logs `console.warn` and records diagnostics on fetch failure instead of silently swallowing errors.

### Fixed
- **hello-world example** — Removed deprecated `HasFrontendSlots` interface, now extends `NoturExtension` base class.
- **cs2-modframework extension** — Fixed version mismatch (PHP returned 1.0.0, manifest had 1.0.4). Now extends `NoturExtension` and uses `notur.server-access` middleware.

### Deprecated
- **`HasFrontendSlots` interface** — Define slots in frontend code via `createExtension({ slots: [...] })` instead.

## [1.2.4] - 2026-02-07

### Added
- **`notur:dev:pull` command** -- Pull the latest Notur framework code directly from GitHub for development. Downloads a specific branch (default `master`) or commit via the GitHub API, replaces `vendor/notur/notur/`, and automatically rebuilds the frontend bridge. Supports `--no-rebuild` and `--dry-run` flags.
- **`repository` config key** -- New `notur.repository` setting in `config/notur.php` (defaults to `sak0a/notur`). Used by `notur:dev:pull` to resolve the GitHub repository.

### Changed
- **Framework version bump to 1.2.4** -- Artisan command count increased from 16 to 17.
- **SDK unchanged** -- SDK package version remains unchanged for this release.

## [1.2.3] - 2026-02-07

### Changed
- **Framework version bump to 1.2.3** -- Documentation and framework version references updated for the extension framework release.
- **SDK unchanged** -- SDK package version remains unchanged for this release.

## [1.2.2] - 2026-02-07

### Changed
- **Framework version bump to 1.2.2** -- Documentation and framework version references updated for the extension framework release.
- **SDK unchanged** -- SDK package version remains unchanged for this release.

## [1.2.0] - 2026-02-05

### Added
- **Admin styles converted to Tailwind** -- Admin UI now uses Tailwind CSS classes instead of custom stylesheets.
- **Improved installer script** -- Better error handling and support for admin setup.

### Changed
- **SDK version bumped to 1.2.0** -- All workspace packages updated to 1.2.0.

## [1.1.1] - 2026-02-04

### Added
- **Admin health overview** -- `/admin/notur/health` aggregates extension health checks and highlights critical failures.
- **Registry cache status command** -- `notur:registry:status` shows cache age, TTL, size, and extension count (with `--json` for automation).
- **Slot enhancements** -- Slot registrations now support `priority`, `props`, and conditional rendering rules (`when`).
- **Dev watch mode** -- `notur:dev --watch` rebuilds extension bundles on change, with `--watch-bridge` for the runtime.
- **CLI/installer banner** -- Notur logo banner renders on installer and CLI commands (respects `NO_COLOR` and `NOTUR_NO_BANNER`).

### Changed
- **Registry cache behavior** -- Auto-refreshes on expiry and can fall back to stale cache if remote fetch fails.
- **Slot ordering** -- Slots are now sorted by priority (desc) then order (asc).

## [1.0.0] - 2026-02-03

### Added
- **E2E test suite** -- Docker-based end-to-end testing with Pterodactyl Panel, MySQL, and the hello-world extension. Covers install, enable, routes, disable, and remove lifecycle. (`docker/e2e/`, `tests/E2E/`, `.github/workflows/e2e.yml`)
- **Compatibility matrix testing** -- CI now tests PHP 8.2/8.3 with both MySQL 8.0 and MariaDB 10.6, and frontend builds on Node 18, 20, and 22. Unit tests run separately from integration tests to enable proper DB-dependent testing.
- **Documentation site** -- Comprehensive documentation for administrators ([Admin guide](/admin/guide)), PHP API reference ([PHP API Reference](/extensions/api-reference)), frontend SDK reference ([Frontend SDK](/extensions/frontend-sdk)), registry documentation ([Registry](/admin/registry)), and this changelog.
- **Signature verification** -- Ed25519 signatures for `.notur` archives with `notur.require_signatures` enforcement, `notur:keygen`, and `notur:export --sign`.
- **Admin Blade UI** -- Extension management page with list, enable/disable, install/remove, and detail views.
- **Theme extensions** -- CSS variable overrides and Blade view overrides from extension manifests.

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
- **All slot renderers** -- Expanded slot set (65 total) including headers/footers, console/file manager slots, and dashboard server list slots.
- **Additional React patches** -- `DashboardContainer.tsx`, `ServerConsoleContainer.tsx`, `FileManagerContainer.tsx`, and navigation/router updates to expose the new slot containers.
- **Bridge hooks** -- `useSlot(slotId)` for reactive slot subscriptions, `useExtensionApi({ extensionId })` for scoped HTTP client with CSRF handling, `useExtensionState(extensionId, initialState)` for shared cross-component state.
- **Theme system** -- CSS custom property extraction from the live Pterodactyl DOM. Three-strategy approach: mapped `--ptero-*` variables, computed style probes on known elements, and stylesheet scanning. `ThemeProvider` wiring with 25 default CSS custom properties.
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
