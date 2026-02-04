<?php

declare(strict_types=1);

namespace Notur\Tests\Unit\Models;

use Notur\Models\InstalledExtension;
use Notur\NoturServiceProvider;
use Orchestra\Testbench\TestCase;

class InstalledExtensionTest extends TestCase
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

    public function test_creates_extension_record(): void
    {
        $extension = InstalledExtension::create([
            'extension_id' => 'acme/test',
            'name' => 'Test Extension',
            'version' => '1.0.0',
            'enabled' => true,
            'manifest' => ['id' => 'acme/test'],
        ]);

        $this->assertDatabaseHas('notur_extensions', [
            'extension_id' => 'acme/test',
            'name' => 'Test Extension',
            'version' => '1.0.0',
        ]);
    }

    public function test_scope_enabled_filters_correctly(): void
    {
        InstalledExtension::create([
            'extension_id' => 'acme/enabled',
            'name' => 'Enabled',
            'version' => '1.0.0',
            'enabled' => true,
            'manifest' => [],
        ]);

        InstalledExtension::create([
            'extension_id' => 'acme/disabled',
            'name' => 'Disabled',
            'version' => '1.0.0',
            'enabled' => false,
            'manifest' => [],
        ]);

        $enabled = InstalledExtension::where('enabled', true)->get();

        $this->assertCount(1, $enabled);
        $this->assertSame('acme/enabled', $enabled->first()->extension_id);
    }

    public function test_scope_by_id_filters_correctly(): void
    {
        InstalledExtension::create([
            'extension_id' => 'acme/first',
            'name' => 'First',
            'version' => '1.0.0',
            'enabled' => true,
            'manifest' => [],
        ]);

        InstalledExtension::create([
            'extension_id' => 'acme/second',
            'name' => 'Second',
            'version' => '1.0.0',
            'enabled' => true,
            'manifest' => [],
        ]);

        $found = InstalledExtension::where('extension_id', 'acme/first')->first();

        $this->assertNotNull($found);
        $this->assertSame('First', $found->name);
    }

    public function test_casts_manifest_to_array(): void
    {
        $manifest = [
            'id' => 'acme/test',
            'name' => 'Test',
            'backend' => [
                'routes' => ['api-client' => 'routes/api.php'],
            ],
        ];

        $extension = InstalledExtension::create([
            'extension_id' => 'acme/test',
            'name' => 'Test',
            'version' => '1.0.0',
            'enabled' => true,
            'manifest' => $manifest,
        ]);

        $retrieved = InstalledExtension::find($extension->id);

        $this->assertIsArray($retrieved->manifest);
        $this->assertSame('acme/test', $retrieved->manifest['id']);
        $this->assertSame('routes/api.php', $retrieved->manifest['backend']['routes']['api-client']);
    }

    public function test_casts_enabled_to_boolean(): void
    {
        $extension = InstalledExtension::create([
            'extension_id' => 'acme/test',
            'name' => 'Test',
            'version' => '1.0.0',
            'enabled' => 1,
            'manifest' => [],
        ]);

        $retrieved = InstalledExtension::find($extension->id);

        $this->assertIsBool($retrieved->enabled);
        $this->assertTrue($retrieved->enabled);
    }
}
