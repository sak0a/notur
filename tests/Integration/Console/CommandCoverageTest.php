<?php

declare(strict_types=1);

namespace Notur\Tests\Integration\Console;

use Mockery;
use Notur\Models\InstalledExtension;
use Notur\NoturServiceProvider;
use Notur\Support\RegistryClient;
use Notur\Support\SignatureVerifier;
use Orchestra\Testbench\TestCase;

class CommandCoverageTest extends TestCase
{
    private string $tempDir;

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

        $this->tempDir = sys_get_temp_dir() . '/notur-command-coverage-' . uniqid('', true);
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        $this->deleteDir($this->tempDir);
        parent::tearDown();
    }

    public function test_build_command_fails_for_missing_path(): void
    {
        $this->artisan('notur:build', ['path' => $this->tempDir . '/missing'])
            ->assertExitCode(1);
    }

    public function test_dev_command_fails_for_missing_path(): void
    {
        $this->artisan('notur:dev', ['path' => $this->tempDir . '/missing'])
            ->assertExitCode(1);
    }

    public function test_export_command_fails_for_missing_path(): void
    {
        $this->artisan('notur:export', ['path' => $this->tempDir . '/missing'])
            ->assertExitCode(1);
    }

    public function test_install_command_fails_when_registry_entry_missing(): void
    {
        $registry = Mockery::mock(RegistryClient::class);
        $registry->shouldReceive('getExtension')
            ->once()
            ->with('acme/missing')
            ->andReturn(null);
        $this->app->instance(RegistryClient::class, $registry);

        $this->artisan('notur:install', ['extension' => 'acme/missing'])
            ->assertExitCode(1);
    }

    public function test_keygen_command_uses_injected_verifier(): void
    {
        $verifier = Mockery::mock(SignatureVerifier::class);
        $verifier->shouldReceive('generateKeypair')
            ->once()
            ->andReturn([
                'public' => str_repeat('1', 64),
                'secret' => str_repeat('2', 128),
            ]);
        $this->app->instance(SignatureVerifier::class, $verifier);

        $this->artisan('notur:keygen')
            ->expectsOutputToContain('Ed25519 keypair generated successfully.')
            ->expectsOutputToContain('NOTUR_PUBLIC_KEY=')
            ->assertExitCode(0);
    }

    public function test_registry_status_fails_when_cache_path_not_configured(): void
    {
        config(['notur.registry_cache_path' => '']);

        $this->artisan('notur:registry:status')
            ->assertExitCode(1);
    }

    public function test_registry_status_json_reports_cache_metadata(): void
    {
        $cachePath = $this->tempDir . '/registry-cache.json';
        file_put_contents($cachePath, json_encode([
            'fetched_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'registry' => [
                'extensions' => [
                    ['id' => 'acme/demo'],
                ],
            ],
        ]));

        config([
            'notur.registry_cache_path' => $cachePath,
            'notur.registry_cache_ttl' => 3600,
        ]);

        $this->artisan('notur:registry:status', ['--json' => true])
            ->assertExitCode(0);
    }

    public function test_registry_sync_search_handles_empty_results(): void
    {
        $registry = Mockery::mock(RegistryClient::class);
        $registry->shouldReceive('search')
            ->once()
            ->with('nonexistent')
            ->andReturn([]);
        $this->app->instance(RegistryClient::class, $registry);

        $this->artisan('notur:registry:sync', ['--search' => 'nonexistent'])
            ->expectsOutput('No extensions found.')
            ->assertExitCode(0);
    }

    public function test_remove_command_fails_for_unknown_extension(): void
    {
        $this->artisan('notur:remove', ['extension' => 'acme/missing'])
            ->assertExitCode(1);
    }

    public function test_status_command_outputs_json(): void
    {
        InstalledExtension::create([
            'extension_id' => 'acme/health',
            'name' => 'Health Extension',
            'version' => '1.0.0',
            'enabled' => true,
            'manifest' => ['id' => 'acme/health'],
        ]);

        $this->artisan('notur:status', ['--json' => true])
            ->expectsOutputToContain('"extensions_total": 1')
            ->assertExitCode(0);
    }

    public function test_uninstall_command_can_be_cancelled_interactively(): void
    {
        $this->artisan('notur:uninstall')
            ->expectsConfirmation('Are you sure you want to uninstall Notur?', 'no')
            ->expectsOutput('Uninstall cancelled.')
            ->assertExitCode(0);
    }

    public function test_update_command_reports_empty_install_set(): void
    {
        $this->artisan('notur:update')
            ->expectsOutput('No extensions installed.')
            ->assertExitCode(0);
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
