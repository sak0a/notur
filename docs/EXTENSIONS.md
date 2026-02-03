# Creating Notur Extensions

## Extension Structure

```
acme-server-analytics/
├── extension.yaml              # Manifest (required)
├── composer.json               # Optional — for Composer-based deps
├── package.json                # Optional — for frontend build
├── src/
│   ├── ServerAnalyticsExtension.php   # Entrypoint (implements ExtensionInterface)
│   ├── routes/
│   │   └── api-client.php     # API routes
│   ├── Http/
│   │   └── Controllers/
│   │       └── AnalyticsController.php
│   └── Listeners/
│       └── InitializeAnalytics.php
├── database/
│   └── migrations/
│       └── 2024_01_01_000001_create_analytics_table.php
    └── resources/
        └── frontend/
        ├── src/
        │   └── index.tsx       # Frontend entry — calls createExtension()
        └── dist/
            └── extension.js    # Pre-built bundle (shipped with extension)
```

## Scaffold with CLI (Optional)

You can scaffold a new extension with the Notur CLI:

```bash
php artisan notur:new acme/server-analytics
```

### Preset Definitions

- `standard`: frontend + API routes (default)
- `backend`: API routes only
- `full`: frontend + API routes + admin UI + migrations + tests
- `minimal`: backend-only scaffolding with no routes or frontend

### Examples
```bash
php artisan notur:new acme/server-analytics --preset=backend
php artisan notur:new acme/server-analytics --preset=full
```

Feature toggles:
```bash
php artisan notur:new acme/server-analytics --with-api-routes
php artisan notur:new acme/server-analytics --with-admin-routes
php artisan notur:new acme/server-analytics --with-admin --with-migrations --with-tests
```
Admin UI scaffolding is separate from admin routes; add `--with-admin-routes` to expose admin endpoints.

## Step 1: Create extension.yaml

```yaml
notur: "1.0"
id: "acme/server-analytics"
name: "Server Analytics"
version: "1.0.0"
description: "Real-time server analytics"
authors:
  - name: "Your Name"
license: "MIT"

requires:
  notur: "^1.0"
  pterodactyl: "^1.11"
  php: "^8.2"

entrypoint: "Acme\\ServerAnalytics\\ServerAnalyticsExtension"

autoload:
  psr-4:
    "Acme\\ServerAnalytics\\": "src/"

backend:
  routes:
    api-client: "src/routes/api-client.php"
  migrations: "database/migrations"
  permissions:
    - "analytics.view"
    - "analytics.export"

frontend:
  bundle: "resources/frontend/dist/extension.js"
  slots:
    server.subnav:
      label: "Analytics"
      icon: "chart-bar"
      permission: "analytics.view"
    dashboard.widgets:
      component: "AnalyticsWidget"
      order: 10
```

## Step 2: Implement the PHP Entrypoint

```php
<?php

namespace Acme\ServerAnalytics;

use Notur\Contracts\ExtensionInterface;
use Notur\Contracts\HasRoutes;
use Notur\Contracts\HasMigrations;
use Notur\Contracts\HasFrontendSlots;

class ServerAnalyticsExtension implements ExtensionInterface, HasRoutes, HasMigrations, HasFrontendSlots
{
    public function getId(): string { return 'acme/server-analytics'; }
    public function getName(): string { return 'Server Analytics'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getBasePath(): string { return __DIR__ . '/..'; }

    public function register(): void
    {
        // Bind services, configure settings
    }

    public function boot(): void
    {
        // Post-registration logic
    }

    public function getRouteFiles(): array
    {
        return ['api-client' => 'src/routes/api-client.php'];
    }

    public function getMigrationsPath(): string
    {
        return $this->getBasePath() . '/database/migrations';
    }

    public function getFrontendSlots(): array
    {
        return [
            'server.subnav' => ['label' => 'Analytics', 'icon' => 'chart-bar'],
            'dashboard.widgets' => ['component' => 'AnalyticsWidget', 'order' => 10],
        ];
    }
}
```

### Available Contracts

Implement these interfaces to opt into capabilities:

| Interface | Purpose |
|---|---|
| `ExtensionInterface` | Required — base contract |
| `HasRoutes` | Register route files |
| `HasMigrations` | Database migrations |
| `HasCommands` | Artisan commands |
| `HasMiddleware` | HTTP middleware |
| `HasEventListeners` | Event listeners |
| `HasBladeViews` | Blade view namespace |
| `HasFrontendSlots` | Frontend slot metadata |

## Step 3: Create API Routes

```php
// src/routes/api-client.php
use Illuminate\Support\Facades\Route;
use Acme\ServerAnalytics\Http\Controllers\AnalyticsController;

// These routes are automatically prefixed with:
// /api/client/notur/acme/server-analytics/

Route::get('/stats', [AnalyticsController::class, 'stats']);
Route::get('/export', [AnalyticsController::class, 'export']);
```

Route groups and their prefixes:

| Group | Prefix | Default Middleware |
|---|---|---|
| `api-client` | `/api/client/notur/{extension-id}/` | `client-api` |
| `admin` | `/admin/notur/{extension-id}/` | `web`, `admin` |
| `web` | `/notur/{extension-id}/` | `web` |

## Step 4: Build the Frontend

### Install the SDK

```bash
bun install @notur/sdk
```

### Create the Frontend Entry

```tsx
// resources/frontend/src/index.tsx
import * as React from 'react';
import { createExtension } from '@notur/sdk';

// Access the Notur bridge hooks
const { useExtensionApi, useExtensionState } = window.__NOTUR__.hooks;

const AnalyticsWidget: React.FC<{ extensionId: string }> = ({ extensionId }) => {
    const api = useExtensionApi({ extensionId });
    const [data, setData] = React.useState(null);

    React.useEffect(() => {
        api.get('/stats').then(setData);
    }, []);

    return (
        <div style={{ padding: '1rem', background: 'var(--notur-bg-secondary)', borderRadius: 'var(--notur-radius-md)' }}>
            <h3 style={{ color: 'var(--notur-text-primary)' }}>Server Analytics</h3>
            {data ? <pre>{JSON.stringify(data, null, 2)}</pre> : <p>Loading...</p>}
        </div>
    );
};

const AnalyticsPage: React.FC = () => {
    return <div>Full analytics page here</div>;
};

// Register the extension
createExtension({
    config: {
        id: 'acme/server-analytics',
        name: 'Server Analytics',
        version: '1.0.0',
    },
    slots: [
        { slot: 'dashboard.widgets', component: AnalyticsWidget, order: 10 },
    ],
    routes: [
        { area: 'server', path: '/analytics', name: 'Analytics', component: AnalyticsPage },
    ],
});
```

### Build with Webpack

Use the SDK's base config or your own:

```js
// webpack.config.js
const base = require('@notur/sdk/webpack.extension.config');
module.exports = {
    ...base,
    entry: './resources/frontend/src/index.tsx',
    output: {
        ...base.output,
        filename: 'extension.js',
    },
};
```

```bash
bunx webpack --mode production
```

The built bundle goes to `resources/frontend/dist/extension.js`.

**React and ReactDOM are externalized** — your bundle uses the panel's existing React instance via `window.React` and `window.ReactDOM`. Do not bundle React.

## Step 5: Test Locally

```bash
# Link your extension for development
cd /var/www/pterodactyl
php artisan notur:dev /path/to/acme-server-analytics

# Your extension is now symlinked and active
# PHP changes take effect immediately
# Frontend changes require rebuilding the JS bundle
```

## Step 6: Export and Distribute

```bash
# Create a .notur archive
php artisan notur:export /path/to/acme-server-analytics

# Output: acme-server-analytics-1.0.0.notur
# Also generates: .sha256 checksum file
```

Users install with:
```bash
php artisan notur:install /path/to/acme-server-analytics-1.0.0.notur
```

## Available Frontend Slots

| Slot ID | Location | Type |
|---|---|---|
| `navbar` | Top navigation bar | Component portal |
| `navbar.left` | Navbar left (near logo) | Component portal |
| `server.subnav` | Server sub-navigation | Nav items |
| `server.header` | Server header area | Component portal |
| `server.page` | Server area | Full route/page |
| `server.footer` | Server footer area | Component portal |
| `server.terminal.buttons` | Terminal power buttons | Component portal |
| `server.console.header` | Console page header | Component portal |
| `server.console.sidebar` | Console sidebar area | Component portal |
| `server.console.footer` | Console page footer | Component portal |
| `server.files.actions` | File manager toolbar | Component portal |
| `server.files.header` | File manager header | Component portal |
| `server.files.footer` | File manager footer | Component portal |
| `dashboard.header` | Dashboard header area | Component portal |
| `dashboard.widgets` | Dashboard below server list | Component portal |
| `dashboard.serverlist.before` | Before dashboard server list | Component portal |
| `dashboard.serverlist.after` | After dashboard server list | Component portal |
| `dashboard.footer` | Dashboard footer area | Component portal |
| `dashboard.page` | Dashboard area | Full route/page |
| `account.header` | Account header area | Component portal |
| `account.page` | Account area | Full route/page |
| `account.footer` | Account footer area | Component portal |
| `account.subnav` | Account sub-navigation | Nav items |

## Available Hooks

From the bridge runtime (`window.__NOTUR__.hooks`):

| Hook | Purpose |
|---|---|
| `useSlot(slotId)` | Get all components registered for a slot |
| `useExtensionApi({ extensionId })` | HTTP client scoped to your extension's API routes |
| `useExtensionState(extensionId, initialState)` | Shared state across your extension's components |
| `useNoturTheme()` | Access CSS custom properties / theme |

From the SDK (`@notur/sdk`):

| Hook | Purpose |
|---|---|
| `useServerContext()` | Current server UUID, name, permissions |
| `useUserContext()` | Current user info |
| `usePermission(permission)` | Check if user has a specific permission |

## Theming

Extensions can use CSS custom properties for consistent styling:

```css
.my-widget {
    background: var(--notur-bg-secondary);
    color: var(--notur-text-primary);
    border-radius: var(--notur-radius-md);
    font-family: var(--notur-font-sans);
}
```

Theme extensions can override these variables by shipping a CSS file that redefines `:root` properties.
