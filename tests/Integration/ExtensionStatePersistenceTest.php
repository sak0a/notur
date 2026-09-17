<?php

declare(strict_types=1);

namespace Notur\Tests\Integration;

use Illuminate\Support\Facades\DB;
use Notur\DependencyResolver;
use Notur\ExtensionManager;
use Notur\ExtensionManifest;
use Notur\Models\InstalledExtension;
use Notur\NoturServiceProvider;
use Notur\PermissionBroker;
use Orchestra\Testbench\TestCase;

class ExtensionStatePersistenceTest extends TestCase
{
    private string $dir;
    private ExtensionManager $manager;

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
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->dir = sys_get_temp_dir() . '/notur-state-integration-' . bin2hex(random_bytes(8));
        mkdir($this->dir);
        $path = $this->dir . '/extensions.json';
        $this->manager = new class($this->app, new DependencyResolver(), new PermissionBroker(), $path) extends ExtensionManager {
            public function __construct($app, $resolver, $broker, private readonly string $statePath)
            {
                parent::__construct($app, $resolver, $broker);
            }

            public function getManifestPath(): string
            {
                return $this->statePath;
            }

            public function getExtensionsPath(): string
            {
                return dirname($this->statePath) . '/extensions';
            }
        };
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->dir);
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $path) {
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    public function test_register_toggle_and_unregister_project_to_database(): void
    {
        $manifest = ExtensionManifest::fromArray(['id' => 'acme/one', 'name' => 'One', 'version' => '1.0.0']);
        $this->manager->registerExtension('acme/one', '1.0.0', $manifest);
        $this->manager->registerExtension('acme/two', '2.0.0');
        $this->manager->disable('acme/one');

        $one = InstalledExtension::where('extension_id', 'acme/one')->firstOrFail();
        $this->assertFalse($one->enabled);
        $this->assertSame('1.0.0', $one->version);
        $this->assertSame('One', $one->name);
        $this->manager->unregisterExtension('acme/one');

        $this->assertNull(InstalledExtension::where('extension_id', 'acme/one')->first());
        $this->assertNotNull(InstalledExtension::where('extension_id', 'acme/two')->first());
        $state = json_decode(file_get_contents($this->dir . '/extensions.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayNotHasKey('acme/one', $state['extensions']);
    }

    public function test_boot_reconciles_interrupted_database_update(): void
    {
        $this->manager->registerExtension('acme/one', '1.0.0');
        DB::statement("CREATE TRIGGER reject_extension_update BEFORE UPDATE ON notur_extensions BEGIN SELECT RAISE(FAIL, 'simulated failure'); END");

        try {
            $this->manager->disable('acme/one');
            $this->fail('Database update should fail.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertTrue(InstalledExtension::where('extension_id', 'acme/one')->firstOrFail()->enabled);
            $state = json_decode(file_get_contents($this->dir . '/extensions.json'), true, 512, JSON_THROW_ON_ERROR);
            $this->assertFalse($state['extensions']['acme/one']['enabled']);
        }

        DB::statement('DROP TRIGGER reject_extension_update');
        $this->manager->boot();
        $this->assertFalse(InstalledExtension::where('extension_id', 'acme/one')->firstOrFail()->enabled);
    }

    public function test_reconciliation_restores_missing_rows_and_removes_stale_rows(): void
    {
        $this->manager->registerExtension('acme/one', '1.0.0');
        InstalledExtension::where('extension_id', 'acme/one')->delete();
        InstalledExtension::create([
            'extension_id' => 'acme/stale', 'name' => 'Stale', 'version' => '1.0.0', 'enabled' => true,
        ]);

        $this->manager->reconcileState();

        $this->assertNotNull(InstalledExtension::where('extension_id', 'acme/one')->first());
        $this->assertNull(InstalledExtension::where('extension_id', 'acme/stale')->first());
    }

    public function test_failed_upgrade_projection_recovers_metadata_without_changing_remote_push_tracking(): void
    {
        $extensionPath = $this->manager->getExtensionsPath() . '/acme/one';
        mkdir($extensionPath, 0755, true);
        file_put_contents($extensionPath . '/extension.yaml', "id: acme/one\nname: Original\nversion: 1.0.0\ndependencies: {}\n");
        $this->manager->registerExtension('acme/one', '1.0.0', ExtensionManifest::load($extensionPath));

        $record = InstalledExtension::where('extension_id', 'acme/one')->firstOrFail();
        $record->update([
            'source' => 'remote_push',
            'pushed_via_key_id' => 73,
            'last_pushed_at' => '2026-01-02 03:04:05',
            'last_push_error' => 'previous attempt',
            'package_checksum' => str_repeat('a', 64),
            'package_size' => 4096,
        ]);
        $trackingColumns = ['source', 'pushed_via_key_id', 'last_pushed_at', 'last_push_error', 'package_checksum', 'package_size'];
        $tracking = array_intersect_key($record->getRawOriginal(), array_flip($trackingColumns));

        file_put_contents($extensionPath . '/extension.yaml', "id: acme/one\nname: Upgraded\nversion: 2.0.0\ndependencies: {}\n");
        $upgrade = ExtensionManifest::load($extensionPath);
        DB::statement("CREATE TRIGGER reject_upgrade BEFORE UPDATE ON notur_extensions BEGIN SELECT RAISE(FAIL, 'simulated failure'); END");

        try {
            $this->manager->registerExtension('acme/one', '2.0.0', $upgrade);
            $this->fail('Database update should fail.');
        } catch (\Illuminate\Database\QueryException) {
            $state = json_decode(file_get_contents($this->dir . '/extensions.json'), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('2.0.0', $state['extensions']['acme/one']['version']);
            $this->assertSame('1.0.0', InstalledExtension::where('extension_id', 'acme/one')->firstOrFail()->version);
        } finally {
            DB::statement('DROP TRIGGER reject_upgrade');
        }

        $this->manager->reconcileState();
        $recovered = InstalledExtension::where('extension_id', 'acme/one')->firstOrFail();
        $this->assertSame('2.0.0', $recovered->version);
        $this->assertSame('Upgraded', $recovered->name);
        $this->assertSame($upgrade->getRaw(), $recovered->manifest);
        $this->assertSame($tracking, array_intersect_key($recovered->getRawOriginal(), array_flip($trackingColumns)));
    }

    public function test_reconciliation_does_not_project_metadata_from_uncommitted_install_files(): void
    {
        $extensionPath = $this->manager->getExtensionsPath() . '/acme/one';
        mkdir($extensionPath, 0755, true);
        file_put_contents($extensionPath . '/extension.yaml', "id: acme/one\nname: Original\nversion: 1.0.0\n");
        $original = ExtensionManifest::load($extensionPath);
        $this->manager->registerExtension('acme/one', '1.0.0', $original, false);

        // Replacement files are visible while migrations are still running.
        file_put_contents($extensionPath . '/extension.yaml', "id: acme/one\nname: Pending\nversion: 2.0.0\n");
        $this->manager->reconcileState();

        $record = InstalledExtension::where('extension_id', 'acme/one')->firstOrFail();
        $this->assertSame('1.0.0', $record->version);
        $this->assertSame('Original', $record->name);
        $this->assertSame($original->getRaw(), $record->manifest);
        $this->assertFalse($record->enabled);
    }

    public function test_reconciling_unchanged_state_does_not_write_database(): void
    {
        $extensionPath = $this->manager->getExtensionsPath() . '/acme/one';
        mkdir($extensionPath, 0755, true);
        file_put_contents($extensionPath . '/extension.yaml', "id: acme/one\nname: One\nversion: 1.0.0\n");
        $this->manager->registerExtension('acme/one', '1.0.0', ExtensionManifest::load($extensionPath));

        $connection = DB::connection();
        $connection->enableQueryLog();
        $connection->flushQueryLog();
        try {
            $this->manager->reconcileState();
            $writes = array_filter($connection->getQueryLog(), static fn (array $query): bool =>
                preg_match('/^\s*(?:update|delete|insert)\b.*notur_extensions/i', $query['query']) === 1);
            $this->assertSame([], array_values($writes));
        } finally {
            $connection->disableQueryLog();
        }
    }

    public function test_safe_mode_does_not_access_or_reconcile_state(): void
    {
        file_put_contents($this->dir . '/extensions.json', '{broken');
        config(['notur.safe_mode' => true]);

        $this->manager->boot();

        $this->assertSame([], $this->manager->getBootFailures());
        $this->assertFileDoesNotExist($this->dir . '/extensions.json.lock');
        $this->assertSame('{broken', file_get_contents($this->dir . '/extensions.json'));
    }

    public function test_reading_installed_entry_does_not_project_stale_database(): void
    {
        $this->manager->registerExtension('acme/one', '1.0.0', null, false);
        InstalledExtension::where('extension_id', 'acme/one')->update(['enabled' => true]);
        $before = file_get_contents($this->dir . '/extensions.json');

        $entry = $this->manager->getInstalledState('acme/one');

        $this->assertFalse($entry['enabled']);
        $this->assertTrue(InstalledExtension::where('extension_id', 'acme/one')->firstOrFail()->enabled);
        $this->assertSame($before, file_get_contents($this->dir . '/extensions.json'));
        $this->assertNull($this->manager->getInstalledState('acme/missing'));
    }

    public function test_initial_boot_without_manifest_creates_no_state_files(): void
    {
        $this->manager->boot();
        $this->assertFileDoesNotExist($this->dir . '/extensions.json');
        $this->assertFileDoesNotExist($this->dir . '/extensions.json.lock');
        $this->assertDirectoryDoesNotExist($this->manager->getExtensionsPath());
    }
}
