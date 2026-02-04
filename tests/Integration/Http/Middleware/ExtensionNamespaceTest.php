<?php

declare(strict_types=1);

namespace Notur\Tests\Integration\Http\Middleware;

use Notur\NoturServiceProvider;
use Notur\Http\Middleware\ExtensionNamespace;
use Notur\ExtensionManager;
use Orchestra\Testbench\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mockery;

class ExtensionNamespaceTest extends TestCase
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
        $this->loadMigrationsFrom(__DIR__ . '/../../../../database/migrations');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_non_notur_paths_pass_without_extension_context(): void
    {
        $manager = Mockery::mock(ExtensionManager::class);
        // isEnabled should not be called for non-matching paths
        $manager->shouldNotReceive('isEnabled');

        $middleware = new ExtensionNamespace($manager);

        $request = Request::create('/api/test', 'GET');
        $response = $middleware->handle($request, function ($req) {
            return new Response('ok');
        });

        $this->assertEquals('ok', $response->getContent());
        $this->assertNull($request->attributes->get('notur.extension_id'));
    }

    public function test_sets_extension_id_for_enabled_extension(): void
    {
        $manager = Mockery::mock(ExtensionManager::class);
        $manager->shouldReceive('isEnabled')
            ->with('acme/test')
            ->once()
            ->andReturn(true);

        $middleware = new ExtensionNamespace($manager);

        $request = Request::create('/api/client/notur/acme/test/endpoint', 'GET');
        $response = $middleware->handle($request, function ($req) {
            return new Response('ok');
        });

        $this->assertEquals('ok', $response->getContent());
        $this->assertEquals('acme/test', $request->attributes->get('notur.extension_id'));
    }

    public function test_aborts_for_disabled_extension(): void
    {
        $manager = Mockery::mock(ExtensionManager::class);
        $manager->shouldReceive('isEnabled')
            ->with('acme/disabled')
            ->once()
            ->andReturn(false);

        $middleware = new ExtensionNamespace($manager);

        $request = Request::create('/api/client/notur/acme/disabled/endpoint', 'GET');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage("Extension 'acme/disabled' is not enabled.");

        $middleware->handle($request, function ($req) {
            return new Response('ok');
        });
    }
}
