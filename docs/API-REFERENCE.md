# Notur PHP API Reference

This document covers all PHP contracts, interfaces, services, and systems available to extension developers.

## Table of Contents

- [Contracts (Interfaces)](#contracts-interfaces)
- [Route Groups](#route-groups)
- [Permission System](#permission-system)
- [Migration System](#migration-system)
- [Events](#events)
- [Models](#models)
- [Configuration](#configuration)
- [CLI Commands](#cli-commands)

---

## Contracts (Interfaces)

All contracts live in the `Notur\Contracts` namespace. Your extension's entrypoint class must implement `ExtensionInterface`. The remaining interfaces are opt-in and enable specific capabilities.

### `ExtensionInterface` (required)

The base contract that every extension must implement.

```php
namespace Notur\Contracts;

interface ExtensionInterface
{
    /**
     * Get the unique identifier of the extension (e.g., "acme/server-analytics").
     */
    public function getId(): string;

    /**
     * Get the human-readable name of the extension.
     */
    public function getName(): string;

    /**
     * Get the extension version string.
     */
    public function getVersion(): string;

    /**
     * Register bindings, services, or configuration.
     * Called during the "register" phase of Laravel's boot cycle.
     */
    public function register(): void;

    /**
     * Boot the extension after all extensions have been registered.
     * Called during the "boot" phase.
     */
    public function boot(): void;

    /**
     * Return the absolute path to the extension's root directory.
     */
    public function getBasePath(): string;
}
```

**Example:**

```php
use Notur\Contracts\ExtensionInterface;

class MyExtension implements ExtensionInterface
{
    public function getId(): string { return 'acme/my-extension'; }
    public function getName(): string { return 'My Extension'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getBasePath(): string { return __DIR__ . '/..'; }

    public function register(): void
    {
        // Bind services into the container, set config defaults, etc.
    }

    public function boot(): void
    {
        // Post-registration logic: publish assets, register view composers, etc.
    }
}
```

### `HasRoutes`

Opt into HTTP route registration.

```php
namespace Notur\Contracts;

interface HasRoutes
{
    /**
     * Return an array of route file paths keyed by group name.
     *
     * Supported groups: "api-client", "admin", "web"
     *
     * @return array<string, string>
     */
    public function getRouteFiles(): array;
}
```

**Example:**

```php
public function getRouteFiles(): array
{
    return [
        'api-client' => 'src/routes/api-client.php',
        'admin'      => 'src/routes/admin.php',
        'web'        => 'src/routes/web.php',
    ];
}
```

Route file paths are relative to the extension's base directory.

### `HasMigrations`

Opt into database migration management.

```php
namespace Notur\Contracts;

interface HasMigrations
{
    /**
     * Return the path to the extension's migrations directory.
     */
    public function getMigrationsPath(): string;
}
```

**Example:**

```php
public function getMigrationsPath(): string
{
    return $this->getBasePath() . '/database/migrations';
}
```

Migrations are tracked per-extension in the `notur_migrations` table, separate from Laravel's own migration table.

### `HasCommands`

Register Artisan console commands.

```php
namespace Notur\Contracts;

interface HasCommands
{
    /**
     * Return an array of artisan command class names.
     *
     * @return array<class-string>
     */
    public function getCommands(): array;
}
```

**Example:**

```php
public function getCommands(): array
{
    return [
        \Acme\Analytics\Console\SyncCommand::class,
        \Acme\Analytics\Console\PruneCommand::class,
    ];
}
```

### `HasMiddleware`

Register HTTP middleware into middleware groups.

```php
namespace Notur\Contracts;

interface HasMiddleware
{
    /**
     * Return middleware class names keyed by middleware group.
     *
     * @return array<string, array<class-string>>
     */
    public function getMiddleware(): array;
}
```

**Example:**

```php
public function getMiddleware(): array
{
    return [
        'web' => [
            \Acme\Analytics\Http\Middleware\TrackPageView::class,
        ],
        'api' => [
            \Acme\Analytics\Http\Middleware\RateLimit::class,
        ],
    ];
}
```

### `HasEventListeners`

Register event listeners.

```php
namespace Notur\Contracts;

interface HasEventListeners
{
    /**
     * Return event-to-listener mappings.
     *
     * @return array<class-string, array<class-string>>
     */
    public function getEventListeners(): array;
}
```

**Example:**

```php
public function getEventListeners(): array
{
    return [
        \Pterodactyl\Events\Server\Created::class => [
            \Acme\Analytics\Listeners\InitializeServerAnalytics::class,
        ],
        \Pterodactyl\Events\Server\Deleted::class => [
            \Acme\Analytics\Listeners\CleanupServerAnalytics::class,
        ],
    ];
}
```

### `HasBladeViews`

Register a Blade view namespace for server-side rendering.

```php
namespace Notur\Contracts;

interface HasBladeViews
{
    /**
     * Return the path to the extension's views directory.
     */
    public function getViewsPath(): string;

    /**
     * Return the view namespace (e.g., "acme-analytics").
     */
    public function getViewNamespace(): string;
}
```

**Example:**

```php
public function getViewsPath(): string
{
    return $this->getBasePath() . '/resources/views';
}

public function getViewNamespace(): string
{
    return 'acme-analytics';
}
```

Views are then usable as `@include('acme-analytics::dashboard')`.

### `HasFrontendSlots`

Declare frontend slot metadata. This data is passed to the bridge runtime for rendering.

```php
namespace Notur\Contracts;

interface HasFrontendSlots
{
    /**
     * Return frontend slot registrations as defined in extension.yaml.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getFrontendSlots(): array;
}
```

**Example:**

```php
public function getFrontendSlots(): array
{
    return [
        'server.subnav' => [
            'label' => 'Analytics',
            'icon' => 'chart-bar',
            'permission' => 'analytics.view',
        ],
        'dashboard.widgets' => [
            'component' => 'AnalyticsWidget',
            'order' => 10,
        ],
    ];
}
```

---

## Route Groups

Extension routes are automatically scoped under namespaced URL prefixes. The route file receives a standard Laravel Router context.

| Group | URL Prefix | Default Middleware | Purpose |
|---|---|---|---|
| `api-client` | `/api/client/notur/{extension-id}/` | `client-api` | Client-facing API endpoints |
| `admin` | `/admin/notur/{extension-id}/` | `web`, `admin` | Admin panel endpoints |
| `web` | `/notur/{extension-id}/` | `web` | General web routes |

The `{extension-id}` is derived from your extension's ID with slashes preserved (e.g., `acme/server-analytics`).

**Route file example (`src/routes/api-client.php`):**

```php
<?php

use Illuminate\Support\Facades\Route;
use Acme\Analytics\Http\Controllers\StatsController;

// URL: /api/client/notur/acme/server-analytics/stats
Route::get('/stats', [StatsController::class, 'index']);

// URL: /api/client/notur/acme/server-analytics/export
Route::get('/export', [StatsController::class, 'export']);

// URL: /api/client/notur/acme/server-analytics/settings
Route::post('/settings', [StatsController::class, 'updateSettings']);
```

---

## Permission System

Extensions can define custom permissions in their `extension.yaml`:

```yaml
backend:
  permissions:
    - "analytics.view"
    - "analytics.export"
    - "analytics.admin"
```

These permissions are enforced via the `PermissionBroker` middleware, which runs on all extension route groups. On the frontend, the `usePermission` hook checks permissions against the current server context.

**Checking permissions in a controller:**

```php
// Permissions are automatically enforced by the middleware based on the
// route and the extension's declared permissions. You can also check manually:
if (!$request->user()->can('analytics.export')) {
    abort(403);
}
```

**Checking permissions on the frontend:**

```tsx
import { usePermission } from '@notur/sdk';

const AnalyticsExport: React.FC = () => {
    const canExport = usePermission('analytics.export');

    if (!canExport) return null;
    return <button>Export Data</button>;
};
```

---

## Migration System

Notur has its own migration system separate from Laravel's. Extension migrations are tracked in the `notur_migrations` table to enable per-extension migrate and rollback.

### How it works

1. When an extension is installed, its migrations directory (from `HasMigrations::getMigrationsPath()`) is scanned.
2. Each migration file is run and recorded in `notur_migrations` with the extension ID.
3. When an extension is removed, its migrations are rolled back in reverse order.
4. When an extension is updated, any new migration files are run.

### Migration file format

Use standard Laravel migration syntax:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ext_analytics_data', function (Blueprint $table) {
            $table->id();
            $table->string('server_uuid');
            $table->json('metrics');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_analytics_data');
    }
};
```

**Naming convention:** Prefix your tables with `ext_` or your vendor name to avoid collisions.

### Notur's own tables

Notur creates 3 tables during installation:

| Table | Purpose |
|---|---|
| `notur_extensions` | Installed extension records (ID, name, version, enabled status) |
| `notur_migrations` | Per-extension migration tracking |
| `notur_settings` | Per-extension key-value settings store |

---

## Events

Notur dispatches events during extension lifecycle operations. You can listen for these in other extensions or in custom Laravel listeners.

| Event Class | When Dispatched | Payload |
|---|---|---|
| `Notur\Events\ExtensionInstalled` | After an extension is installed | `string $extensionId` |
| `Notur\Events\ExtensionEnabled` | After an extension is enabled | `string $extensionId` |
| `Notur\Events\ExtensionDisabled` | After an extension is disabled | `string $extensionId` |
| `Notur\Events\ExtensionRemoved` | After an extension is removed | `string $extensionId` |

**Listening for events:**

```php
// In your extension's getEventListeners():
public function getEventListeners(): array
{
    return [
        \Notur\Events\ExtensionEnabled::class => [
            \Acme\MyExtension\Listeners\OnExtensionEnabled::class,
        ],
    ];
}
```

---

## Models

### `InstalledExtension`

The `Notur\Models\InstalledExtension` Eloquent model represents a row in the `notur_extensions` table.

**Key attributes:**

| Attribute | Type | Description |
|---|---|---|
| `extension_id` | `string` | The extension ID (`vendor/name`) |
| `name` | `string` | Human-readable name |
| `version` | `string` | Installed version |
| `enabled` | `bool` | Whether the extension is active |

---

## Configuration

The Notur config file (`config/notur.php`) exposes the following keys:

| Key | Type | Default | Description |
|---|---|---|---|
| `version` | `string` | `'1.0.0'` | Notur framework version |
| `extensions_path` | `string` | `'notur/extensions'` | Extension storage directory (relative to panel root) |
| `require_signatures` | `bool` | `false` | Require Ed25519 signatures on `.notur` archives |
| `registry_url` | `string` | `'https://raw.githubusercontent.com/notur/registry/main'` | Remote registry base URL |
| `registry_cache_path` | `string` | `storage_path('notur/registry-cache.json')` | Local registry cache file |
| `public_key` | `string` | `env('NOTUR_PUBLIC_KEY', '')` | Ed25519 public key for signature verification |

---

## CLI Commands

All commands are prefixed with `notur:` in Artisan.

| Command | Signature | Description |
|---|---|---|
| `notur:install` | `notur:install {extension} [--force] [--no-migrate]` | Install from registry or `.notur` file |
| `notur:remove` | `notur:remove {extension} [--keep-data]` | Remove an extension |
| `notur:enable` | `notur:enable {extension}` | Enable a disabled extension |
| `notur:disable` | `notur:disable {extension}` | Disable an extension without removing files |
| `notur:list` | `notur:list [--enabled] [--disabled]` | List installed extensions |
| `notur:update` | `notur:update {extension?} [--check]` | Update one or all extensions |
| `notur:new` | `notur:new {id} [--path=] [--preset=] [--with-api-routes|--no-api-routes] [--with-admin-routes|--no-admin-routes] [--with-frontend|--no-frontend] [--with-admin|--no-admin] [--with-migrations|--no-migrations] [--with-tests|--no-tests]` | Scaffold a new extension from templates |
| `notur:dev` | `notur:dev {path} [--link]` | Link a local extension for development |
| `notur:export` | `notur:export {path?} [--output=] [--sign]` | Export extension as `.notur` archive |
| `notur:build` | `notur:build` | Build extension frontend assets |
| `notur:registry:sync` | `notur:registry:sync [--search=] [--force]` | Sync or search the extension registry |
| `notur:uninstall` | `notur:uninstall [--confirm]` | Completely remove Notur from the panel |

### Preset Definitions

For `notur:new`:

- `standard`: frontend + API routes (default)
- `backend`: API routes only
- `full`: frontend + API routes + admin UI + migrations + tests
- `minimal`: backend-only scaffolding with no routes or frontend
