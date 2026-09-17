<?php

declare(strict_types=1);

namespace Notur\Tests\Integration;

use Notur\Contracts\ExtensionInterface;
use Notur\ExtensionManager;
use Notur\Models\InstalledExtension;
use Notur\NoturServiceProvider;
use Notur\PermissionBroker;
use Orchestra\Testbench\TestCase;

class ExtensionBootRecoveryTest extends TestCase
{
    private string $fixturePath;
    private string|false $previousSafeMode;

    protected function getApplicationBasePath()
    {
        return $this->fixturePath;
    }

    protected function getPackageProviders($app): array
    {
        return [NoturServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Simulate cached config; the process environment must still win when set.
        $app['config']->set('notur.safe_mode', $this->name() === 'test_config_safe_mode_skips_extensions');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        $this->fixturePath = sys_get_temp_dir() . '/notur-boot-recovery-' . uniqid('', true);
        mkdir($this->fixturePath . '/notur/extensions', 0755, true);
        mkdir($this->fixturePath . '/bootstrap/cache', 0755, true);
        mkdir($this->fixturePath . '/storage/framework/views', 0755, true);
        mkdir($this->fixturePath . '/storage/logs', 0755, true);
        $this->previousSafeMode = getenv('NOTUR_SAFE_MODE');
        if ($this->name() === 'test_config_safe_mode_skips_extensions') {
            putenv('NOTUR_SAFE_MODE');
        } else {
            putenv('NOTUR_SAFE_MODE=' . ($this->name() === 'test_safe_mode_allows_recovery_cli' ? '1' : '0'));
        }

        $this->fixture('acme/fails', "entrypoint: '" . FailingBootExtension::class . "'\nbackend:\n  permissions:\n    - acme.fails.read");
        $this->fixture('acme/dependent', "dependencies:\n  acme/fails: '^1.0'\nentrypoint: '" . CountingExtension::class . "'");
        $this->fixture('acme/transitive', "dependencies:\n  acme/dependent: '^1.0'\nentrypoint: '" . CountingExtension::class . "'");
        $this->fixture('acme/independent', "entrypoint: '" . CountingExtension::class . "'");
        $this->fixture('acme/missing', "entrypoint: 'Acme\\Missing\\Entrypoint'");
        $this->fixture('acme/bad-manifest', 'name: Missing ID');
        $this->fixture('acme/cycle-a', "dependencies:\n  acme/cycle-b: '^1.0'");
        $this->fixture('acme/cycle-b', "dependencies:\n  acme/cycle-a: '^1.0'");
        file_put_contents($this->fixturePath . '/notur/extensions.json', json_encode([
            'extensions' => array_fill_keys([
                'acme/fails', 'acme/dependent', 'acme/transitive', 'acme/independent',
                'acme/missing', 'acme/bad-manifest', 'acme/cycle-a', 'acme/cycle-b',
            ], ['version' => '1.0.0', 'enabled' => true]),
        ], JSON_THROW_ON_ERROR));
        if ($this->name() === 'test_corrupt_master_manifest_does_not_block_startup') {
            file_put_contents($this->fixturePath . '/notur/extensions.json', '{broken json');
        }

        CountingExtension::$bootCount = 0;
        FailingBootExtension::$bootCount = 0;
        parent::setUp(); // Boots NoturServiceProvider against the fixtures above.
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if ($this->previousSafeMode === false) {
            putenv('NOTUR_SAFE_MODE');
        } else {
            putenv('NOTUR_SAFE_MODE=' . $this->previousSafeMode);
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->fixturePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->fixturePath);
    }

    public function test_provider_startup_isolates_failures_and_skips_dependents(): void
    {
        $manager = $this->app->make(ExtensionManager::class);
        $this->assertSame(1, FailingBootExtension::$bootCount);
        $this->assertSame(1, CountingExtension::$bootCount);
        $this->assertSame(['acme/independent'], array_keys($manager->all()));
        $this->assertSame('boot', $manager->getBootFailures()['acme/fails']['stage']);
        $this->assertSame('skipped', $manager->getBootFailures()['acme/dependent']['status']);
        $this->assertSame('acme/fails', $manager->getBootFailures()['acme/dependent']['dependency']);
        $this->assertSame('acme/dependent', $manager->getBootFailures()['acme/transitive']['dependency']);
        $this->assertSame('entrypoint', $manager->getBootFailures()['acme/missing']['stage']);
        $this->assertSame('manifest', $manager->getBootFailures()['acme/bad-manifest']['stage']);
        $this->assertSame('dependency resolution', $manager->getBootFailures()['acme/cycle-a']['stage']);
        $this->assertSame('dependency resolution', $manager->getBootFailures()['acme/cycle-b']['stage']);
        $this->assertSame([], $this->app->make(PermissionBroker::class)->getExtensionPermissions('acme/fails'));

        $manager->boot();
        $this->assertSame(1, FailingBootExtension::$bootCount, 'Boot is idempotent after a failure.');
        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('notur:status', ['--json' => true]));
        $status = json_decode(\Illuminate\Support\Facades\Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('skipped', $status['boot_failures']['acme/dependent']['status']);
        $this->assertSame('boot', $status['boot_failures']['acme/fails']['stage']);
        $this->assertSame(\RuntimeException::class, $status['boot_failures']['acme/fails']['exception']);
        $this->assertFalse($status['safe_mode']);
        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('notur:status', ['--extensions' => true]));
        $output = \Illuminate\Support\Facades\Artisan::output();
        $this->assertStringContainsString('Extension Boot Diagnostics', $output);
        $this->assertStringContainsString('acme/dependent', $output);
        \Illuminate\Support\Facades\Route::get('/panel-probe', static fn () => 'ready');
        $this->get('/panel-probe')->assertOk()->assertSee('ready');
    }

    public function test_safe_mode_allows_recovery_cli(): void
    {
        $manager = $this->app->make(ExtensionManager::class);
        $this->assertTrue($manager->isSafeMode());
        $this->assertSame([], $manager->all());
        $this->assertNull($manager->getManifest('acme/fails'));
        $this->assertSame([], $manager->getBootFailures());
        $this->assertSame(0, FailingBootExtension::$bootCount);
        $this->artisan('notur:status', ['--json' => true])
            ->expectsOutputToContain('"safe_mode": true')
            ->assertExitCode(0);

        InstalledExtension::create([
            'extension_id' => 'acme/fails',
            'name' => 'Failing extension',
            'version' => '1.0.0',
            'enabled' => true,
            'manifest' => ['id' => 'acme/fails'],
        ]);
        $this->artisan('notur:disable', ['extension' => 'acme/fails'])
            ->expectsOutput("Extension 'acme/fails' has been disabled.")
            ->assertExitCode(0);
        $this->assertFalse(InstalledExtension::where('extension_id', 'acme/fails')->first()->enabled);
        $manifest = json_decode(file_get_contents($this->fixturePath . '/notur/extensions.json'), true);
        $this->assertFalse($manifest['extensions']['acme/fails']['enabled']);
        $this->assertSame(0, FailingBootExtension::$bootCount);
    }

    public function test_config_safe_mode_skips_extensions(): void
    {
        $manager = $this->app->make(ExtensionManager::class);
        $this->assertTrue($manager->isSafeMode());
        $this->assertSame([], $manager->all());
        $this->assertSame(0, FailingBootExtension::$bootCount);
    }

    public function test_corrupt_master_manifest_does_not_block_startup(): void
    {
        $manager = $this->app->make(ExtensionManager::class);
        $this->assertSame([], $manager->all());
        $this->assertSame('discovery', $manager->getBootFailures()['@manifest']['stage']);
        $this->assertSame(0, FailingBootExtension::$bootCount);
        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('notur:status', ['--json' => true]));
        $status = json_decode(\Illuminate\Support\Facades\Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('@manifest', $status['boot_failures']);
    }

    private function fixture(string $id, string $extra): void
    {
        $path = $this->fixturePath . '/notur/extensions/' . $id;
        mkdir($path, 0755, true);
        file_put_contents($path . '/extension.yaml', "id: {$id}\nname: Test {$id}\nversion: 1.0.0\n{$extra}\n");
    }
}

class CountingExtension implements ExtensionInterface
{
    public static int $bootCount = 0;

    public function getId(): string { return 'acme/independent'; }
    public function getName(): string { return 'Counting'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getBasePath(): string { return ''; }
    public function register(): void {}
    public function boot(): void { self::$bootCount++; }
}

class FailingBootExtension extends CountingExtension
{
    public static int $bootCount = 0;

    public function boot(): void
    {
        self::$bootCount++;
        throw new \RuntimeException('Fixture boot failure');
    }
}
