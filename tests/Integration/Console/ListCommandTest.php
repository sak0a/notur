<?php

declare(strict_types=1);

namespace Notur\Tests\Integration\Console;

use Notur\Models\InstalledExtension;
use Notur\NoturServiceProvider;
use Orchestra\Testbench\TestCase;

class ListCommandTest extends TestCase
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

    public function test_lists_installed_extensions(): void
    {
        InstalledExtension::create([
            'extension_id' => 'acme/first',
            'name' => 'First Extension',
            'version' => '1.0.0',
            'enabled' => true,
            'manifest' => [],
        ]);

        InstalledExtension::create([
            'extension_id' => 'acme/second',
            'name' => 'Second Extension',
            'version' => '2.0.0',
            'enabled' => false,
            'manifest' => [],
        ]);

        $this->artisan('notur:list')
            ->assertExitCode(0);
    }

    public function test_shows_enabled_status(): void
    {
        InstalledExtension::create([
            'extension_id' => 'acme/enabled',
            'name' => 'Enabled Extension',
            'version' => '1.0.0',
            'enabled' => true,
            'manifest' => [],
        ]);

        InstalledExtension::create([
            'extension_id' => 'acme/disabled',
            'name' => 'Disabled Extension',
            'version' => '1.0.0',
            'enabled' => false,
            'manifest' => [],
        ]);

        $this->artisan('notur:list')
            ->assertExitCode(0);
    }

    public function test_handles_no_extensions(): void
    {
        $this->artisan('notur:list')
            ->expectsOutput('No extensions installed.')
            ->assertExitCode(0);
    }

    public function test_filters_enabled_only(): void
    {
        InstalledExtension::create([
            'extension_id' => 'acme/enabled',
            'name' => 'Enabled Extension',
            'version' => '1.0.0',
            'enabled' => true,
            'manifest' => [],
        ]);

        InstalledExtension::create([
            'extension_id' => 'acme/disabled',
            'name' => 'Disabled Extension',
            'version' => '1.0.0',
            'enabled' => false,
            'manifest' => [],
        ]);

        $this->artisan('notur:list', ['--enabled' => true])
            ->assertExitCode(0);
    }

    public function test_filters_disabled_only(): void
    {
        InstalledExtension::create([
            'extension_id' => 'acme/enabled',
            'name' => 'Enabled Extension',
            'version' => '1.0.0',
            'enabled' => true,
            'manifest' => [],
        ]);

        InstalledExtension::create([
            'extension_id' => 'acme/disabled',
            'name' => 'Disabled Extension',
            'version' => '1.0.0',
            'enabled' => false,
            'manifest' => [],
        ]);

        $this->artisan('notur:list', ['--disabled' => true])
            ->assertExitCode(0);
    }
}
