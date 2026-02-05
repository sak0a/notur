# Roadmap

## Current Release

- Version: 1.2.0
- Status: Stable
- Lifecycle CLI, registry distribution, admin UI, and theme extensions are complete
- Ed25519 signature verification is available and optional via `notur.require_signatures`
- E2E and compatibility matrix testing are in place

## Near-Term Focus

- Stabilize frontend tests and CI (Jest config)
- Validate integration tests in CI (Orchestra Testbench + SQLite)
- Harden the installer on non-macOS Linux
- Validate patch checksums on subsequent installer runs
- Verify `notur:dev` symlink workflow with real extensions

## Versioning

- Notur follows semantic versioning.
- Extension manifests use `notur: "1.0"` as the format version.
- Compatibility target: Pterodactyl Panel v1.11+, PHP 8.2+, Node.js 22+.

See the changelog for release history.

---

## Frontend/SDK + Admin UI Roadmap (Temporary)

This is a temporary backlog focused on Frontend/SDK and Admin UI improvements.
Priorities are listed as P0 (next), P1 (soon), P2 (later).

## Done
- Admin settings UI schema + renderer: read a simple schema from `extension.yaml` and render a settings page in the admin UI.
- SDK hooks for permissions/config: `useExtensionConfig()` and `usePermission()` helpers for consistent UI gating.
- Slot metadata + preview: show where slots render and provide a dev preview page.
- Admin route discovery: list registered admin routes in the extension detail view.
- CSS isolation helper: optional class prefixing or scoped style injection for extension bundles.
- Extension activity log in admin UI: install/update/enable/disable timeline per extension.
- Extension health checks display: expose a `health()` method and render results in admin UI.

## P2 (Later)
- Frontend dev diagnostics page: shows loaded bundles, slot registrations, and runtime errors.
- Theme preview tooling for extensions: preview theme variables and extension-specific styles.