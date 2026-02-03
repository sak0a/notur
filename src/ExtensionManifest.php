<?php

declare(strict_types=1);

namespace Notur;

use Symfony\Component\Yaml\Yaml;
use InvalidArgumentException;

class ExtensionManifest
{
    private array $data;

    public function __construct(private readonly string $path)
    {
        if (!file_exists($path)) {
            throw new InvalidArgumentException("Manifest not found: {$path}");
        }

        $this->data = Yaml::parseFile($path);
        $this->validate();
    }

    public static function fromArray(array $data): self
    {
        $instance = new self(__FILE__); // dummy — overridden below
        // Reset with provided data instead
        $ref = new \ReflectionProperty($instance, 'data');
        $ref->setValue($instance, $data);
        $ref = new \ReflectionProperty($instance, 'path');
        $ref->setValue($instance, '');
        return $instance;
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
        $required = ['id', 'name', 'version', 'entrypoint'];
        foreach ($required as $field) {
            if (empty($this->data[$field])) {
                throw new InvalidArgumentException(
                    "Extension manifest at '{$this->path}' is missing required field: {$field}"
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
        return $this->data['entrypoint'];
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
        return $this->data['backend']['routes'] ?? [];
    }

    public function getMigrationsPath(): string
    {
        return $this->data['backend']['migrations'] ?? '';
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
        return $this->data['frontend']['bundle'] ?? '';
    }

    public function getFrontendStyles(): string
    {
        return $this->data['frontend']['styles'] ?? '';
    }

    public function getFrontendCssIsolation(): array
    {
        return $this->data['frontend']['css_isolation'] ?? [];
    }

    public function getFrontendSlots(): array
    {
        return $this->data['frontend']['slots'] ?? [];
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
}
