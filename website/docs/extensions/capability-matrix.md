# Extension Capability Matrix

This matrix tracks current extension capabilities and near-term expansion priorities.

## Current Capabilities

| Capability | Current Support | Notes |
|---|---|---|
| Frontend slot injection | ✅ | Slot registry + render conditions (`when`, area/path/permission gates). |
| Frontend route injection | ✅ | `server`, `dashboard`, and `account` route areas. |
| Backend API/admin routes | ✅ | Route files loaded from extension manifest/backend contract. |
| Per-extension migrations | ✅ | Install/rollback integration via `MigrationManager`. |
| Admin views/settings | ✅ | Blade namespace + admin settings schema. |
| Event bus (global) | ✅ | `emitEvent`/`onEvent` on bridge registry. |
| Event bus (scoped) | ✅ | SDK `createScopedEventChannel()` namespaces events by extension id. |
| Signature verification | ✅ | Optional Ed25519 verification at install-time. |
| Archive integrity checks | ✅ | Required `checksums.json` + extra/missing file detection. |

## Expansion Targets

| Area | Target | Status |
|---|---|---|
| Slot contexts | More admin/auth slot anchors in core panel views | Planned |
| Route safety | Additional conventions around extension route permission namespaces | In progress (SDK warnings) |
| Event patterns | Standardized cross-extension event naming contract | In progress (scoped channels shipped) |
| Capability flags | Explicit compatibility flags for newly introduced runtime hooks | Planned |

## Scoped Event Pattern

Use namespaced channels for extension-to-extension communication:

```ts
import { createScopedEventChannel } from '@notur/sdk';

const channel = createScopedEventChannel('acme/analytics');

channel.emit('refresh', { source: 'dashboard' });
const off = channel.on('refresh', payload => {
    console.log('Received scoped event payload', payload);
});
```

This emits/listens on `ext:acme/analytics:refresh` to prevent collisions with other extensions.
