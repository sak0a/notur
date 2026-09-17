<?php

declare(strict_types=1);

namespace Notur\Tests\Integration;

use Notur\DependencyResolver;
use Notur\Exceptions\DependencyResolutionException;
use Notur\ExtensionManager;
use Notur\ExtensionManifest;
use Notur\NoturServiceProvider;
use Notur\PermissionBroker;
use Notur\Models\InstalledExtension;
use Notur\Support\ExtensionPath;
use Notur\Support\NoturArchive;
use Orchestra\Testbench\TestCase;
use Symfony\Component\Yaml\Yaml;

class ExtensionDependencyLifecycleTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [NoturServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    protected function tearDown(): void
    {
        $this->deleteDir(base_path('notur'));
        parent::tearDown();
    }

    public function test_install_checks_missing_dependency_before_any_files_change(): void
    {
        $candidate = $this->manifest('acme/app', '1.0.0', ['acme/core' => '^1.0']);

        $this->expectException(DependencyResolutionException::class);
        $this->expectExceptionMessage("'acme/core' (^1.0), which is not installed");
        $this->manager()->assertCanInstall($candidate);
    }

    public function test_enable_checks_disabled_dependency_and_preserves_state(): void
    {
        $this->installFixture('acme/app', '1.0.0', ['acme/core' => '^1.0']);
        $this->installFixture('acme/core', '1.0.0');
        $this->writeMaster(['acme/app' => false, 'acme/core' => false]);

        try {
            $this->manager()->enable('acme/app');
            $this->fail('Expected dependency validation to reject enable.');
        } catch (DependencyResolutionException $e) {
            $this->assertStringContainsString("'acme/core' (^1.0), which is disabled", $e->getMessage());
        }

        $this->assertFalse($this->master()['acme/app']['enabled']);
    }

    public function test_update_cannot_break_an_enabled_reverse_dependent(): void
    {
        $this->installFixture('acme/app', '1.0.0', ['acme/core' => '^1.0']);
        $this->installFixture('acme/core', '1.5.0');
        $this->writeMaster(['acme/app' => true, 'acme/core' => true]);

        $this->expectException(DependencyResolutionException::class);
        $this->expectExceptionMessage("requires 'acme/core' ^1.0, but version 2.0.0 is installed");
        $this->manager()->assertCanInstall($this->manifest('acme/core', '2.0.0'));
    }

    public function test_registration_rechecks_dependencies_after_preflight(): void
    {
        $this->installFixture('acme/core', '1.0.0');
        $this->writeMaster(['acme/core' => true]);
        $manager = $this->manager();
        $candidate = $this->manifest('acme/app', '1.0.0', ['acme/core' => '^1.0']);

        $manager->assertCanInstall($candidate);
        $manager->disable('acme/core');

        try {
            $manager->registerExtension('acme/app', '1.0.0', $candidate);
            $this->fail('Expected locked registration to reject the stale preflight.');
        } catch (DependencyResolutionException $e) {
            $this->assertStringContainsString("'acme/core' (^1.0), which is disabled", $e->getMessage());
        }

        $this->assertArrayNotHasKey('acme/app', $this->master());
        $this->assertFalse($this->master()['acme/core']['enabled']);
    }

    public function test_unregister_rechecks_reverse_dependents_after_preflight(): void
    {
        $this->installFixture('acme/core', '1.0.0');
        $this->installFixture('acme/app', '1.0.0', ['acme/core' => '^1.0']);
        $this->writeMaster(['acme/core' => true]);
        $manager = $this->manager();

        $manager->assertCanRemove('acme/core');
        $manager->registerExtension('acme/app', '1.0.0', ExtensionManifest::load(ExtensionPath::base('acme/app')));

        try {
            $manager->unregisterExtension('acme/core');
            $this->fail('Expected locked removal to reject the new dependent.');
        } catch (DependencyResolutionException $e) {
            $this->assertStringContainsString("Extension 'acme/app' requires 'acme/core'", $e->getMessage());
        }

        $this->assertArrayHasKey('acme/core', $this->master());
    }

    public function test_disable_and_remove_cannot_break_an_enabled_reverse_dependent(): void
    {
        $this->installFixture('acme/app', '1.0.0', ['acme/core' => '^1.0']);
        $this->installFixture('acme/core', '1.5.0');
        $this->writeMaster(['acme/app' => true, 'acme/core' => true, 'acme/unreadable' => true]);
        $manager = $this->manager();

        foreach ([fn () => $manager->disable('acme/core'), fn () => $manager->assertCanRemove('acme/core'), fn () => $manager->unregisterExtension('acme/core')] as $operation) {
            try {
                $operation();
                $this->fail('Expected reverse dependency protection.');
            } catch (DependencyResolutionException $e) {
                $this->assertStringContainsString("Extension 'acme/app' requires 'acme/core'", $e->getMessage());
            }
        }

        $this->assertTrue($this->master()['acme/core']['enabled']);
        $this->assertFileExists(ExtensionPath::base('acme/core') . '/extension.yaml');
    }

    public function test_bad_extension_can_be_disabled_and_unregistered_despite_unrelated_broken_graphs(): void
    {
        $this->installFixture('acme/bad', '1.0.0', ['acme/absent' => '^1.0']);
        $this->installFixture('acme/invalid', '1.0.0', ['acme/other' => 'bad-constraint']);
        $this->installFixture('acme/cycle-a', '1.0.0', ['acme/cycle-b' => '^1.0']);
        $this->installFixture('acme/cycle-b', '1.0.0', ['acme/cycle-a' => '^1.0']);
        $this->installFixture('acme/empty-yaml', '1.0.0');
        $this->installFixture('acme/scalar-yaml', '1.0.0');
        $this->installFixture('acme/type-invalid-yaml', '1.0.0');
        file_put_contents(ExtensionPath::base('acme/empty-yaml') . '/extension.yaml', '');
        file_put_contents(ExtensionPath::base('acme/scalar-yaml') . '/extension.yaml', 'hello');
        file_put_contents(ExtensionPath::base('acme/type-invalid-yaml') . '/extension.yaml', 'id: []');
        $this->writeMaster([
            'acme/bad' => true,
            'acme/unreadable' => true,
            'acme/invalid' => true,
            'acme/cycle-a' => true,
            'acme/cycle-b' => true,
            'acme/empty-yaml' => true,
            'acme/scalar-yaml' => true,
            'acme/type-invalid-yaml' => true,
        ]);

        $manager = $this->manager();
        $manager->disable('acme/bad');
        $this->assertFalse($this->master()['acme/bad']['enabled']);
        $manager->unregisterExtension('acme/bad');
        $this->assertArrayNotHasKey('acme/bad', $this->master());
    }

    public function test_remove_command_cleans_unreadable_extension_despite_other_broken_extensions(): void
    {
        $this->installFixture('acme/invalid', '1.0.0', ['acme/absent' => 'bad-constraint']);
        $this->installFixture('acme/empty-yaml', '1.0.0');
        $this->installFixture('acme/scalar-yaml', '1.0.0');
        file_put_contents(ExtensionPath::base('acme/empty-yaml') . '/extension.yaml', '');
        file_put_contents(ExtensionPath::base('acme/scalar-yaml') . '/extension.yaml', 'hello');
        $this->writeMaster([
            'acme/unreadable' => true,
            'acme/invalid' => true,
            'acme/empty-yaml' => true,
            'acme/scalar-yaml' => true,
        ]);
        InstalledExtension::create([
            'extension_id' => 'acme/unreadable',
            'name' => 'Unreadable',
            'version' => '1.0.0',
            'enabled' => true,
            'manifest' => ['id' => 'acme/unreadable'],
        ]);

        $this->artisan('notur:remove', [
            'extension' => 'acme/unreadable',
            '--force' => true,
            '--keep-data' => true,
        ])->assertExitCode(0);

        $this->assertArrayNotHasKey('acme/unreadable', $this->master());
        $this->assertNull(InstalledExtension::where('extension_id', 'acme/unreadable')->first());
    }

    public function test_install_rejects_new_cycle(): void
    {
        $this->installFixture('acme/core', '1.0.0', ['acme/app' => '^1.0']);
        $this->writeMaster(['acme/core' => true]);

        $this->expectException(DependencyResolutionException::class);
        $this->expectExceptionMessage('Circular dependency');
        $this->manager()->assertCanInstall($this->manifest('acme/app', '1.0.0', ['acme/core' => '^1.0']));
    }

    public function test_disabled_reverse_dependent_allows_update_and_removal(): void
    {
        $this->installFixture('acme/app', '1.0.0', ['acme/core' => '^1.0']);
        $this->installFixture('acme/core', '1.5.0');
        $this->writeMaster(['acme/app' => false, 'acme/core' => true]);
        $manager = $this->manager();

        $manager->assertCanInstall($this->manifest('acme/core', '2.0.0'));
        $manager->disable('acme/core');
        $manager->assertCanRemove('acme/core');

        $this->assertFalse($this->master()['acme/core']['enabled']);
    }

    public function test_boot_isolates_missing_and_incompatible_dependencies_and_skips_transitive_dependents(): void
    {
        $this->installFixture('acme/available', '1.0.0');
        $this->installFixture('acme/disabled', '1.0.0');
        $this->installFixture('acme/missing', '1.0.0', ['acme/absent' => '^1.0']);
        $this->installFixture('acme/incompatible', '1.0.0', ['acme/available' => '^2.0']);
        $this->installFixture('acme/requires-disabled', '1.0.0', ['acme/disabled' => '^1.0']);
        $this->installFixture('acme/after-missing', '1.0.0', ['acme/missing' => '^1.0']);
        $this->installFixture('acme/after-incompatible', '1.0.0', ['acme/incompatible' => '^1.0']);
        $this->writeMaster([
            'acme/available' => true,
            'acme/disabled' => false,
            'acme/missing' => true,
            'acme/incompatible' => true,
            'acme/requires-disabled' => true,
            'acme/after-missing' => true,
            'acme/after-incompatible' => true,
        ]);
        $manager = $this->manager();

        $manager->boot();

        $this->assertSame(['acme/available'], array_keys($manager->all()));
        $failures = $manager->getBootFailures();
        $this->assertSame('dependency validation', $failures['acme/missing']['stage']);
        $this->assertStringContainsString("'acme/absent' (^1.0), which is not installed", $failures['acme/missing']['message']);
        $this->assertSame('dependency validation', $failures['acme/incompatible']['stage']);
        $this->assertStringContainsString('version 1.0.0 is installed', $failures['acme/incompatible']['message']);
        $this->assertSame('dependency validation', $failures['acme/requires-disabled']['stage']);
        $this->assertStringContainsString('which is disabled', $failures['acme/requires-disabled']['message']);
        $this->assertSame('skipped', $failures['acme/after-missing']['status']);
        $this->assertSame('acme/missing', $failures['acme/after-missing']['dependency']);
        $this->assertSame('skipped', $failures['acme/after-incompatible']['status']);
        $this->assertSame('acme/incompatible', $failures['acme/after-incompatible']['dependency']);
    }

    public function test_add_command_rejects_incompatible_update_before_replacing_files(): void
    {
        $this->installFixture('acme/app', '1.0.0', ['acme/core' => '^1.0']);
        $this->installFixture('acme/core', '1.5.0');
        $this->writeMaster(['acme/app' => true, 'acme/core' => true]);
        InstalledExtension::create([
            'extension_id' => 'acme/core',
            'name' => 'Core',
            'version' => '1.5.0',
            'enabled' => true,
            'manifest' => $this->manifest('acme/core', '1.5.0')->getRaw(),
        ]);

        $source = sys_get_temp_dir() . '/notur-dependency-candidate-' . uniqid();
        mkdir($source);
        $archive = $source . '.notur';
        file_put_contents($source . '/extension.yaml', Yaml::dump($this->manifest('acme/core', '2.0.0')->getRaw()));

        try {
            NoturArchive::pack($source, $archive);
            $this->artisan('notur:add', ['extension' => $archive, '--force' => true])
                ->expectsOutputToContain("requires 'acme/core' ^1.0, but version 2.0.0 is installed")
                ->assertExitCode(1);
        } finally {
            @unlink($archive);
            $this->deleteDir($source);
        }

        $this->assertSame('1.5.0', ExtensionManifest::load(ExtensionPath::base('acme/core'))->getVersion());
        $this->assertSame('1.5.0', InstalledExtension::where('extension_id', 'acme/core')->first()->version);
    }

    public function test_update_of_disabled_extension_with_absent_requirement_stays_disabled(): void
    {
        $this->installFixture('acme/app', '1.0.0');
        $this->writeMaster(['acme/app' => false]);
        InstalledExtension::create([
            'extension_id' => 'acme/app',
            'name' => 'App',
            'version' => '1.0.0',
            'enabled' => true, // Deliberately stale; the JSON state is authoritative.
            'manifest' => $this->manifest('acme/app', '1.0.0')->getRaw(),
        ]);

        $source = sys_get_temp_dir() . '/notur-disabled-candidate-' . uniqid();
        mkdir($source);
        $archive = $source . '.notur';
        file_put_contents($source . '/extension.yaml', Yaml::dump(
            $this->manifest('acme/app', '2.0.0', ['acme/absent' => '^9.0'])->getRaw()
        ));

        try {
            NoturArchive::pack($source, $archive);
            $this->artisan('notur:add', ['extension' => $archive, '--force' => true])
                ->expectsOutputToContain("Extension 'acme/app' v2.0.0 installed and disabled.")
                ->assertExitCode(0);
        } finally {
            @unlink($archive);
            $this->deleteDir($source);
        }

        $this->assertFalse($this->master()['acme/app']['enabled']);
        $this->assertSame('2.0.0', ExtensionManifest::load(ExtensionPath::base('acme/app'))->getVersion());
        $this->assertDatabaseHas('notur_extensions', [
            'extension_id' => 'acme/app',
            'version' => '2.0.0',
            'enabled' => false,
        ]);
    }

    public function test_remove_command_rejects_active_dependent_before_cleanup(): void
    {
        $this->installFixture('acme/app', '1.0.0', ['acme/core' => '^1.0']);
        $this->installFixture('acme/core', '1.5.0');
        $this->writeMaster(['acme/app' => true, 'acme/core' => true]);
        InstalledExtension::create([
            'extension_id' => 'acme/core',
            'name' => 'Core',
            'version' => '1.5.0',
            'enabled' => true,
            'manifest' => $this->manifest('acme/core', '1.5.0')->getRaw(),
        ]);

        $this->artisan('notur:remove', ['extension' => 'acme/core', '--force' => true])
            ->expectsOutputToContain("Extension 'acme/app' requires 'acme/core'")
            ->assertExitCode(1);

        $this->assertFileExists(ExtensionPath::base('acme/core') . '/extension.yaml');
        $this->assertNotNull(InstalledExtension::where('extension_id', 'acme/core')->first());
        $this->assertTrue($this->master()['acme/core']['enabled']);
    }

    /** @param array<string, string> $dependencies */
    private function manifest(string $id, string $version, array $dependencies = []): ExtensionManifest
    {
        return ExtensionManifest::fromArray([
            'id' => $id,
            'name' => $id,
            'version' => $version,
            'dependencies' => $dependencies,
        ]);
    }

    /** @param array<string, string> $dependencies */
    private function installFixture(string $id, string $version, array $dependencies = []): void
    {
        $path = ExtensionPath::base($id);
        mkdir($path, 0755, true);
        file_put_contents($path . '/extension.yaml', Yaml::dump($this->manifest($id, $version, $dependencies)->getRaw()));
    }

    /** @param array<string, bool> $enabled */
    private function writeMaster(array $enabled): void
    {
        $entries = [];
        foreach ($enabled as $id => $value) {
            try {
                $version = ExtensionManifest::load(ExtensionPath::base($id))->getVersion();
            } catch (\Throwable) {
                $version = '1.0.0';
            }
            $entries[$id] = ['version' => $version, 'enabled' => $value];
        }
        $path = ExtensionPath::manifest();
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, json_encode(['extensions' => $entries]));
    }

    private function master(): array
    {
        return json_decode((string) file_get_contents(ExtensionPath::manifest()), true)['extensions'];
    }

    private function manager(): ExtensionManager
    {
        return new ExtensionManager($this->app, new DependencyResolver(), new PermissionBroker());
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
