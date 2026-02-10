<?php

declare(strict_types=1);

namespace Notur\Tests\Integration;

use Illuminate\Support\Facades\Http;
use Notur\NoturServiceProvider;
use Notur\Support\SystemDiagnostics;
use Orchestra\Testbench\TestCase;

class SystemDiagnosticsTest extends TestCase
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
        $app['config']->set('cache.default', 'array');
    }

    public function test_summary_contains_framework_runtime_hardware_and_update_sections(): void
    {
        Http::fake([
            'repo.packagist.org/*' => Http::response([
                'packages' => [
                    'notur/notur' => [
                        ['version' => '1.2.2', 'version_normalized' => '1.2.2.0'],
                        ['version' => '9.9.9', 'version_normalized' => '9.9.9.0'],
                    ],
                ],
            ], 200),
        ]);

        $diagnostics = $this->app->make(SystemDiagnostics::class);
        $summary = $diagnostics->summary();

        $this->assertArrayHasKey('framework', $summary);
        $this->assertArrayHasKey('runtime', $summary);
        $this->assertArrayHasKey('hardware', $summary);
        $this->assertArrayHasKey('package_manager', $summary);
        $this->assertArrayHasKey('updates', $summary);
        $this->assertArrayHasKey('notur', $summary['updates']);
        $this->assertSame('9.9.9', $summary['updates']['notur']['latest_version']);
    }

    public function test_detect_package_manager_uses_lockfile_even_if_command_is_missing(): void
    {
        $tmpDir = sys_get_temp_dir() . '/notur-diagnostics-' . uniqid('', true);
        mkdir($tmpDir, 0755, true);
        file_put_contents($tmpDir . '/pnpm-lock.yaml', "lockfileVersion: '9.0'");

        $diagnostics = $this->app->make(SystemDiagnostics::class);
        $manager = $diagnostics->detectPackageManager($tmpDir);

        $this->assertSame('pnpm', $manager['manager']);
        $this->assertSame('pnpm-lock.yaml', $manager['lockfile']);
        $this->assertSame('lockfile', $manager['source']);

        @unlink($tmpDir . '/pnpm-lock.yaml');
        @rmdir($tmpDir);
    }

    public function test_build_notur_update_info_marks_update_available_when_latest_is_newer(): void
    {
        $diagnostics = $this->app->make(SystemDiagnostics::class);

        $updateInfo = $diagnostics->buildNoturUpdateInfo('1.0.0', '1.1.0');

        $this->assertSame('update_available', $updateInfo['status']);
        $this->assertTrue($updateInfo['update_available']);
    }
}
