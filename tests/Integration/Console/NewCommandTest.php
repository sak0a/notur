<?php

declare(strict_types=1);

namespace Notur\Tests\Integration\Console;

use Notur\NoturServiceProvider;
use Orchestra\Testbench\TestCase;

class NewCommandTest extends TestCase
{
    private string $workspace;

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
        $this->workspace = sys_get_temp_dir() . '/notur-new-command-' . uniqid('', true);
        mkdir($this->workspace, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->workspace);
        parent::tearDown();
    }

    public function test_scaffolded_frontend_uses_webpack_cli_scripts_and_required_dependencies(): void
    {
        $this->artisan('notur:new', [
            'id' => 'acme/hello-world',
            '--path' => $this->workspace,
            '--preset' => 'standard',
            '--no-interaction' => true,
        ])->assertExitCode(0);

        $packageJsonPath = $this->workspace . '/hello-world/package.json';
        $this->assertFileExists($packageJsonPath);

        $package = json_decode((string) file_get_contents($packageJsonPath), true);

        $this->assertSame('webpack-cli --mode production --config webpack.config.js', $package['scripts']['build'] ?? null);
        $this->assertSame('webpack-cli --mode development --watch --config webpack.config.js', $package['scripts']['dev'] ?? null);
        $this->assertArrayHasKey('css-loader', $package['devDependencies'] ?? []);
        $this->assertArrayHasKey('style-loader', $package['devDependencies'] ?? []);
    }

    public function test_minimal_preset_skips_frontend_scaffold(): void
    {
        $this->artisan('notur:new', [
            'id' => 'acme/minimal-ext',
            '--path' => $this->workspace,
            '--preset' => 'minimal',
            '--no-interaction' => true,
        ])->assertExitCode(0);

        $packageJsonPath = $this->workspace . '/minimal-ext/package.json';
        $this->assertFileDoesNotExist($packageJsonPath);
    }

    private function deleteDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }
}
