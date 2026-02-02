# Notur — Roadmap

## Phase 2: CLI + Extension Lifecycle

- [x] **`notur:uninstall` command** — Full framework removal:
  - Restore patched React files from `.notur-backup` copies
  - Roll back all Notur database migrations (3 tables)
  - Remove Blade injection (`@include('notur::scripts')`)
  - Delete `notur/` directory and `public/notur/` assets
  - Run `composer remove notur/notur`
  - Trigger frontend rebuild (`yarn build:production`)
- [x] **Reverse patches** — Generate and ship reverse `.patch` files for each React patch so uninstall can cleanly revert without relying on backup copies
- [x] **`notur:install` command** — Install extensions from registry or local path
- [x] **`notur:remove` command** — Remove an installed extension (rollback migrations, delete files, update manifest)
- [x] **`notur:enable` / `notur:disable`** — Toggle extensions without removing files
- [x] **`notur:update`** — Update an extension to a newer version (run new migrations, swap files)
- [x] **`MigrationManager` integration testing** — Verify per-extension migrate/rollback against real DB
- [x] **`PermissionBroker` enforcement** — Middleware-level permission checks on extension routes

## Phase 3: Frontend Slots + SDK

- [x] **All slot renderers** — Wire up remaining slots: `server.subnav`, `server.page`, `server.terminal.buttons`, `server.files.actions`, `account.page`, `account.subnav`
- [x] **Additional React patches** — `ServerRouter.tsx` subnav/page slots, terminal/file-manager slots
- [x] **Bridge hooks** — Finish `useSlot`, `useExtensionApi`, `useExtensionState` with real API integration
- [x] **Theme system** — CSS variable extraction from panel's tailwind config, `ThemeProvider` wiring, theme extension support
- [x] **SDK CLI scaffolding** — `notur:new` command to generate extension boilerplate from templates
- [x] **SDK webpack config** — Validate base webpack config works for typical extension builds
- [x] **SDK type exports** — Publish `@notur/sdk` types for extension developers

## Phase 4: Registry + Distribution

- [x] **`RegistryClient`** — Fetch extension metadata from GitHub-backed registry
- [x] **`notur:registry:sync`** — Update local registry cache
- [x] **`.notur` packaging** — tar.gz archive format with checksums + optional signature
- [x] **`notur:export`** — Package an extension into `.notur` archive
- [x] **Registry index builder** — `build-index.php` tool to generate `registry.json` from GitHub repos
- [x] **JSON schema validation** — Validate manifests and registry index against shipped schemas

## Phase 5: Security + Admin UI + Polish

- [x] **Signature verification** — Ed25519 signatures on `.notur` archives; `notur.require_signatures` config
- [x] **Admin Blade UI** — Extension management page: list, enable/disable, install/remove, view logs
- [x] **Theme extensions** — Full theme override support (CSS variables + Blade view overrides)
- [x] **E2E test suite** — Docker-based: fresh Pterodactyl + Notur install, extension lifecycle, frontend verification
- [x] **Compatibility matrix testing** — PHP 8.2/8.3, MySQL 8.0/MariaDB 10.6+, Node 18/20/22
- [x] **Documentation site** — Expand docs for extension developers and panel administrators

## Bugs / Tech Debt

- [ ] Frontend tests (`yarn test:frontend`) — Jest config not finalized; CI allows failure
- [ ] Integration tests require Orchestra Testbench + SQLite — verify they run in CI
- [ ] Installer: validate on non-macOS Linux (current fixes are macOS-oriented)
- [ ] Patch checksums — installer stores checksums but never validates them on subsequent runs
- [ ] `DevCommand` (`notur:dev`) — Symlink-based dev mode needs testing with real extension
