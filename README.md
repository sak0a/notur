<p align="center">
  <img src="notur-logo.png" alt="Notur" height="80">
</p>

# Notur Extension Library

![Version](https://img.shields.io/badge/version-1.4.7-blue)
![Status](https://img.shields.io/badge/status-stable-brightgreen)

A standalone extension framework for [Pterodactyl Panel](https://pterodactyl.io/) v1. Enables community-built extensions (plugins, themes, tools) that modify panel functionality without forking the source.

## Key Features

- **No per-extension rebuilds** — extensions ship pre-built JS bundles loaded at runtime
- **Clean architecture** — no sed-based injection or file patching per extension
- **One-time install** — patches React files + rebuilds once during Notur setup
- **Full lifecycle management** — install, enable, disable, update, remove via artisan
- **Interactive CLI** — beautiful terminal UI with search, wizards, and status dashboard
- **Frontend slot system** — React portal-based rendering into predefined panel locations
- **Manifest-only frontend extensions** — UI-only extensions do not need a PHP entrypoint
- **Developer SDK CLI** — scaffold, sync, validate, doctor, package, and push extensions locally
- **Remote packaged push** — push trusted local builds to a Notur-enabled panel using API keys
- **Scoped namespacing** — routes, permissions, migrations, and config are all extension-scoped
- **Registry support** — GitHub-backed extension registry with optional Ed25519 signatures

## Requirements

- Pterodactyl Panel v1.12+
- PHP 8.2+
- Node.js 22+ (matches panel requirement)
- Composer 2.x
- Package manager: npm, Yarn, pnpm, or Bun

## Example Extensions

- `examples/hello-world` -- minimal starter.
- `examples/full-extension` -- full-stack reference (backend routes, admin view, migration, frontend slot/route, tests).

## Roadmap (Next)

Near-term focus areas:

- Frontend test coverage expansion (SlotRenderWhen, CssVariables, ThemeProvider)
- Command integration tests (AddCommand, BuildCommand)
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

# Install the root frontend dependency graph from its lockfile
npm ci

# Build the bridge runtime
npm run build:bridge

# Build the SDK
npm run build:sdk

# Run PHP tests
./vendor/bin/phpunit

# Run frontend tests
npm run test:frontend
```

The root project uses `package-lock.json` for CI builds, npm audit, and releases.
The docs site in `website/` has its own `bun.lock`; the hello-world example has
its own `package-lock.json`. PHP CI uses the committed Laravel 11 lock on PHP
8.2–8.4, resolves recent Laravel 10/Testbench 8 dependencies on PHP 8.3,
and tests the oldest stable Laravel 10 dependencies on PHP 8.2. Docker E2E
continues to cover PHP 8.2 with one panel configuration rather than duplicating
the PHP matrix.

## Docker E2E

The repo ships with a real browser-backed E2E environment built on the existing Docker panel setup. It boots a Pterodactyl panel, installs the current Notur checkout into that panel, seeds a deterministic root admin, and runs the shell and Playwright suites against the same environment.

```bash
# Build the reusable E2E base image once.
# This is the slow dependency layer with OS packages, Node, Composer, Pterodactyl, and browser libraries.
bash docker/e2e/build-base.sh

# Run the full shell + browser E2E suite
bash docker/e2e/run-e2e.sh

# Run only the browser suite
bash docker/e2e/run-e2e.sh --suite browser

# Run the destructive Notur install/uninstall lifecycle suite
# This checks the panel before install, installs Notur, uninstalls Notur, and checks the panel again.
bash docker/e2e/run-e2e.sh --suite install-uninstall

# Keep containers running for inspection after the suite finishes
bash docker/e2e/run-e2e.sh --keep

# Force a fully fresh rebuild when debugging Docker image state
bash docker/e2e/run-e2e.sh --no-cache --rebuild-base
```

The reusable local base image is named `notur/e2e-base:php8.2-node22-panel1.12.2`. It contains the slow-moving E2E dependencies: PHP extensions, system packages, Node.js, Bun, Composer, the Pterodactyl panel tarball, and browser runtime libraries. Normal runs reuse it and only rebuild the lightweight repo-specific layers. If the base image is missing, `run-e2e.sh` fails with instructions instead of silently downloading all packages again.

GitHub Actions uses the published GHCR base image `ghcr.io/sak0a/notur-e2e-base:php8.2-node22-panel1.12.2` instead of rebuilding that slow layer on every PR. Publish or refresh it manually from the `Publish E2E Base Image` workflow after changing `docker/e2e/Dockerfile.base` or the PHP/Node/panel version tuple. The normal E2E workflow fails fast if the published base image is missing.

The default `all` suite runs the shell and browser E2E suites against a bootstrapped Notur panel. The `install-uninstall` suite is intentionally explicit because it destructively removes Notur from the panel while verifying that the underlying Pterodactyl installation remains usable.

Seeded admin credentials inside the E2E environment:

```text
Email: admin@example.com
Password: notur-admin-password
```

The browser specs live in `tests/E2E/browser/` and can also be invoked directly against an already running E2E panel with:

```bash
npm run test:e2e:browser
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

## Remote development push

Panel admins manage remote-push API keys at **Admin → Notur → Developer Push**. Developers use those keys with `npm run push` to install extensions on a running panel without going through the registry. See [`docs/remote-push.md`](docs/remote-push.md) for the full workflow.

## Extension Lifecycle

Extensions declare required extension versions in `extension.yaml` using Composer version constraints:

```yaml
dependencies:
  acme/core: "^1.2 || ^2.0"
```

Install and enable compatible dependencies before installing or enabling a dependent extension. Notur checks the proposed enabled set before installs, updates, and enables. Disabling or removing an extension checks its active dependents, so an unrelated broken extension does not block recovery. An active dependent prevents an update to an incompatible version or removal of its dependency; disable the dependent first. Updating a disabled extension leaves it disabled, even if the new version's dependencies are absent. At boot, extensions with missing, disabled, incompatible, or cyclic dependencies are reported and skipped, along with their dependents; unrelated extensions still start. The installed extension manifest supplies the version used for checks.

```bash
php artisan notur:add acme/server-analytics   # Install from registry
php artisan notur:enable acme/server-analytics     # Enable
php artisan notur:disable acme/server-analytics    # Disable
php artisan notur:remove acme/server-analytics     # Uninstall + rollback migrations
php artisan notur:list                             # Show all installed
php artisan notur:update                           # Check for updates
php artisan notur:status                           # System status dashboard
```

### Emergency extension recovery

Notur isolates extension manifest, dependency, entrypoint, registration and boot failures so an
unrelated extension and the panel can continue starting. Dependents of a failed
extension are skipped for that process. `php artisan notur:status` (or
`notur:status --json`) reports the extension ID, failure stage, exception and
message; the Laravel log includes the exception stack trace. An explicitly
configured entrypoint that cannot be loaded is reported as a failure too.

If extension boot prevents normal recovery, start a fresh process with safe mode:

```bash
NOTUR_SAFE_MODE=1 php artisan notur:status --json
NOTUR_SAFE_MODE=1 php artisan notur:disable acme/server-analytics
```

For a web panel, set `NOTUR_SAFE_MODE=true` in its environment and restart the
PHP workers; remove the override and restart after disabling or repairing the
extension. `notur.safe_mode` can also be configured in `config/notur.php`. A
process-level environment variable takes precedence even with cached Laravel
configuration. Safe mode skips all extension manifest discovery, autoloading and
booting; it does not change the saved enabled flags. It does not undo hooks,
services, routes or arbitrary PHP effects already registered in a running
process—restart workers for a clean recovery. Failed extensions are not marked
disabled automatically, so operators can inspect and repair them.
With an explicit `NOTUR_SAFE_MODE=1` environment override, disable and remove
can bypass active dependent protection to break a dependency cycle. Notur logs
the affected dependent. On the next normal boot, that dependent is reported as
failed and its dependents are skipped until the missing or disabled requirement
is repaired. A config-only safe mode setting does not bypass this guard.
The enable and disable commands read `notur/extensions.json` first, so they
still work when its database projection is stale or missing during recovery.
If the master `notur/extensions.json` is corrupt or unreadable, fix that file
before using `notur:disable`; its discovery error is reported as `@manifest`.

### Recoverable updates

`notur:add --force` and `notur:update` validate a package and stage its extension files and public assets before replacing an installed version. The previous trees are retained until migrations and registration succeed. An upgrade keeps a disabled extension disabled. A failed install restores the previous files and assets, and exits nonzero; `notur:update` continues other pending updates but exits nonzero if any update fails or throws. If a post-install event listener or cache-clear command throws, the extension may already be upgraded despite the nonzero update result; inspect its installed version before retrying.

Migrations are applied one at a time. If a later migration fails, Notur restores the previous files and assets, but completed database migrations and their `notur_migrations` records remain. Inspect and recover those database changes manually before retrying; file recovery does not reverse data changes.

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

Frontend-only quick start:

```bash
npx notur-create acme/red-button --preset frontend --slot server.header
cd red-button
npm install
npm run build
npx notur-validate
npx notur-pack
```

Panel-side scaffold is also available:

```bash
php artisan notur:new acme/server-analytics
```

### Preset Definitions

- `standard`: frontend + API routes (default)
- `backend`: API routes only
- `full`: frontend + API routes + admin UI + migrations + tests
- `minimal`: backend-only scaffolding with no routes or frontend

Frontend-only extensions are manifest-only by default. Add a PHP entrypoint only when the extension needs backend routes, migrations, commands, events, admin views, or custom boot logic.

Useful local SDK commands:

```bash
npx notur-sync      # Sync package/build metadata from extension.yaml
npx notur-validate  # Validate manifest, package drift, bundle paths, and slots
npx notur-doctor    # Diagnose local and remote push setup
npx notur-push      # Package and upload to a remote Notur panel
```

## License

MIT
