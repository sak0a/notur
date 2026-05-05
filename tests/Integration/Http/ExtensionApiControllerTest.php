<?php

declare(strict_types=1);

namespace Notur\Tests\Integration\Http;

use Mockery;
use Notur\Contracts\ExtensionInterface;
use Notur\ExtensionManager;
use Notur\ExtensionManifest;
use Notur\Http\Controllers\ExtensionApiController;
use Notur\Models\ExtensionSetting;
use Notur\NoturServiceProvider;
use Orchestra\Testbench\TestCase;

class ExtensionApiControllerTest extends TestCase
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_extensions_returns_enabled_extension_metadata_with_slots(): void
    {
        $extension = new class implements ExtensionInterface {
            public function getId(): string { return 'acme/test'; }
            public function getName(): string { return 'Acme Test'; }
            public function getVersion(): string { return '1.2.3'; }
            public function register(): void {}
            public function boot(): void {}
            public function getBasePath(): string { return '/tmp/acme-test'; }
        };

        $manifest = ExtensionManifest::fromArray([
            'id' => 'acme/test',
            'name' => 'Acme Test',
            'version' => '1.2.3',
            'description' => 'Public description',
        ]);

        $manager = Mockery::mock(ExtensionManager::class);
        $manager->shouldReceive('getFrontendSlots')->once()->andReturn([
            'acme/test' => [
                'dashboard.widgets' => ['component' => 'Widget'],
            ],
        ]);
        $manager->shouldReceive('all')->once()->andReturn(['acme/test' => $extension]);
        $manager->shouldReceive('getManifest')->with('acme/test')->once()->andReturn($manifest);

        $response = (new ExtensionApiController($manager))->extensions();
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('acme/test', $payload['data'][0]['id']);
        $this->assertSame('Acme Test', $payload['data'][0]['name']);
        $this->assertSame('1.2.3', $payload['data'][0]['version']);
        $this->assertSame('Public description', $payload['data'][0]['description']);
        $this->assertSame(['dashboard.widgets' => ['component' => 'Widget']], $payload['data'][0]['slots']);
    }

    public function test_settings_returns_only_public_settings_and_defaults(): void
    {
        ExtensionSetting::setValue('acme/test', 'public_limit', 25);
        ExtensionSetting::setValue('acme/test', 'private_secret', 'stored-secret');

        $manifest = ExtensionManifest::fromArray([
            'id' => 'acme/test',
            'name' => 'Acme Test',
            'version' => '1.2.3',
            'admin' => [
                'settings' => [
                    'fields' => [
                        ['key' => 'public_limit', 'type' => 'number', 'public' => true, 'default' => 10],
                        ['key' => 'public_default', 'type' => 'text', 'public' => true, 'default' => 'fallback'],
                        ['key' => 'private_secret', 'type' => 'text', 'public' => false, 'default' => 'hidden'],
                    ],
                ],
            ],
        ]);

        $manager = Mockery::mock(ExtensionManager::class);
        $manager->shouldReceive('getManifest')->with('acme/test')->once()->andReturn($manifest);

        $response = (new ExtensionApiController($manager))->settings('acme/test');
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'public_limit' => 25,
            'public_default' => 'fallback',
        ], $payload['data']);
    }

    public function test_settings_returns_404_for_unknown_extension(): void
    {
        $manager = Mockery::mock(ExtensionManager::class);
        $manager->shouldReceive('getManifest')->with('acme/missing')->once()->andReturn(null);

        $response = (new ExtensionApiController($manager))->settings('acme/missing');
        $payload = $response->getData(true);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Extension not found.', $payload['message']);
    }
}
