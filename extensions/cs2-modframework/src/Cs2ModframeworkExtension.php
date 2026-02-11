<?php

declare(strict_types=1);

namespace Notur\Cs2Modframework;

use Notur\Contracts\ExtensionInterface;
use Notur\Contracts\HasRoutes;

class Cs2ModframeworkExtension implements ExtensionInterface, HasRoutes
{
    public function getId(): string
    {
        return 'notur/cs2-modframework';
    }

    public function getName(): string
    {
        return 'CS2 Mod Frameworks';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getBasePath(): string
    {
        return __DIR__ . '/..';
    }

    public function register(): void
    {
    }

    public function boot(): void
    {
    }

    public function getRouteFiles(): array
    {
        return [
            'api-client' => 'src/routes/api-client.php',
        ];
    }
}
