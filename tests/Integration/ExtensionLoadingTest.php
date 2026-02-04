<?php

declare(strict_types=1);

namespace Notur\Tests\Integration;

use Notur\ExtensionManager;
use Notur\NoturServiceProvider;
use Orchestra\Testbench\TestCase;

class ExtensionLoadingTest extends TestCase
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

    public function test_service_provider_is_registered(): void
    {
        $this->assertInstanceOf(
            ExtensionManager::class,
            $this->app->make(ExtensionManager::class),
        );
    }

    public function test_extension_manager_is_singleton(): void
    {
        $a = $this->app->make(ExtensionManager::class);
        $b = $this->app->make(ExtensionManager::class);

        $this->assertSame($a, $b);
    }

    public function test_config_is_loaded(): void
    {
        $version = config('notur.version');
        $this->assertIsString($version);
        $this->assertNotSame('', $version);
        $this->assertMatchesRegularExpression('/^\\d+\\.\\d+\\.\\d+$/', $version);
    }

    public function test_boots_with_no_extensions(): void
    {
        $manager = $this->app->make(ExtensionManager::class);
        $this->assertEmpty($manager->all());
    }
}
