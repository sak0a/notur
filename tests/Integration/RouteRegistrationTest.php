<?php

declare(strict_types=1);

namespace Notur\Tests\Integration;

use Notur\NoturServiceProvider;
use Orchestra\Testbench\TestCase;

class RouteRegistrationTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [NoturServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    public function test_notur_api_routes_are_registered(): void
    {
        $routes = collect($this->app['router']->getRoutes()->getRoutes());

        $noturRoutes = $routes->filter(function ($route) {
            return str_starts_with($route->uri(), 'api/client/notur');
        });

        $this->assertTrue($noturRoutes->isNotEmpty(), 'Notur API routes should be registered.');
    }

    public function test_notur_admin_routes_are_registered(): void
    {
        $routes = collect($this->app['router']->getRoutes()->getRoutes());

        $adminRoutes = $routes->filter(function ($route) {
            return str_starts_with($route->uri(), 'admin/notur');
        });

        $this->assertTrue($adminRoutes->isNotEmpty(), 'Notur admin routes should be registered.');
    }
}
