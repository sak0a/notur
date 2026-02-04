# @notur/sdk

Notur Extension Developer SDK. This package provides the runtime helpers and React hooks needed to register Notur extensions, define UI slots and routes, and interact with the Notur bridge at runtime.

## Install

```bash
npm install @notur/sdk
```

```bash
bun add @notur/sdk
```

Peer dependencies:
- `react` `^16.14.0`
- `react-dom` `^16.14.0`

## Quick Start

```ts
import { createExtension } from '@notur/sdk';
import AnalyticsWidget from './components/AnalyticsWidget';
import AnalyticsPage from './pages/AnalyticsPage';

createExtension({
  config: { id: 'acme/analytics', name: 'Analytics', version: '1.0.0' },
  slots: [
    { slot: 'dashboard.widgets', component: AnalyticsWidget, order: 10 },
  ],
  routes: [
    { area: 'server', path: '/analytics', name: 'Analytics', component: AnalyticsPage },
  ],
});
```

Notes:
- The Notur bridge must be loaded before your bundle. The SDK reads the bridge from `window.__NOTUR__`.
- Extension IDs should be stable and unique (e.g., `vendor/name`).

## API

### `createExtension(definition)`

Registers the extension with Notur, wires routes and slots, and runs optional lifecycle hooks.

Definition fields:
- `config`: `{ id, name, version }`
- `slots`: UI slot registrations (`slot`, `component`, `order`, `when`, etc.)
- `routes`: Panel routes (`area`, `path`, `name`, `component`, `permission`, etc.)
- `cssIsolation`: `true` or `{ mode: 'root-class', className?: string }`
- `onInit`: Called after registration.
- `onDestroy`: Called when the extension is unloaded.

### Hooks

All hooks are exported from `@notur/sdk`.

- `useServerContext()`: Returns server context or `null` when not on a server page.
- `useUserContext()`: Returns user info or `null` while loading.
- `usePermission(permission)`: Checks for a specific extension permission.
- `useExtensionConfig(extensionId, options)`: Loads public settings for the extension.
  - `options`: `{ baseUrl?, initial?, pollInterval? }`
- `useNoturEvent(event, handler)`: Subscribe to inter-extension events.
- `useEmitEvent()`: Returns a function to emit inter-extension events.
- `useNavigate({ extensionId })`: Navigate inside your extension namespace.

## Build

```bash
bun run build
```

```bash
npm run build
```

Outputs are written to `dist/`.

## Templates

The SDK package ships a few templates in `templates/`:
- `extension.yaml.template` for extension manifests
- `ServiceProvider.php.template` for backend registration
- `ExampleComponent.tsx.template` for a starter UI component

## License

MIT
