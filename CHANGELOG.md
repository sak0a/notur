# Changelog

All notable changes to the Notur Extension Library will be documented in this file.

## [1.3.1] - 2026-04-03

### Fixed

- **Security:** Resolve all npm audit vulnerabilities (handlebars, picomatch, yaml, brace-expansion, serialize-javascript)
- **Security:** Update `league/commonmark` to 2.8.2 (CVE-2026-33347, CVE-2026-30838)
- **CI:** Fix Jest `Object.values` error by adding ES2018 lib target to jest config
- **CI:** Regenerate `routes.ts` reverse patches from actual panel source for v1.11 and v1.12
- **CI:** Upgrade `jest-environment-jsdom` to v30, update tests to use `history.pushState` for location mocking

## [1.3.0] - 2026-04-02

### Added

- **Custom exception hierarchy** — `NoturException` base with `ExtensionNotFoundException`, `ManifestException`, `DependencyResolutionException`, `ExtensionBootException` for typed error handling across the framework.
- **`VerifyServerAccess` middleware** — Shared middleware for extension routes to verify authenticated user has server access (owner or subuser). Handles both full and short UUID. Usage: `->middleware('notur.server-access')`.
- **Lifecycle logging** — `ExtensionManager` now logs manifest failures, boot summaries, missing entrypoints, and enable/disable state changes via Laravel's `Log` facade.
- **`recordDiagnosticError()` utility** — Bounded error recording (max 100 entries) for frontend diagnostics. Exposed on `window.__NOTUR__`.
- **Slot registration validation** — `PluginRegistry.registerSlot()` warns on unknown slot IDs and skips duplicate registrations from the same extension.
- **Bridge cleanup/teardown** — `window.__NOTUR__.cleanup()` disconnects all pending MutationObservers and unmounts all slot renderers.
- **Bridge version compatibility check** — `createExtension()` warns if SDK major version doesn't match bridge major version.
- **`SlotComponentProps` type** — Exported from `@notur/sdk` for typing extension slot components.
- **`EntrypointResolverTest`** — Unit tests for the new `EntrypointResolver` class.
- **`diagnostics.test.ts`** — Frontend tests for `recordDiagnosticError` bounding behavior.
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
- **Bridge tsconfig target** — Aligned to ES2018 (was ES2019) for consistency with SDK.
- **Bridge version** — Synced to 1.2.9 (was 1.2.8).
- **`build:sdk` script** — Uses `--project` flag instead of `cd` for better CI compatibility.

### Fixed

- **hello-world example** — Removed deprecated `HasFrontendSlots` interface, now extends `NoturExtension` base class, added `package.json`.
- **cs2-modframework extension** — Fixed version mismatch (PHP returned 1.0.0, manifest had 1.0.4). Now extends `NoturExtension` and uses `notur.server-access` middleware.
- **Removed empty `examples/brutalist-glass/`** directory.

### Deprecated

- **`HasFrontendSlots` interface** — Define slots in frontend code via `createExtension({ slots: [...] })` instead.

## [1.2.9] - 2026-03-28

- Published cs2-modframework v1.0.4 artifact
- Server route compatibility fallback for cs2-modframework
- Prefix server extension routes with `/server/:id`

## [1.2.8] - 2026-03-27

- Restored server route path compatibility for cs2-modframework
