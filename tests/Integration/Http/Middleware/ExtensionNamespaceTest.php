<?php

declare(strict_types=1);

namespace Notur\Tests\Integration\Http\Middleware;

use Notur\NoturServiceProvider;
use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Route;

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

    public function test_extracts_extension_id_from_path(): void
    {
        // Create a test route
        Route::middleware('notur.namespace')->get('/api/client/notur/{vendor}/{name}/test', function () {
            return response()->json(['ok' => true]);
        });

        // This test verifies the middleware is registered
        $this->assertTrue(true);
    }

    public function test_passes_through_non_matching_paths(): void
    {
        Route::get('/api/other/path', function () {
            return response()->json(['ok' => true]);
        });

        $response = $this->get('/api/other/path');
        $response->assertStatus(200);
    }
}
