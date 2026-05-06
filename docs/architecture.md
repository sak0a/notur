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

## Trust Boundary

Notur extensions are trusted code. PHP extensions run inside the panel process, and frontend bundles run in the authenticated panel browser session. Use signatures, registry review, and source review for third-party extensions.
