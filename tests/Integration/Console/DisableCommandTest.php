<?php

declare(strict_types=1);

namespace Notur\Tests\Integration\Console;

use Notur\Models\InstalledExtension;
use Notur\NoturServiceProvider;
use Orchestra\Testbench\TestCase;

class DisableCommandTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
    }

    public function test_reports_already_disabled(): void
    {
        InstalledExtension::create([
            'extension_id' => 'acme/test',
            'name' => 'Test Extension',
            'version' => '1.0.0',
            'enabled' => false,
            'manifest' => ['id' => 'acme/test'],
        ]);

        $this->artisan('notur:disable', ['extension' => 'acme/test'])
            ->expectsOutput("Extension 'acme/test' is already disabled.")
            ->assertExitCode(0);
    }

    public function test_fails_for_unknown_extension(): void
    {
        $this->artisan('notur:disable', ['extension' => 'unknown/extension'])
            ->expectsOutput("Extension 'unknown/extension' is not installed.")
            ->assertExitCode(1);
    }

}
