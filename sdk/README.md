# @notur/sdk

Notur Extension Developer SDK for [Pterodactyl Panel v1](https://github.com/pterodactyl/panel). Provides the runtime API, React hooks, CLI tools, webpack config, and starter templates for building Notur extensions.

## Install

```bash
npm install @notur/sdk
# or: yarn add @notur/sdk
# or: pnpm add @notur/sdk
# or: bun add @notur/sdk
```

Peer dependencies: `react ^16.14.0`, `react-dom ^16.14.0`

## Quick Start

```ts
import { createExtension } from '@notur/sdk';
import AnalyticsWidget from './components/AnalyticsWidget';
import AnalyticsPage from './pages/AnalyticsPage';

createExtension({
  id: 'acme/analytics',
  slots: [
    { slot: 'dashboard.widgets', component: AnalyticsWidget, order: 10 },
  ],
  routes: [
    { area: 'server', path: '/analytics', name: 'Analytics', component: AnalyticsPage },
  ],
});
```

Name and version are auto-resolved from `extension.yaml`. The full `config: { id, name, version }` syntax is still supported for backward compatibility.

---

## API

### `createExtension(definition)`

Registers the extension with the Notur bridge, wiring slots, routes, and lifecycle hooks.

Two calling conventions:

```ts
// Simplified (recommended) — name/version auto-resolved from extension.yaml:
createExtension({ id: 'acme/analytics', slots: [...], routes: [...] });

// Full (backward compatible):
createExtension({ config: { id: 'acme/analytics' }, slots: [...], routes: [...] });
```

**Definition fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | `string` | Extension ID (simplified form) |
| `config` | `{ id, name?, version? }` | Extension identity (full form) |
| `slots` | `SlotConfig[]` | UI slot registrations |
| `routes` | `RouteConfig[]` | Panel route registrations |
| `cssIsolation` | `boolean \| CssIsolationConfig` | CSS isolation (`true` for defaults, or `{ mode: 'root-class', className? }`) |
| `onInit` | `() => void` | Called after registration |
| `onDestroy` | `() => void` | Called when the extension is unloaded |

### Hooks

All hooks are exported from `@notur/sdk`.

| Hook | Description |
|------|-------------|
| `useServerContext()` | Returns server context or `null` when not on a server page |
| `useUserContext()` | Returns user info or `null` while loading |
| `usePermission(permission)` | Checks a specific extension permission |
| `useExtensionConfig(extensionId, options?)` | Loads public settings (`options: { baseUrl?, initial?, pollInterval? }`) |
| `useNoturEvent(event, handler)` | Subscribe to inter-extension events |
| `useEmitEvent()` | Returns a function to emit inter-extension events |
| `useNavigate({ extensionId })` | Navigate inside your extension namespace |

### TypeScript Types

```ts
import type {
  ExtensionConfig,
  ExtensionDefinition,
  SimpleExtensionDefinition,
  SlotConfig,
  RouteConfig,
  NoturApi,
} from '@notur/sdk';
```

---

## CLI Tools

The SDK ships two CLI tools, available via `npx`, `yarn dlx`, `pnpm dlx`, or `bunx`.

### `notur-pack` — Package extensions

Creates a `.notur` archive (tar.gz) from your extension directory, ready to upload to the Pterodactyl admin panel.

```bash
# Pack current directory
npx notur-pack

# Pack a specific path
npx notur-pack /path/to/my-extension

# Custom output filename
npx notur-pack --output my-extension.notur
npx notur-pack -o my-extension.notur
```

The archive includes all extension files (excluding `node_modules`, `.git`, `vendor`, `.idea`, `.vscode`, `.DS_Store`) plus a generated `checksums.json` with SHA-256 hashes for integrity verification.

Output:
- `vendor-name-1.0.0.notur` — the extension archive
- `vendor-name-1.0.0.notur.sha256` — SHA-256 checksum file

Upload the `.notur` file at `/admin/notur/extensions` in your panel.

#### Signing archives

Sign your archive with Ed25519 for panels that have `require_signatures` enabled. Compatible with the PHP `notur:keygen` / `notur:export --sign` commands.

```bash
# Sign using environment variable
NOTUR_SECRET_KEY=<your_secret_key> npx notur-pack --sign

# Sign using flag
npx notur-pack --sign --secret-key <your_secret_key>
```

This additionally produces:
- `vendor-name-1.0.0.notur.sig` — Ed25519 signature (hex-encoded)

The `.sig` format is compatible with PHP's `SignatureVerifier::verify()`.

### `notur-keygen` — Generate signing keypair

Generates a new Ed25519 keypair for extension signing.

```bash
npx notur-keygen
```

Output:
- **Public Key** (64 hex chars) — add to your panel config as `NOTUR_PUBLIC_KEY`
- **Secret Key** (128 hex chars) — keep private, use with `notur-pack --sign`

The output format matches the PHP `php artisan notur:keygen` command.

---

## Webpack Config

The SDK includes a base webpack configuration that extension developers can import and extend. This handles TypeScript compilation, CSS loading, React externalization, and UMD output format.

```js
// webpack.config.js
const base = require('@notur/sdk/webpack.extension.config');

module.exports = {
  ...base,
  entry: './resources/frontend/src/index.tsx',
  output: {
    ...base.output,
    filename: 'extension.js',
    path: require('path').resolve(__dirname, 'resources/frontend/dist'),
  },
};
```

**What the base config provides:**

| Setting | Value |
|---------|-------|
| Entry | `./src/index.ts` |
| Output | `dist/bundle.js` (UMD) |
| Resolve | `.ts`, `.tsx`, `.js`, `.jsx` |
| Loaders | `ts-loader` for TypeScript, `style-loader`/`css-loader` for CSS |
| Externals | `react` → `React`, `react-dom` → `ReactDOM`, `@notur/sdk` → `__NOTUR__` |

Override `entry` and `output` to match your extension's directory structure.

---

## Templates

The SDK ships starter templates in `templates/`, used by the PHP `notur:new` scaffolding command. They can also serve as reference when creating extensions manually.

### `extension.yaml.template`

Minimal extension manifest with placeholders:

```yaml
notur: "1.0"
id: "{{vendor}}/{{name}}"
name: "{{displayName}}"
version: "1.0.0"
description: "{{description}}"
authors:
  - name: "{{authorName}}"
license: "MIT"

requires:
  notur: "^1.0"
  pterodactyl: "^1.11"
  php: "^8.2"
```

### `ServiceProvider.php.template`

PHP entrypoint using the `NoturExtension` base class. Metadata (`getId()`, `getName()`, `getVersion()`, `getBasePath()`) is auto-resolved from `extension.yaml` — no boilerplate needed:

```php
namespace {{namespace}};

use Notur\Support\NoturExtension;
use Notur\Contracts\HasRoutes;

class {{className}} extends NoturExtension implements HasRoutes
{
    public function register(): void { }
    public function boot(): void { }

    public function getRouteFiles(): array
    {
        return ['api-client' => 'src/routes/api-client.php'];
    }
}
```

### `ExampleComponent.tsx.template`

Starter React component with `createExtension()` registration using the simplified syntax:

```tsx
import * as React from 'react';
import { createExtension } from '@notur/sdk';

const ExampleWidget: React.FC<{ extensionId: string }> = ({ extensionId }) => {
    return (
        <div style={{ padding: '1rem', background: 'var(--notur-bg-secondary)' }}>
            <h3>{{displayName}}</h3>
            <p>Hello from {{vendor}}/{{name}}!</p>
        </div>
    );
};

createExtension({
    id: '{{vendor}}/{{name}}',
    slots: [{ slot: 'dashboard.widgets', component: ExampleWidget, order: 100 }],
});
```

---

## Build

```bash
npm run build    # Compiles TypeScript to dist/
npm run dev      # Watch mode
```

## License

MIT
