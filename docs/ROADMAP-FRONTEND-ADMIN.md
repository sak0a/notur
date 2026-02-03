# Frontend/SDK + Admin UI Roadmap (Temporary)

This is a temporary backlog focused on Frontend/SDK and Admin UI improvements.
Priorities are listed as P0 (next), P1 (soon), P2 (later).

## Done
- Admin settings UI schema + renderer: read a simple schema from `extension.yaml` and render a settings page in the admin UI.
- SDK hooks for permissions/config: `useExtensionConfig()` and `usePermission()` helpers for consistent UI gating.
- Slot metadata + preview: show where slots render and provide a dev preview page.
- Admin route discovery: list registered admin routes in the extension detail view.

## P1 (Soon)
- CSS isolation helper: optional class prefixing or scoped style injection for extension bundles.
- Extension activity log in admin UI: install/update/enable/disable timeline per extension.
- Extension health checks display: expose a `health()` method and render results in admin UI.

## P2 (Later)
- Frontend dev diagnostics page: shows loaded bundles, slot registrations, and runtime errors.
- Theme preview tooling for extensions: preview theme variables and extension-specific styles.
