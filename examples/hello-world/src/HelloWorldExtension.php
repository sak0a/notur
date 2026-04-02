<?php

declare(strict_types=1);

namespace Notur\HelloWorld;

use Notur\Contracts\HasRoutes;
use Notur\Support\NoturExtension;

class HelloWorldExtension extends NoturExtension implements HasRoutes
{
    public function getRouteFiles(): array
    {
        return [
            'api-client' => 'src/routes/api-client.php',
        ];
    }
}
