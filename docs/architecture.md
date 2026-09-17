# Notur Architecture

Notur has three cooperating parts:

- PHP runtime: install, boot, backend features, settings, permissions, assets.
- Browser bridge: runtime registry and React rendering inside Pterodactyl.
- SDK: extension-author API, types, scaffolding, packaging, and remote push.

## Runtime Flow

```text
Pterodactyl request
  -> Laravel boots NoturServiceProvider
  -> ExtensionManager loads enabled extension.yaml files
  -> ExtensionManager boots PHP entrypoint or ManifestOnlyExtension
  -> backend routes/settings/assets are registered
  -> notur::scripts injects window.__NOTUR__
  -> bridge.js initializes PluginRegistry
  -> extension bundles call createExtension()
  -> SlotRenderer renders React components into patched slots
```

## PHP Runtime

The PHP package is the trusted server-side host. It owns:

- extension install/update/remove lifecycle
- manifest parsing and validation
- dependency ordering
- backend route registration
- migrations
- settings and public config
- permissions
- asset URL exposure
- remote push upload endpoint

Frontend-only extensions do not need a PHP class. If no entrypoint is found, Notur creates a `ManifestOnlyExtension` from `extension.yaml` and still exposes its frontend assets.

## Bridge Runtime

The bridge runs in the browser after Pterodactyl loads. It owns:

- `window.__NOTUR__`
- `PluginRegistry`
- slot registration and sorting
- extension route registration
- event bus
- theme variables
- diagnostics
- React rendering into patched slot containers

Extension bundles should not manually mount React roots. They call `createExtension()` and let the bridge render components.

## SDK

The SDK gives extension authors:

- `createExtension()`
- TypeScript types and hover docs
- hooks for context, config, permissions, navigation, and events
- `notur-create`
- `notur-sync`
- `notur-validate`
- `notur-doctor`
- `notur-pack`
- `notur-push`

## Source Of Truth

`extension.yaml` is the source of truth. Package metadata and build config can be synchronized from it:

```bash
npx notur-sync
```

For installed runtime state, `notur/extensions.json` is authoritative for extension membership, version, and enabled status. `ExtensionStateStore` locks a stable `extensions.json.lock` file around each read/change/write and database projection, then replaces JSON through a temporary file in the same directory. Invalid JSON or a failed file write stops the operation without applying a database change.

The `notur_extensions` table is a projection used by admin screens and commands. When JSON exists, `ExtensionManager` reconciles that table on boot; a later mutation or an explicit `reconcileState()` call also retries reconciliation. If the database update fails after JSON replacement, the operation reports the error and the next reconciliation repairs the table. Reconciliation reloads name and manifest metadata from a readable on-disk `extension.yaml`, including for existing rows after a failed upgrade, while leaving remote-push tracking columns untouched. Unchanged rows are not written again. Boot continues from JSON during a database outage and logs the reconciliation error. If the table has not been migrated yet, projection waits until it exists. Rows absent from JSON are removed during reconciliation. A recovered row without a readable `extension.yaml` uses its extension ID as its display name until a later registration supplies metadata. An initial boot with no JSON manifest does not create a state directory or lock file.

## Trust Boundary

Notur extensions are trusted code. PHP extensions run inside the panel process, and frontend bundles run in the authenticated panel browser session. Use signatures, registry review, and source review for third-party extensions.
