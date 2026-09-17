<?php

declare(strict_types=1);

namespace Notur\Tests\Integration\Console;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Event;
use Mockery;
use Notur\Events\ExtensionUpdated;
use Notur\Models\InstalledExtension;
use Notur\NoturServiceProvider;
use Notur\Support\ExtensionPath;
use Notur\Support\NoturArchive;
use Notur\Support\RegistryClient;
use Orchestra\Testbench\TestCase;

class InstallUpdateLifecycleTest extends TestCase
{
    private string $scratch;
    private string $relativePath;

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
        $this->relativePath = 'notur-install-test-' . bin2hex(random_bytes(6)) . '/extensions';
        config(['notur.extensions_path' => $this->relativePath]);
        $this->scratch = sys_get_temp_dir() . '/notur-install-test-' . bin2hex(random_bytes(6));
        mkdir($this->scratch, 0755, true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        $this->removeTree($this->scratch);
        $this->removeTree(base_path(dirname($this->relativePath)));
        $this->removeTree(public_path(dirname($this->relativePath)));
        parent::tearDown();
    }

    public function test_upgrade_keeps_disabled_state_and_replaces_old_public_assets(): void
    {
        $this->installed('acme/demo', false);
        $archive = $this->archive('acme/demo', '2.0.0', 'frontend/new.js', 'new asset');

        $this->artisan('notur:add', ['extension' => $archive, '--force' => true])
            ->expectsOutput("Extension 'acme/demo' v2.0.0 installed and disabled.")
            ->assertExitCode(0);

        $this->assertSame('new code', file_get_contents(ExtensionPath::base('acme/demo') . '/code.txt'));
        $this->assertFileDoesNotExist(ExtensionPath::public('acme/demo') . '/frontend/old.js');
        $this->assertSame('new asset', file_get_contents(ExtensionPath::public('acme/demo') . '/frontend/new.js'));
        $this->assertDatabaseHas('notur_extensions', [
            'extension_id' => 'acme/demo', 'version' => '2.0.0', 'enabled' => 0,
        ]);
        $manifest = json_decode(file_get_contents(ExtensionPath::manifest()), true);
        $this->assertFalse($manifest['extensions']['acme/demo']['enabled']);
    }

    public function test_missing_declared_asset_fails_before_replacing_either_tree(): void
    {
        $this->installed('acme/demo', true);
        $archive = $this->archive('acme/demo', '2.0.0', 'frontend/missing.js');

        $this->artisan('notur:add', ['extension' => $archive, '--force' => true])
            ->expectsOutputToContain('Declared frontend asset is missing')
            ->assertExitCode(1);

        $this->assertOldInstallIntact('acme/demo');
        $this->assertNoStagingDirectories('acme/demo');
    }

    public function test_failed_migration_restores_files_and_assets_and_reports_persistent_database_changes(): void
    {
        $this->installed('acme/demo', true);
        $archive = $this->archive('acme/demo', '2.0.0', 'frontend/new.js', 'new asset', true);

        $this->artisan('notur:add', ['extension' => $archive, '--force' => true])
            ->expectsOutputToContain('Installation failed: migration failed')
            ->expectsOutputToContain('Completed migrations may have changed the database')
            ->assertExitCode(1);

        $this->assertOldInstallIntact('acme/demo');
        $this->assertNoStagingDirectories('acme/demo');
        $this->assertTrue(Schema::hasTable('install_lifecycle_marker'));
        $this->assertDatabaseHas('notur_migrations', [
            'extension_id' => 'acme/demo',
            'migration' => '2024_01_01_000001_create_marker',
        ]);
    }

    public function test_failed_first_install_leaves_no_live_files_or_registration(): void
    {
        $archive = $this->archive('acme/demo', '1.0.0', 'frontend/new.js', 'new asset', true);

        $this->artisan('notur:add', ['extension' => $archive])
            ->expectsOutputToContain('Installation failed: migration failed')
            ->assertExitCode(1);

        $this->assertDirectoryDoesNotExist(ExtensionPath::base('acme/demo'));
        $this->assertDirectoryDoesNotExist(ExtensionPath::public('acme/demo'));
        $this->assertDatabaseMissing('notur_extensions', ['extension_id' => 'acme/demo']);
        $this->assertNoStagingDirectories('acme/demo');
    }

    public function test_failed_public_tree_swap_restores_prior_code_and_leaves_public_path_untouched(): void
    {
        $this->installed('acme/demo', true);
        $publicPath = ExtensionPath::public('acme/demo');
        $this->removeTree($publicPath);
        file_put_contents($publicPath, 'public path blocker');
        $archive = $this->archive('acme/demo', '2.0.0', 'frontend/new.js', 'new asset');

        $this->artisan('notur:add', ['extension' => $archive, '--force' => true])
            ->expectsOutputToContain('Installation failed:')
            ->assertExitCode(1);

        $this->assertSame('old code', file_get_contents(ExtensionPath::base('acme/demo') . '/code.txt'));
        $this->assertSame('public path blocker', file_get_contents($publicPath));
        $this->assertDatabaseHas('notur_extensions', ['extension_id' => 'acme/demo', 'version' => '1.0.0']);
        $this->assertNoStagingDirectories('acme/demo');
        unlink($publicPath);
    }

    public function test_registration_failure_restores_files_manifest_and_database(): void
    {
        $this->installed('acme/demo', false);
        $archive = $this->archive('acme/demo', '2.0.0', 'frontend/new.js', 'new asset');
        InstalledExtension::saving(function (InstalledExtension $record): void {
            if ($record->version === '2.0.0') {
                throw new \RuntimeException('registration denied');
            }
        });

        $this->artisan('notur:add', ['extension' => $archive, '--force' => true])
            ->expectsOutputToContain('Installation failed: registration denied')
            ->assertExitCode(1);

        $this->assertOldInstallIntact('acme/demo');
        $this->assertNoStagingDirectories('acme/demo');
        $this->assertDatabaseHas('notur_extensions', ['extension_id' => 'acme/demo', 'enabled' => 0]);
        $manifest = json_decode(file_get_contents(ExtensionPath::manifest()), true);
        $this->assertFalse($manifest['extensions']['acme/demo']['enabled']);
    }

    public function test_bulk_update_continues_after_one_failed_install_and_returns_failure(): void
    {
        $this->installed('acme/first', true);
        $this->installed('acme/second', true);
        $archive = $this->archive('acme/second', '2.0.0', 'frontend/new.js', 'new asset');

        $registry = Mockery::mock(RegistryClient::class);
        $registry->shouldReceive('getExtension')->andReturn(['latest_version' => '2.0.0']);
        $registry->shouldReceive('download')->twice()->andReturnUsing(
            function (string $id, string $version, string $destination) use ($archive): void {
                if ($id === 'acme/first') {
                    throw new \RuntimeException('registry unavailable');
                }
                copy($archive, $destination);
            },
        );
        $registry->shouldReceive('getExpectedArchiveChecksum')->once()->andReturn(null);
        $this->app->instance(RegistryClient::class, $registry);

        $this->artisan('notur:update')
            ->expectsConfirmation('Update all?', 'yes')
            ->expectsOutputToContain('Failed to update: acme/first')
            ->assertExitCode(1);

        $this->assertDatabaseHas('notur_extensions', ['extension_id' => 'acme/first', 'version' => '1.0.0']);
        $this->assertDatabaseHas('notur_extensions', ['extension_id' => 'acme/second', 'version' => '2.0.0']);
    }

    public function test_bulk_update_catches_child_command_exception_and_continues(): void
    {
        $this->installed('acme/first', true);
        $this->installed('acme/second', true);
        $archives = [
            'acme/first' => $this->archive('acme/first', '2.0.0', 'frontend/new.js', 'first asset'),
            'acme/second' => $this->archive('acme/second', '2.0.0', 'frontend/new.js', 'second asset'),
        ];
        Event::listen(ExtensionUpdated::class, function (ExtensionUpdated $event): void {
            if ($event->extensionId === 'acme/first') {
                throw new \RuntimeException('listener failed after install');
            }
        });

        $registry = Mockery::mock(RegistryClient::class);
        $registry->shouldReceive('getExtension')->andReturn(['latest_version' => '2.0.0']);
        $registry->shouldReceive('download')->twice()->andReturnUsing(
            function (string $id, string $version, string $destination) use ($archives): void {
                copy($archives[$id], $destination);
            },
        );
        $registry->shouldReceive('getExpectedArchiveChecksum')->twice()->andReturn(null);
        $this->app->instance(RegistryClient::class, $registry);

        $this->artisan('notur:update')
            ->expectsConfirmation('Update all?', 'yes')
            ->expectsOutputToContain("Update failed for 'acme/first': listener failed after install")
            ->expectsOutputToContain('Failed to update: acme/first')
            ->assertExitCode(1);

        $this->assertDatabaseHas('notur_extensions', ['extension_id' => 'acme/first', 'version' => '2.0.0']);
        $this->assertDatabaseHas('notur_extensions', ['extension_id' => 'acme/second', 'version' => '2.0.0']);
        $this->assertSame('second asset', file_get_contents(ExtensionPath::public('acme/second') . '/frontend/new.js'));
    }

    private function installed(string $id, bool $enabled): void
    {
        $path = ExtensionPath::base($id);
        $public = ExtensionPath::public($id);
        mkdir($path, 0755, true);
        mkdir($public . '/frontend', 0755, true);
        file_put_contents($path . '/code.txt', 'old code');
        file_put_contents($path . '/extension.yaml', "id: {$id}\nname: Demo\nversion: 1.0.0\n");
        file_put_contents($public . '/frontend/old.js', 'old asset');
        InstalledExtension::create([
            'extension_id' => $id,
            'name' => 'Demo',
            'version' => '1.0.0',
            'enabled' => $enabled,
            'manifest' => ['id' => $id, 'version' => '1.0.0'],
        ]);
        $manifestPath = ExtensionPath::manifest();
        $manifest = is_file($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : ['extensions' => []];
        $manifest['extensions'][$id] = ['version' => '1.0.0', 'enabled' => $enabled];
        file_put_contents($manifestPath, json_encode($manifest));
    }

    private function archive(string $id, string $version, string $asset, ?string $assetContents = null, bool $failingMigration = false): string
    {
        $number = count(glob($this->scratch . '/package-*')) + 1;
        $source = $this->scratch . '/package-' . $number;
        mkdir($source, 0755, true);
        $yaml = "id: {$id}\nname: Demo\nversion: {$version}\nfrontend:\n  bundle: {$asset}\n";
        if ($failingMigration) {
            $yaml .= "backend:\n  migrations: database/migrations\n";
            mkdir($source . '/database/migrations', 0755, true);
            file_put_contents($source . '/database/migrations/2024_01_01_000001_create_marker.php', <<<'PHP'
<?php
return new class {
    public function up(): void { \Illuminate\Support\Facades\Schema::create('install_lifecycle_marker', function (\Illuminate\Database\Schema\Blueprint $table): void { $table->id(); }); }
};
PHP);
            file_put_contents($source . '/database/migrations/2024_01_01_000002_fail.php', <<<'PHP'
<?php
return new class {
    public function up(): void { throw new \RuntimeException('migration failed'); }
};
PHP);
        }
        file_put_contents($source . '/extension.yaml', $yaml);
        file_put_contents($source . '/code.txt', 'new code');
        if ($assetContents !== null) {
            mkdir($source . '/frontend', 0755, true);
            file_put_contents($source . '/' . $asset, $assetContents);
        }
        $archive = $this->scratch . '/package-' . $number . '.notur';
        NoturArchive::pack($source, $archive);
        return $archive;
    }

    private function assertOldInstallIntact(string $id): void
    {
        $this->assertSame('old code', file_get_contents(ExtensionPath::base($id) . '/code.txt'));
        $this->assertSame('old asset', file_get_contents(ExtensionPath::public($id) . '/frontend/old.js'));
        $this->assertDatabaseHas('notur_extensions', ['extension_id' => $id, 'version' => '1.0.0']);
        $manifest = json_decode(file_get_contents(ExtensionPath::manifest()), true);
        $this->assertSame('1.0.0', $manifest['extensions'][$id]['version']);
    }

    private function assertNoStagingDirectories(string $id): void
    {
        $this->assertSame([], glob(ExtensionPath::base($id) . '.stage-*'));
        $this->assertSame([], glob(ExtensionPath::base($id) . '.backup-*'));
        $this->assertSame([], glob(ExtensionPath::public($id) . '.stage-*'));
        $this->assertSame([], glob(ExtensionPath::public($id) . '.backup-*'));
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }
}
