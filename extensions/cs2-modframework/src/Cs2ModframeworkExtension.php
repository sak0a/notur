<?php

declare(strict_types=1);

namespace Notur\Cs2Modframework;

use Notur\Contracts\HasRoutes;
use Notur\Support\NoturExtension;

class Cs2ModframeworkExtension extends NoturExtension implements HasRoutes
{
    public function getRouteFiles(): array
    {
        return [
            'api-client' => 'src/routes/api-client.php',
        ];
    }
}
