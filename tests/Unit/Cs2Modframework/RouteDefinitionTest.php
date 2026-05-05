<?php

declare(strict_types=1);

namespace Notur\Tests\Unit\Cs2Modframework;

use PHPUnit\Framework\TestCase;

class RouteDefinitionTest extends TestCase
{
    public function test_mutating_routes_require_extension_permission(): void
    {
        $routes = file_get_contents(__DIR__ . '/../../../extensions/cs2-modframework/src/routes/api-client.php');

        $this->assertIsString($routes);
        $this->assertStringContainsString("->middleware(['notur.namespace', 'notur.server-access'])", $routes);
        $this->assertSame(2, substr_count($routes, "->middleware('notur.permission:cs2-modframework.manage')"));
    }
}
