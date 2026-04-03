<?php

declare(strict_types=1);

namespace Notur\HelloWorld;

use Notur\Contracts\ExtensionInterface;
use Notur\Contracts\HasRoutes;
use Notur\Contracts\HasFrontendSlots;

class HelloWorldExtension implements ExtensionInterface, HasRoutes, HasFrontendSlots
{
    public function getId(): string
    {
        return 'notur/hello-world';
    }

    public function getName(): string
    {
        return 'Hello World';
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
        // Nothing to register for this simple extension
    }

    public function boot(): void
    {
        // Nothing to boot
    }

    public function getRouteFiles(): array
    {
        return [
            'api-client' => 'src/routes/api-client.php',
        ];
    }

    public function getFrontendSlots(): array
    {
        return [
            'dashboard.widgets' => [
                'component' => 'HelloWidget',
                'order' => 100,
            ],
        ];
    }
}
