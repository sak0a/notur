<?php

declare(strict_types=1);

namespace Notur\FullExtension;

use Notur\Contracts\HasBladeViews;
use Notur\Contracts\HasMigrations;
use Notur\Contracts\HasRoutes;
use Notur\Support\NoturExtension;

class FullExampleExtension extends NoturExtension implements HasRoutes, HasMigrations, HasBladeViews
{
    public function getRouteFiles(): array
    {
        return [
            'api-client' => 'src/routes/api-client.php',
            'admin' => 'src/routes/admin.php',
        ];
    }

    public function getMigrationsPath(): string
    {
        return $this->getBasePath() . '/database/migrations';
    }

    public function getViewsPath(): string
    {
        return $this->getBasePath() . '/resources/views';
    }

    public function getViewNamespace(): string
    {
        return 'notur-full-extension';
    }
}
