<?php

declare(strict_types=1);

namespace Notur\Features;

use Illuminate\Contracts\Foundation\Application;
use Notur\Contracts\ExtensionInterface;
use Notur\ExtensionManifest;
use Notur\ExtensionManager;

final class ExtensionContext
{
    public function __construct(
        public readonly string $id,
        public readonly ExtensionInterface $extension,
        public readonly ExtensionManifest $manifest,
        public readonly string $path,
        public readonly Application $app,
        public readonly ExtensionManager $manager,
    ) {}
}
