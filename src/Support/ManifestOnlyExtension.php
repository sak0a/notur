<?php

declare(strict_types=1);

namespace Notur\Support;

use Notur\Contracts\ExtensionInterface;
use Notur\ExtensionManifest;

class ManifestOnlyExtension implements ExtensionInterface
{
    public function __construct(
        private readonly ExtensionManifest $manifest,
        private readonly string $basePath,
    ) {}

    public function getId(): string
    {
        return $this->manifest->getId();
    }

    public function getName(): string
    {
        return $this->manifest->getName();
    }

    public function getVersion(): string
    {
        return $this->manifest->getVersion();
    }

    public function register(): void
    {
        // Manifest-only extensions have no PHP bootstrap logic.
    }

    public function boot(): void
    {
        // Manifest-only extensions have no PHP bootstrap logic.
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }
}
