<?php

declare(strict_types=1);

namespace Notur\Support;

use Notur\Contracts\ExtensionInterface;
use Notur\ExtensionManifest;
use ReflectionClass;
use RuntimeException;

/**
 * Abstract base class for Notur extensions.
 *
 * Provides default implementations for all ExtensionInterface methods,
 * reading metadata from the extension.yaml manifest automatically.
 *
 * Extensions extending this class only need to implement register() and boot()
 * if they need custom logic.
 *
 * @example
 * ```php
 * class MyExtension extends NoturExtension
 * {
 *     public function boot(): void
 *     {
 *         // Custom boot logic
 *     }
 * }
 * ```
 */
abstract class NoturExtension implements ExtensionInterface
{
    private ?ExtensionManifest $manifest = null;
    private ?string $basePath = null;

    /**
     * Get extension ID from manifest.
     */
    public function getId(): string
    {
        return $this->getManifest()->getId();
    }

    /**
     * Get extension display name from manifest.
     */
    public function getName(): string
    {
        return $this->getManifest()->getName();
    }

    /**
     * Get extension version from manifest.
     */
    public function getVersion(): string
    {
        return $this->getManifest()->getVersion();
    }

    /**
     * Get the extension's base directory path.
     *
     * Automatically determined via reflection, assuming the class
     * is located in a src/ subdirectory of the extension root.
     */
    public function getBasePath(): string
    {
        if ($this->basePath === null) {
            $this->basePath = $this->resolveBasePath();
        }
        return $this->basePath;
    }

    /**
     * Register bindings, services, or configuration.
     *
     * Override this method to add custom registration logic.
     * Called during the "register" phase of Laravel's boot cycle.
     */
    public function register(): void
    {
        // Default: no-op
    }

    /**
     * Boot the extension after all extensions have been registered.
     *
     * Override this method to add custom boot logic.
     * Called during the "boot" phase.
     */
    public function boot(): void
    {
        // Default: no-op
    }

    /**
     * Get the loaded manifest instance.
     *
     * Useful for accessing additional manifest properties.
     */
    protected function getManifest(): ExtensionManifest
    {
        if ($this->manifest === null) {
            $this->manifest = ExtensionManifest::load($this->getBasePath());
        }
        return $this->manifest;
    }

    /**
     * Allow setting a custom base path (useful for testing).
     */
    protected function setBasePath(string $path): void
    {
        $this->basePath = $path;
        $this->manifest = null; // Reset manifest cache
    }

    /**
     * Resolve the base path using reflection.
     */
    private function resolveBasePath(): string
    {
        $reflection = new ReflectionClass($this);
        $filename = $reflection->getFileName();

        if ($filename === false) {
            throw new RuntimeException(
                sprintf('Cannot determine base path for extension class %s', static::class)
            );
        }

        // Walk up to find directory containing extension.yaml
        $dir = dirname($filename);

        while (true) {
            if (file_exists($dir . '/extension.yaml') || file_exists($dir . '/extension.yml')) {
                return $dir;
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break; // Reached root
            }
            $dir = $parent;
        }

        // Fallback: assume src/ subdirectory structure
        return dirname($filename, 2);
    }
}
