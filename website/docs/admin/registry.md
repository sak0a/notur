# Notur Extension Registry

The Notur registry is a GitHub-backed index of available extensions. It allows panel administrators to discover, install, and update extensions via the CLI.

## Table of Contents

- [How the Registry Works](#how-the-registry-works)
- [Registry Index Format](#registry-index-format)
- [Extension Manifest Schema](#extension-manifest-schema)
- [Publishing Extensions](#publishing-extensions)
- [The .notur Archive Format](#the-notur-archive-format)
- [Signature Verification](#signature-verification)
- [Building a Registry Index](#building-a-registry-index)
- [Self-Hosted Registries](#self-hosted-registries)

---

## How the Registry Works

The registry is a static JSON file (`registry.json`) hosted on a GitHub repository. It contains metadata about available extensions -- their IDs, versions, descriptions, repository URLs, and requirements.

### Flow

1. **Sync**: `php artisan notur:registry:sync` fetches `registry.json` from the configured registry URL and caches it locally at `storage/notur/registry-cache.json`. The cache TTL is configurable.
2. **Search**: `php artisan notur:registry:sync --search "analytics"` searches the cached (or freshly fetched) index by ID, name, description, and tags.
3. **Install**: `php artisan notur:install acme/analytics` looks up the extension in the registry, downloads the `.notur` archive from its GitHub release, and installs it.
4. **Update**: `php artisan notur:update` compares installed versions against the registry and offers to update any extensions with newer versions available.
5. **Status**: `php artisan notur:registry:status` shows cache age, TTL, size, and extension count.

### RegistryClient

The `Notur\Support\RegistryClient` class handles all registry operations:

```php
class RegistryClient
{
    // Fetch the raw registry index from the remote URL
    public function fetchIndex(): array;

    // Search for extensions matching a keyword query
    // Searches ID, name, description, and tags
    public function search(string $query): array;

    // Get metadata for a specific extension by ID
    public function getExtension(string $extensionId): ?array;

    // Download a .notur archive from the extension's repository
    public function download(string $extensionId, string $version, string $targetPath): void;

    // Sync the remote index to a local cache file
    // Returns the number of extensions in the index
    public function syncToCache(string $cachePath): int;

    // Load registry data from a local cache file
    // Returns null if cache is missing or expired
    public function loadFromCache(string $cachePath, bool $ignoreExpiry = false): ?array;

    // Check if the local cache is still valid (not expired)
    public function isCacheFresh(string $cachePath): bool;
}
```

Configuration is read from `config/notur.php`:

```php
'registry_url' => 'https://raw.githubusercontent.com/notur/registry/main',
'registry_cache_path' => storage_path('notur/registry-cache.json'),
'registry_cache_ttl' => 3600,
```

Use `php artisan notur:registry:status --json` for machine-readable cache metadata.

---

## Registry Index Format

The registry index file (`registry.json`) has the following structure:

```json
{
    "version": "1.0",
    "updated_at": "2025-01-15T12:00:00Z",
    "extensions": [
        {
            "id": "acme/server-analytics",
            "name": "Server Analytics",
            "description": "Real-time server analytics and monitoring",
            "latest_version": "1.2.0",
            "versions": ["1.0.0", "1.1.0", "1.2.0"],
            "license": "MIT",
            "authors": [
                { "name": "John Doe", "email": "john@example.com" }
            ],
            "requires": {
                "notur": "^1.0",
                "pterodactyl": "^1.11",
                "php": "^8.2"
            },
            "repository": "https://github.com/acme/server-analytics",
            "tags": ["analytics", "monitoring", "server"],
            "dependencies": {
                "acme/core-lib": "^1.0"
            }
        }
    ]
}
```

### Extension Entry Fields

| Field | Type | Required | Description |
|---|---|---|---|
| `id` | `string` | Yes | Unique identifier in `vendor/name` format |
| `name` | `string` | Yes | Human-readable name |
| `description` | `string` | No | Short description |
| `latest_version` | `string` | Yes | Latest available version |
| `versions` | `string[]` | No | All available versions |
| `license` | `string` | No | SPDX license identifier |
| `authors` | `object[]` | No | Author objects with `name` and optional `email` |
| `requires` | `object` | No | Version constraints for `notur`, `pterodactyl`, `php` |
| `repository` | `string` | Yes | GitHub repository URL |
| `tags` | `string[]` | No | Searchable tags |
| `dependencies` | `object` | No | Other Notur extensions this depends on |

---

## Extension Manifest Schema

Every extension must include an `extension.yaml` (or `extension.yml`) in its root directory. Notur validates manifests against a JSON schema at `registry/schema/extension-manifest.schema.json`.

### Required Fields

```yaml
notur: "1.0"                    # Manifest format version
id: "acme/server-analytics"     # Unique ID (pattern: ^[a-z0-9\-]+/[a-z0-9\-]+$)
name: "Server Analytics"        # Human-readable name
version: "1.0.0"                # Semantic version
entrypoint: "Acme\\ServerAnalytics\\ServerAnalyticsExtension"  # PHP class
```

### Full Manifest Example

```yaml
notur: "1.0"
id: "acme/server-analytics"
name: "Server Analytics"
version: "1.0.0"
description: "Real-time server analytics and monitoring"
authors:
  - name: "John Doe"
    email: "john@example.com"
license: "MIT"

requires:
  notur: "^1.0"
  pterodactyl: "^1.11"
  php: "^8.2"

dependencies:
  acme/core-lib: "^1.0"

entrypoint: "Acme\\ServerAnalytics\\ServerAnalyticsExtension"

autoload:
  psr-4:
    "Acme\\ServerAnalytics\\": "src/"

backend:
  routes:
    api-client: "src/routes/api-client.php"
    admin: "src/routes/admin.php"
  migrations: "database/migrations"
  commands:
    - "Acme\\ServerAnalytics\\Console\\SyncCommand"
  middleware:
    web:
      - "Acme\\ServerAnalytics\\Http\\Middleware\\TrackPageView"
  events:
    "Pterodactyl\\Events\\Server\\Created":
      - "Acme\\ServerAnalytics\\Listeners\\InitializeAnalytics"
  permissions:
    - "analytics.view"
    - "analytics.export"
    - "analytics.admin"

frontend:
  bundle: "resources/frontend/dist/extension.js"
  styles: "resources/frontend/dist/extension.css"
  slots:
    server.subnav:
      label: "Analytics"
      icon: "chart-bar"
      permission: "analytics.view"
    dashboard.widgets:
      component: "AnalyticsWidget"
      order: 10

admin:
  views:
    settings: "resources/views/admin/settings.blade.php"
  settings:
    title: "Settings"
    description: "Configure analytics behavior"
    fields:
      - key: "api_key"
        label: "API Key"
        type: "string"
        required: true
        help: "Used to authenticate with the analytics service."
      - key: "mode"
        label: "Mode"
        type: "select"
        options:
          - value: "fast"
            label: "Fast"
          - value: "safe"
            label: "Safe"
      - key: "enabled"
        label: "Enable Extension"
        type: "boolean"
        default: true
        public: true
```

---

## Publishing Extensions

To publish an extension to the registry:

### Step 1: Create a GitHub Repository

Structure your repository with an `extension.yaml` at the root. See the [manifest schema](#extension-manifest-schema) above.

### Step 2: Build and Export

```bash
# Build the frontend bundle (using npx, yarn dlx, pnpm dlx, or bunx)
cd your-extension
npx webpack --mode production

# Export as a .notur archive
php artisan notur:export /path/to/your-extension

# Output: acme-server-analytics-1.0.0.notur
# Also generated: acme-server-analytics-1.0.0.notur.sha256
```

### Step 3: Create a GitHub Release

1. Tag your release (e.g., `v1.0.0`).
2. Attach the `.notur` archive to the release.
3. Optionally attach the `.sha256` checksum and `.sig` signature files.

The download URL follows the pattern:
```
https://github.com/{vendor}/{name}/releases/download/v{version}/{vendor}-{name}-{version}.notur
```

### Step 4: Add to the Registry

Add your repository to the registry's configuration file and rebuild the index, or submit a pull request to the registry repository.

---

## The .notur Archive Format

A `.notur` file is a gzipped tar archive (`tar.gz`) containing the extension's files.

### Contents

```
acme-server-analytics-1.0.0.notur (tar.gz)
  extension.yaml
  src/
    ServerAnalyticsExtension.php
    routes/
      api-client.php
    Http/
      Controllers/
        ...
  database/
    migrations/
      ...
  resources/
    frontend/
      dist/
        extension.js
```

### Associated Files

| File | Purpose |
|---|---|
| `{name}-{version}.notur` | The archive itself (tar.gz) |
| `{name}-{version}.notur.sha256` | SHA-256 checksum of the archive |
| `{name}-{version}.notur.sig` | Ed25519 signature (optional) |

### Creating Archives Manually

```bash
cd /path/to/your-extension
tar czf acme-server-analytics-1.0.0.notur \
    extension.yaml src/ database/ resources/frontend/dist/ \
    --exclude='node_modules' \
    --exclude='.git'

sha256sum acme-server-analytics-1.0.0.notur > acme-server-analytics-1.0.0.notur.sha256
```

Or use the `notur:export` command, which handles this automatically.

---

## Signature Verification

Notur supports Ed25519 signatures on `.notur` archives to verify their integrity and authenticity.

### How It Works

1. The publisher signs the archive with their Ed25519 secret key.
2. The signature is stored in a `.sig` file alongside the archive.
3. When `notur.require_signatures` is `true`, the `InstallCommand` verifies the signature using the configured public key before installation.
4. If verification fails, installation is aborted.

### Configuration

In `config/notur.php`:

```php
// Require valid signatures for all installs
'require_signatures' => true,

// The Ed25519 public key (set via environment variable)
'public_key' => env('NOTUR_PUBLIC_KEY', ''),
```

### Generating Keys

Generate a keypair with the built-in command (hex-encoded keys):

```bash
php artisan notur:keygen
```

Store the public key in your panel's `.env`:

```
NOTUR_PUBLIC_KEY=hex-encoded-public-key-here
```

### Signing Archives

Use the `--sign` flag when exporting:

```bash
php artisan notur:export --sign
```

This reads the secret key from the environment and generates a `.sig` file alongside the `.notur` archive.

---

## Building a Registry Index

The registry index builder tool generates a `registry.json` from extension repositories.

### From a Local Directory

```bash
php registry/tools/build-index.php /path/to/extensions --output registry.json
```

Expected directory layout:

```
extensions/
  vendor1/
    extension-a/
      extension.yaml
    extension-b/
      extension.yaml
  vendor2/
    ...
```

### From GitHub Repositories

Create a config file listing repositories:

```json
{
    "repositories": [
        "acme/server-analytics",
        "acme/backup-manager",
        "notur/hello-world"
    ]
}
```

Then run:

```bash
php registry/tools/build-index.php --config repos.json --output registry.json
```

The tool fetches `extension.yaml` from each repository's default branch (tries `main`, then `master`) and generates the registry index.

### Schema Validation

Registry entries and manifests can be validated against the JSON schemas shipped at:

- `registry/schema/registry-index.schema.json`
- `registry/schema/extension-manifest.schema.json`

---

## Self-Hosted Registries

To host your own private registry:

1. Generate a `registry.json` using the build tool.
2. Host it on a web server, GitHub Pages, or any static file host.
3. Configure panels to point to your registry:

```php
// config/notur.php
'registry_url' => 'https://extensions.mycompany.com',
```

4. Ensure your extensions' `.notur` archives are downloadable at the expected URL pattern:
   ```
   {repository}/releases/download/v{version}/{vendor}-{name}-{version}.notur
   ```

For fully private registries behind authentication, you may need to customize the `RegistryClient` or configure a Guzzle middleware to add authentication headers.
