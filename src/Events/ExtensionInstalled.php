<?php

declare(strict_types=1);

namespace Notur\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ExtensionInstalled
{
    use Dispatchable;

    public function __construct(
        public readonly string $extensionId,
        public readonly string $version,
    ) {}
}
