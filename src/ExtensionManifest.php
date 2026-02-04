<?php

declare(strict_types=1);

namespace Notur;

use Notur\Support\SettingsNormalizer;
use Symfony\Component\Yaml\Yaml;
use InvalidArgumentException;

class ExtensionManifest
{
    private array $data;
    private string $path;
    private string $basePath;

    public function __construct(string $path, ?array $data = null, ?string $basePath = null)
    {
        $this->path = $path;
        $this->basePath = $basePath ?? (is_dir($path) ? rtrim($path, '/') : dirname($path));

        if ($data === null) {
            if (!file_exists($path)) {
                throw new InvalidArgumentException("Manifest not found: {$path}");
            }

            $this->data = Yaml::parseFile($path);
        } else {
            $this->data = $data;
        }
        $this->validate();
    }

    public static function fromArray(array $data, string $basePath = ''): self
    {
        $path = $basePath !== '' ? rtrim($basePath, '/') . '/extension.yaml' : '<memory>';
        $resolvedBase = $basePath !== '' ? rtrim($basePath, '/') : '';

        return new self($path, $data, $resolvedBase);
    }

    public static function load(string $extensionPath): self
    {
        $yamlPath = rtrim($extensionPath, '/') . '/extension.yaml';
        $ymlPath = rtrim($extensionPath, '/') . '/extension.yml';

        if (file_exists($yamlPath)) {
            return new self($yamlPath);
        }

        if (file_exists($ymlPath)) {
            return new self($ymlPath);
        }

        throw new InvalidArgumentException("No extension.yaml found in: {$extensionPath}");
    }

    private function validate(): void
    {
        $required = ['id', 'name', 'version'];
        foreach ($required as $field) {
            if (empty($this->data[$field])) {
                throw new InvalidArgumentException(
                    "Extension manifest at '{$this->path}' is missing required field: {$field}"
                );
            }
        }

        if (array_key_exists('entrypoint', $this->data)) {
            $entrypoint = $this->data['entrypoint'];
            if (!is_string($entrypoint) || $entrypoint === '') {
                throw new InvalidArgumentException(
                    "Extension manifest at '{$this->path}' has invalid entrypoint (must be non-empty string)."
                );
            }
        }

        if (!preg_match('#^[a-z0-9\-]+/[a-z0-9\-]+$#', $this->data['id'])) {
            throw new InvalidArgumentException(
                "Invalid extension ID '{$this->data['id']}': must be 'vendor/name' with lowercase alphanumeric characters and hyphens"
            );
        }
    }

    public function getId(): string
    {
        return $this->data['id'];
    }

    public function getName(): string
    {
        return $this->data['name'];
    }

    public function getVersion(): string
    {
        return $this->data['version'];
    }

    public function getDescription(): string
    {
        return $this->data['description'] ?? '';
    }

    public function getEntrypoint(): string
    {
        return $this->data['entrypoint'] ?? '';
    }

    public function getAuthors(): array
    {
        return $this->data['authors'] ?? [];
    }

    public function getLicense(): string
    {
        return $this->data['license'] ?? '';
    }

    public function getRequirements(): array
    {
        return $this->data['requires'] ?? [];
    }

    public function getDependencies(): array
    {
        return $this->data['dependencies'] ?? [];
    }

    public function hasCapabilitiesDeclared(): bool
    {
        return array_key_exists('capabilities', $this->data);
    }

    public function getCapabilities(): array
    {
        return $this->data['capabilities'] ?? [];
    }

    public function isCapabilityEnabled(string $capabilityId, int $majorVersion, bool $defaultIfMissing = false): bool
    {
        if (!$this->hasCapabilitiesDeclared()) {
            return $defaultIfMissing;
        }

        $capabilities = $this->getCapabilities();
        if (!isset($capabilities[$capabilityId])) {
            return false;
        }

        return \Notur\Support\CapabilityMatcher::matches((string) $capabilities[$capabilityId], $majorVersion);
    }

    public function getAutoload(): array
    {
        return $this->data['autoload'] ?? [];
    }

    public function getBackendConfig(): array
    {
        return $this->data['backend'] ?? [];
    }

    public function getFrontendConfig(): array
    {
        return $this->data['frontend'] ?? [];
    }

    public function getAdminConfig(): array
    {
        return $this->data['admin'] ?? [];
    }

    public function getPermissions(): array
    {
        return $this->data['backend']['permissions'] ?? [];
    }

    public function getRoutes(): array
    {
        $routes = $this->data['backend']['routes'] ?? null;
        if (is_array($routes) && $routes !== []) {
            return $routes;
        }

        return $this->resolveDefaultRoutes();
    }

    public function getMigrationsPath(): string
    {
        $path = $this->data['backend']['migrations'] ?? null;
        if (is_string($path) && $path !== '') {
            return $path;
        }

        return $this->resolveDefaultDir('database/migrations') ?? '';
    }

    public function getCommands(): array
    {
        return $this->data['backend']['commands'] ?? [];
    }

    public function getMiddleware(): array
    {
        return $this->data['backend']['middleware'] ?? [];
    }

    public function getEvents(): array
    {
        return $this->data['backend']['events'] ?? [];
    }

    public function getFrontendBundle(): string
    {
        $bundle = $this->data['frontend']['bundle'] ?? null;
        if (is_string($bundle) && $bundle !== '') {
            return $bundle;
        }

        return $this->resolveFirstExistingFile([
            'resources/frontend/dist/extension.js',
            'resources/frontend/dist/bundle.js',
            'dist/extension.js',
            'dist/bundle.js',
        ]) ?? '';
    }

    public function getFrontendStyles(): string
    {
        $styles = $this->data['frontend']['styles'] ?? null;
        if (is_string($styles) && $styles !== '') {
            return $styles;
        }

        return $this->resolveFirstExistingFile([
            'resources/frontend/dist/extension.css',
            'resources/frontend/dist/bundle.css',
            'dist/extension.css',
            'dist/bundle.css',
        ]) ?? '';
    }

    public function getFrontendCssIsolation(): array
    {
        return $this->data['frontend']['css_isolation'] ?? [];
    }

    /**
     * Get frontend slot definitions from manifest.
     *
     * @deprecated Define slots in frontend code via createExtension({ slots: [...] }) instead.
     * @return array<string, array<string, mixed>>
     */
    public function getFrontendSlots(): array
    {
        $slots = $this->data['frontend']['slots'] ?? [];

        if ($slots !== []) {
            @trigger_error(
                'frontend.slots in manifest is deprecated. Define slots in frontend code via createExtension({ slots: [...] }) instead.',
                E_USER_DEPRECATED
            );
        }

        return $slots;
    }

    /**
     * Get normalized admin settings schema.
     *
     * Supports both full format and shorthand format:
     * - Full: { fields: [{ key: "...", type: "...", ... }] }
     * - Shorthand: { api_key: { type: string }, enabled: { type: boolean } }
     *
     * @return array<string, mixed> Normalized settings with fields array
     */
    public function getSettings(): array
    {
        $settings = $this->data['admin']['settings'] ?? [];

        if (!is_array($settings)) {
            return [];
        }

        return SettingsNormalizer::normalize($settings);
    }

    public function getTheme(): array
    {
        return $this->data['theme'] ?? [];
    }

    public function getRaw(): array
    {
        return $this->data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    private function resolveDefaultFile(string $relativePath): ?string
    {
        if ($this->basePath === '') {
            return null;
        }

        $fullPath = $this->basePath . '/' . ltrim($relativePath, '/');
        return file_exists($fullPath) ? $relativePath : null;
    }

    /**
     * @param array<int, string> $paths
     */
    private function resolveFirstExistingFile(array $paths): ?string
    {
        foreach ($paths as $path) {
            $resolved = $this->resolveDefaultFile($path);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    private function resolveDefaultDir(string $relativePath): ?string
    {
        if ($this->basePath === '') {
            return null;
        }

        $fullPath = $this->basePath . '/' . ltrim($relativePath, '/');
        return is_dir($fullPath) ? $relativePath : null;
    }

    /**
     * @return array<string, string>
     */
    private function resolveDefaultRoutes(): array
    {
        if ($this->basePath === '') {
            return [];
        }

        $defaults = [
            'api-client' => 'src/routes/api-client.php',
            'admin' => 'src/routes/admin.php',
            'web' => 'src/routes/web.php',
        ];

        $resolved = [];
        foreach ($defaults as $group => $relativePath) {
            $fullPath = $this->basePath . '/' . $relativePath;
            if (file_exists($fullPath)) {
                $resolved[$group] = $relativePath;
            }
        }

        return $resolved;
    }
}
