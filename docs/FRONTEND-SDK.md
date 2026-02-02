# Notur Frontend SDK Reference

This document covers the frontend SDK (`@notur/sdk`), bridge hooks, slot system, theme system, and webpack configuration for extension developers.

## Table of Contents

- [Architecture Overview](#architecture-overview)
- [SDK API: createExtension](#sdk-api-createextension)
- [SDK Types](#sdk-types)
- [Bridge Hooks](#bridge-hooks)
- [SDK Hooks](#sdk-hooks)
- [Slot System](#slot-system)
- [Theme System](#theme-system)
- [Webpack Configuration](#webpack-configuration)

---

## Architecture Overview

The Notur frontend has three layers:

1. **Bridge Runtime** (`bridge.js`) -- Loaded in the panel's HTML. Creates `window.__NOTUR__` and provides the PluginRegistry, SlotRenderer, hooks, and theme engine.
2. **Extension SDK** (`@notur/sdk`) -- NPM package used by extension developers. Provides `createExtension()`, type definitions, and convenience hooks.
3. **Extension Bundles** -- Each extension ships a pre-built JS bundle that is loaded after `bridge.js` and registers itself via `createExtension()`.

React and ReactDOM are **not bundled** with extensions. They are externalized and use the panel's existing instances via `window.React` and `window.ReactDOM`.

---

## SDK API: createExtension

The primary entry point for registering an extension.

```typescript
import { createExtension } from '@notur/sdk';

createExtension(definition: ExtensionDefinition): void;
```

### Parameters

`definition: ExtensionDefinition` -- The extension registration object:

```typescript
interface ExtensionDefinition {
    config: ExtensionConfig;
    slots?: SlotConfig[];
    routes?: RouteConfig[];
    onInit?: () => void;
    onDestroy?: () => void;
}
```

### Full Example

```tsx
import * as React from 'react';
import { createExtension } from '@notur/sdk';

const DashboardWidget: React.FC<{ extensionId: string }> = ({ extensionId }) => {
    return <div>Hello from my extension!</div>;
};

const SettingsPage: React.FC = () => {
    return <div>Settings page content</div>;
};

createExtension({
    config: {
        id: 'acme/my-extension',
        name: 'My Extension',
        version: '1.0.0',
    },
    slots: [
        {
            slot: 'dashboard.widgets',
            component: DashboardWidget,
            order: 10,
        },
        {
            slot: 'server.subnav',
            component: () => null,  // Nav items use label/icon, not rendered component
            label: 'Settings',
            icon: 'cog',
            permission: 'my-ext.view',
        },
    ],
    routes: [
        {
            area: 'server',
            path: '/my-settings',
            name: 'Settings',
            component: SettingsPage,
            icon: 'cog',
            permission: 'my-ext.view',
        },
    ],
    onInit: () => {
        console.log('Extension initialized');
    },
    onDestroy: () => {
        console.log('Extension destroyed');
    },
});
```

### What happens on registration

1. Each slot config is registered with the PluginRegistry via `registerSlot()`.
2. Each route config is registered with the PluginRegistry via `registerRoute()`.
3. The extension metadata is registered via `registerExtension()`.
4. The `onInit` callback is invoked (if provided).
5. A log message `[Notur] Extension registered: {id} v{version}` is printed to the console.

---

## SDK Types

### `ExtensionConfig`

```typescript
interface ExtensionConfig {
    id: string;        // Extension ID matching extension.yaml (e.g., "acme/analytics")
    name: string;      // Human-readable name
    version: string;   // Semantic version string
}
```

### `SlotConfig`

```typescript
interface SlotConfig {
    slot: string;                          // Slot ID (see Slot System below)
    component: React.ComponentType<any>;   // React component to render
    order?: number;                        // Render order (lower = first, default: 0)
    label?: string;                        // Display label (for nav slots)
    icon?: string;                         // Icon name (for nav slots)
    permission?: string;                   // Required permission
}
```

### `RouteConfig`

```typescript
interface RouteConfig {
    area: 'server' | 'dashboard' | 'account';  // Which router area
    path: string;                                // Route path (e.g., "/analytics")
    name: string;                                // Display name for nav
    component: React.ComponentType<any>;         // Page component
    icon?: string;                               // Icon for nav item
    permission?: string;                         // Required permission
}
```

### `NoturApi`

The global API exposed on `window.__NOTUR__`:

```typescript
interface NoturApi {
    version: string;
    registry: {
        registerSlot: (registration: any) => void;
        registerRoute: (area: string, route: any) => void;
        registerExtension: (ext: any) => void;
        getSlot: (slotId: string) => any[];
        getRoutes: (area: string) => any[];
        on: (event: string, callback: () => void) => () => void;
    };
    hooks: {
        useSlot: (slotId: string) => any[];
        useExtensionApi: (options: { extensionId: string }) => any;
        useExtensionState: <T>(extensionId: string, initialState: T) => [T, (partial: Partial<T>) => void];
        useNoturTheme: () => any;
    };
    SLOT_IDS: Record<string, string>;
}
```

Access it via:

```typescript
import { getNoturApi } from '@notur/sdk';

const api = getNoturApi();  // throws if bridge.js hasn't loaded yet
```

---

## Bridge Hooks

These hooks are provided by the bridge runtime and are available on `window.__NOTUR__.hooks`. They can also be imported in extension code by accessing the global.

### `useSlot(slotId: SlotId): SlotRegistration[]`

Returns all component registrations for a given slot. Subscribes to change events so the consuming component re-renders when extensions register or unregister slot components.

```tsx
const { useSlot } = window.__NOTUR__.hooks;

const MyComponent: React.FC = () => {
    const widgets = useSlot('dashboard.widgets');

    return (
        <div>
            {widgets.map((reg, i) => (
                <reg.component key={i} extensionId={reg.extensionId} />
            ))}
        </div>
    );
};
```

**Behavior:**
- Returns an empty array if the bridge hasn't initialized yet.
- Polls every 50ms until the bridge is ready (handles late initialization).
- Listens to both `slot:{slotId}` and `extension:registered` events for re-renders.

### `useExtensionApi<T>(options: UseExtensionApiOptions)`

HTTP client scoped to an extension's namespaced API endpoints.

```tsx
const { useExtensionApi } = window.__NOTUR__.hooks;

const MyComponent: React.FC = () => {
    const api = useExtensionApi({ extensionId: 'acme/analytics' });

    React.useEffect(() => {
        api.get('/stats').then(data => console.log(data));
    }, []);

    return (
        <div>
            {api.loading && <p>Loading...</p>}
            {api.error && <p>Error: {api.error}</p>}
            {api.data && <pre>{JSON.stringify(api.data, null, 2)}</pre>}
        </div>
    );
};
```

**Options:**

```typescript
interface UseExtensionApiOptions {
    extensionId: string;   // Your extension ID
    baseUrl?: string;      // Override the default base URL
}
```

**Default base URL:** `/api/client/notur/{extensionId}`

**Returned object:**

| Property/Method | Type | Description |
|---|---|---|
| `data` | `T \| null` | Latest response data |
| `loading` | `boolean` | Whether a request is in flight |
| `error` | `string \| null` | Error message from last failed request |
| `get(path)` | `(path: string) => Promise<T>` | GET request |
| `post(path, body?)` | `(path: string, body?: any) => Promise<T>` | POST request |
| `put(path, body?)` | `(path: string, body?: any) => Promise<T>` | PUT request |
| `patch(path, body?)` | `(path: string, body?: any) => Promise<T>` | PATCH request |
| `delete(path)` | `(path: string) => Promise<T>` | DELETE request |
| `request(path, options?)` | `(path: string, options?: RequestInit) => Promise<T>` | Custom request |

**Features:**
- Automatically includes CSRF tokens for mutation methods (POST, PUT, PATCH, DELETE).
- Uses `credentials: 'same-origin'` for cookie-based Pterodactyl auth.
- Guards against state updates on unmounted components.
- Handles 204 No Content responses (e.g., successful DELETE).
- Extracts structured error messages from Laravel error responses.

### `useExtensionState<T>(extensionId: string, initialState: T): [T, setState, resetState]`

Shared state scoped to an extension. All components from the same extension share one store instance, so state changes are reflected everywhere.

```tsx
const { useExtensionState } = window.__NOTUR__.hooks;

const Counter: React.FC = () => {
    const [state, setState, resetState] = useExtensionState('acme/analytics', {
        count: 0,
        lastUpdated: null as string | null,
    });

    return (
        <div>
            <p>Count: {state.count}</p>
            <button onClick={() => setState({ count: state.count + 1, lastUpdated: new Date().toISOString() })}>
                Increment
            </button>
            <button onClick={resetState}>Reset</button>
        </div>
    );
};
```

**Return value:**

| Index | Type | Description |
|---|---|---|
| `[0]` | `T` | Current state |
| `[1]` | `(partial: Partial<T>) => void` | Merge partial state (like React's `setState`) |
| `[2]` | `() => void` | Reset state back to `initialState` |

**Features:**
- State store is automatically created on first use and cleaned up when the last subscriber unmounts.
- Uses a pub/sub pattern internally -- changes propagate to all subscribers immediately.
- The store is keyed by extension ID, so different extensions never share state.

### `useNoturTheme(): Record<string, string>`

Access the current Notur theme CSS custom properties.

```tsx
const { useNoturTheme } = window.__NOTUR__.hooks;

const ThemedBox: React.FC = () => {
    const theme = useNoturTheme();

    return (
        <div style={{
            background: theme['--notur-bg-secondary'],
            color: theme['--notur-text-primary'],
            borderRadius: theme['--notur-radius-md'],
        }}>
            Themed content
        </div>
    );
};
```

---

## SDK Hooks

These hooks are exported from `@notur/sdk` and provide access to Pterodactyl panel context.

### `useServerContext(): ServerContext | null`

Access the current server context. Only available on server-scoped pages.

```tsx
import { useServerContext } from '@notur/sdk';

const ServerInfo: React.FC = () => {
    const server = useServerContext();

    if (!server) return <p>Not on a server page</p>;

    return (
        <div>
            <p>Server: {server.name}</p>
            <p>UUID: {server.uuid}</p>
            <p>Status: {server.status}</p>
        </div>
    );
};
```

**`ServerContext` shape:**

```typescript
interface ServerContext {
    uuid: string;
    name: string;
    node: string;
    isOwner: boolean;
    status: string | null;
    permissions: string[];
}
```

### `useUserContext(): UserContext | null`

Access the current user information.

```tsx
import { useUserContext } from '@notur/sdk';

const UserGreeting: React.FC = () => {
    const user = useUserContext();

    if (!user) return null;
    return <p>Hello, {user.username}!</p>;
};
```

**`UserContext` shape:**

```typescript
interface UserContext {
    uuid: string;
    username: string;
    email: string;
    isAdmin: boolean;
}
```

The hook first tries to read from `window.PterodactylUser`. If unavailable, it falls back to fetching `/api/client/account`.

### `usePermission(permission: string): boolean`

Check if the current user has a specific permission in the current server context.

```tsx
import { usePermission } from '@notur/sdk';

const AdminPanel: React.FC = () => {
    const canAdmin = usePermission('analytics.admin');

    if (!canAdmin) return null;
    return <div>Admin-only content</div>;
};
```

**Permission resolution:**
- Returns `true` if the user is the server owner (`isOwner`).
- Returns `true` if the user's permissions include `*` (wildcard).
- Returns `true` if the user's permissions include the exact permission string.
- Returns `false` if not on a server page (no server context).

---

## Slot System

Slots are predefined injection points in the Pterodactyl panel where extensions can render components.

### Available Slot IDs

| Constant | Slot ID | Location | Type | Description |
|---|---|---|---|---|
| `NAVBAR` | `navbar` | Top navigation bar | `portal` | Render components in the navbar |
| `SERVER_SUBNAV` | `server.subnav` | Server sub-navigation | `nav` | Add items to server sub-nav |
| `SERVER_PAGE` | `server.page` | Server area | `route` | Full page in server context |
| `SERVER_TERMINAL_BUTTONS` | `server.terminal.buttons` | Terminal power buttons | `portal` | Add buttons near terminal controls |
| `SERVER_FILES_ACTIONS` | `server.files.actions` | File manager toolbar | `portal` | Add actions to file manager |
| `DASHBOARD_WIDGETS` | `dashboard.widgets` | Dashboard below server list | `portal` | Add widgets to the dashboard |
| `DASHBOARD_PAGE` | `dashboard.page` | Dashboard area | `route` | Full page in dashboard context |
| `ACCOUNT_PAGE` | `account.page` | Account area | `route` | Full page in account context |
| `ACCOUNT_SUBNAV` | `account.subnav` | Account sub-navigation | `nav` | Add items to account sub-nav |

### Slot Types

- **`portal`** -- Component is rendered into a container `<div>` via React portals. Use for widgets, buttons, and inline content.
- **`nav`** -- Component metadata (label, icon) is used to render a navigation item. The actual page component is registered as a route.
- **`route`** -- Component is rendered as a full page when the user navigates to the registered path.

### Using Slot IDs in Code

The slot IDs are available as constants:

```typescript
// From bridge runtime
const { SLOT_IDS } = window.__NOTUR__;

// Constants:
SLOT_IDS.NAVBAR                    // 'navbar'
SLOT_IDS.SERVER_SUBNAV             // 'server.subnav'
SLOT_IDS.SERVER_PAGE               // 'server.page'
SLOT_IDS.SERVER_TERMINAL_BUTTONS   // 'server.terminal.buttons'
SLOT_IDS.SERVER_FILES_ACTIONS      // 'server.files.actions'
SLOT_IDS.DASHBOARD_WIDGETS         // 'dashboard.widgets'
SLOT_IDS.DASHBOARD_PAGE            // 'dashboard.page'
SLOT_IDS.ACCOUNT_PAGE              // 'account.page'
SLOT_IDS.ACCOUNT_SUBNAV            // 'account.subnav'
```

---

## Theme System

Notur provides a CSS custom property-based theme system that derives its defaults from the Pterodactyl panel's styles.

### CSS Custom Properties

All Notur theme variables are prefixed with `--notur-`. Extensions should use these variables for consistent styling.

#### Colors

| Variable | Default | Purpose |
|---|---|---|
| `--notur-primary` | `#0967d2` | Primary accent color |
| `--notur-primary-light` | `#47a3f3` | Light primary |
| `--notur-primary-dark` | `#03449e` | Dark primary |
| `--notur-secondary` | `#616e7c` | Secondary color |
| `--notur-success` | `#27ab83` | Success / positive |
| `--notur-danger` | `#e12d39` | Danger / destructive |
| `--notur-warning` | `#f7c948` | Warning |
| `--notur-info` | `#2bb0ed` | Informational |

#### Backgrounds

| Variable | Default | Purpose |
|---|---|---|
| `--notur-bg-primary` | `#1f2933` | Primary background |
| `--notur-bg-secondary` | `#323f4b` | Secondary background (cards, inputs) |
| `--notur-bg-tertiary` | `#3e4c59` | Tertiary background (hover states) |

#### Text

| Variable | Default | Purpose |
|---|---|---|
| `--notur-text-primary` | `#f5f7fa` | Primary text |
| `--notur-text-secondary` | `#cbd2d9` | Secondary text |
| `--notur-text-muted` | `#9aa5b1` | Muted / placeholder text |

#### Borders and Radius

| Variable | Default | Purpose |
|---|---|---|
| `--notur-border` | `#3e4c59` | Border color |
| `--notur-radius-sm` | `4px` | Small border radius |
| `--notur-radius-md` | `8px` | Medium border radius |
| `--notur-radius-lg` | `12px` | Large border radius |

#### Typography

| Variable | Default | Purpose |
|---|---|---|
| `--notur-font-sans` | `-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif` | Sans-serif font stack |
| `--notur-font-mono` | `"Fira Code", "JetBrains Mono", monospace` | Monospace font stack |

### Using Theme Variables

```css
.my-widget {
    background: var(--notur-bg-secondary);
    color: var(--notur-text-primary);
    border: 1px solid var(--notur-border);
    border-radius: var(--notur-radius-md);
    font-family: var(--notur-font-sans);
    padding: 1rem;
}

.my-widget .title {
    color: var(--notur-primary);
    font-weight: 600;
}

.my-widget .danger-text {
    color: var(--notur-danger);
}
```

Or inline with the `useNoturTheme` hook (see Bridge Hooks above).

### Theme Extraction

On initialization, the bridge runtime attempts to extract theme values from the live panel DOM using three strategies:

1. **CSS custom properties** -- Reads `--ptero-*` properties from `:root` and maps them to `--notur-*` equivalents.
2. **Computed styles** -- Probes known panel elements (e.g., `#app` background color, input borders) and derives Notur variables.
3. **Stylesheet scanning** -- Scans all loaded stylesheets for `--ptero-*` declarations in `:root` rules.

If no values can be extracted, the defaults listed above are applied.

### Theme Extensions

Theme extensions can override CSS custom properties by shipping a CSS file that redefines `:root` properties:

```css
/* my-theme.css */
:root {
    --notur-primary: #6366f1;
    --notur-bg-primary: #0f172a;
    --notur-bg-secondary: #1e293b;
    --notur-text-primary: #e2e8f0;
}
```

---

## Webpack Configuration

The SDK ships a base webpack configuration for building extension frontend bundles.

### Using the Base Config

```js
// webpack.config.js
const base = require('@notur/sdk/webpack.extension.config');

module.exports = {
    ...base,
    entry: './resources/frontend/src/index.tsx',
    output: {
        ...base.output,
        filename: 'extension.js',
        path: __dirname + '/resources/frontend/dist',
    },
};
```

### What the Base Config Does

- Sets React and ReactDOM as externals (uses `window.React` and `window.ReactDOM`).
- Configures TypeScript/TSX compilation via `ts-loader` or `babel-loader`.
- Outputs a single bundle file suitable for browser loading.
- Sets `libraryTarget: 'umd'` for compatibility.

### Building

```bash
# Production build
npx webpack --mode production

# Development build (with source maps)
npx webpack --mode development
```

### Important Notes

- **Do not bundle React.** The panel already includes React. Your extension's webpack config must externalize it:
  ```js
  externals: {
      'react': 'React',
      'react-dom': 'ReactDOM',
  }
  ```
- **Output goes to `resources/frontend/dist/`** by convention.
- The output filename should match the `frontend.bundle` field in your `extension.yaml`.
- Bundles are loaded in the browser after `bridge.js`, so `window.__NOTUR__` is guaranteed to exist.

### SDK Exports

The `@notur/sdk` package exports:

```typescript
// Functions
export { createExtension } from './createExtension';
export { getNoturApi } from './types';

// Types
export type { ExtensionConfig, SlotConfig, RouteConfig, ExtensionDefinition, NoturApi } from './types';

// Hooks
export { useServerContext } from './hooks/useServerContext';
export { useUserContext } from './hooks/useUserContext';
export { usePermission } from './hooks/usePermission';
```
