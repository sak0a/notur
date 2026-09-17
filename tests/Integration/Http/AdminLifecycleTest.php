<?php

declare(strict_types=1);

namespace Notur\Tests\Integration\Http;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Notur\Http\Controllers\ExtensionAdminController;
use Notur\Models\InstalledExtension;
use Notur\Models\ExtensionSetting;
use Notur\NoturServiceProvider;
use Notur\Support\ExtensionPath;
use Notur\Support\NoturArchive;
use Notur\Support\RegistryClient;
use Notur\Support\SignatureVerifier;
use Orchestra\Testbench\TestCase;

class AdminLifecycleTest extends TestCase
{
    private string $scratch;
    private string $relative;

    protected function getPackageProviders($app): array { return [NoturServiceProvider::class]; }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('session.driver', 'array');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
        $this->scratch = sys_get_temp_dir() . '/notur-admin-test-' . bin2hex(random_bytes(8));
        mkdir($this->scratch, 0700);
        $this->relative = 'notur-admin-test-' . bin2hex(random_bytes(8));
        config(['notur.extensions_path' => $this->relative . '/extensions', 'notur.backups_path' => $this->scratch . '/backups']);
        Route::middleware('web')->group(function (): void {
            Route::post('/ui-test/install', [ExtensionAdminController::class, 'install']);
            Route::post('/ui-test/remove/{extensionId}', [ExtensionAdminController::class, 'remove'])->where('extensionId', '.+');
            Route::post('/ui-test/update/{extensionId}', [ExtensionAdminController::class, 'update'])->where('extensionId', '.+');
        });
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->scratch);
        File::deleteDirectory(base_path($this->relative));
        File::deleteDirectory(public_path($this->relative));
        parent::tearDown();
    }

    public function test_upload_install_rejects_duplicates_until_replacement_is_selected(): void
    {
        $this->post('/ui-test/install', ['archive' => $this->archive('1.0.0')])->assertSessionHas('success');
        $this->post('/ui-test/install', ['archive' => $this->archive('2.0.0')])->assertSessionHas('error');
        $this->assertDatabaseHas('notur_extensions', ['extension_id' => 'audit/demo', 'version' => '1.0.0']);
        $this->post('/ui-test/install', ['archive' => $this->archive('2.0.0'), 'force' => '1'])->assertSessionHas('success');
        $this->assertSame('asset 2.0.0', file_get_contents(ExtensionPath::public('audit/demo') . '/frontend/main.js'));
        $this->assertCount(1, glob($this->scratch . '/backups/extension-*'));
    }

    public function test_ambiguous_source_is_rejected_without_installing(): void
    {
        $this->post('/ui-test/install', ['registry_id' => 'audit/demo', 'archive' => $this->archive('1.0.0')])
            ->assertSessionHasErrors(['registry_id', 'archive']);
        $this->assertDatabaseCount('notur_extensions', 0);
    }

    public function test_corrupt_archive_returns_error_and_preserves_installed_files(): void
    {
        $this->post('/ui-test/install', ['archive' => $this->archive('1.0.0')])->assertSessionHas('success');
        $this->post('/ui-test/install', ['archive' => UploadedFile::fake()->createWithContent('broken.notur', 'not an archive'), 'force' => '1'])
            ->assertSessionHas('error');
        $this->assertSame('asset 1.0.0', file_get_contents(ExtensionPath::public('audit/demo') . '/frontend/main.js'));
    }

    public function test_signed_archive_requires_and_accepts_detached_signature(): void
    {
        $verifier = new SignatureVerifier();
        $keys = $verifier->generateKeypair();
        config(['notur.require_signatures' => true, 'notur.public_key' => $keys['public']]);
        $this->post('/ui-test/install', ['archive' => $this->archive('1.0.0')])->assertSessionHasErrors('signature');
        $archive = $this->archive('1.0.0');
        $signature = $verifier->sign($archive->getPathname(), $keys['secret']);
        $this->post('/ui-test/install', ['archive' => $archive, 'signature' => UploadedFile::fake()->createWithContent('extension.sig', $signature)])
            ->assertSessionHas('success');
        $this->assertDatabaseHas('notur_extensions', ['extension_id' => 'audit/demo']);
    }

    public function test_keep_data_choice_is_honored_by_web_removal(): void
    {
        $this->post('/ui-test/install', ['archive' => $this->archive('1.0.0')])->assertSessionHas('success');
        ExtensionSetting::create(['extension_id' => 'audit/demo', 'key' => 'example', 'value' => 'saved']);
        $this->post('/ui-test/remove/audit/demo', ['keep_data' => '1'])->assertSessionHas('success');
        $this->assertDatabaseHas('notur_settings', ['extension_id' => 'audit/demo', 'key' => 'example']);
        $this->assertDatabaseMissing('notur_extensions', ['extension_id' => 'audit/demo']);
        $this->assertDirectoryDoesNotExist(ExtensionPath::base('audit/demo'));
    }

    public function test_web_update_cannot_downgrade_an_installed_extension(): void
    {
        $this->post('/ui-test/install', ['archive' => $this->archive('2.0.0')])->assertSessionHas('success');
        $registry = \Mockery::mock(RegistryClient::class);
        $registry->shouldReceive('getExtension')->once()->with('audit/demo')->andReturn(['latest_version' => '1.0.0']);
        $registry->shouldNotReceive('download');
        $this->app->instance(RegistryClient::class, $registry);
        $this->post('/ui-test/update/audit/demo')->assertSessionHas('success');
        $this->assertDatabaseHas('notur_extensions', ['extension_id' => 'audit/demo', 'version' => '2.0.0']);
    }

    public function test_bulk_update_continues_after_one_extension_throws(): void
    {
        foreach (['audit/first', 'audit/second'] as $id) {
            InstalledExtension::create(['extension_id' => $id, 'name' => $id, 'version' => '1.0.0', 'enabled' => false, 'manifest' => []]);
        }
        $registry = \Mockery::mock(RegistryClient::class);
        $registry->shouldReceive('getExtension')->twice()->andReturn(['latest_version' => '2.0.0']);
        $controller = new class($this->app->make(\Notur\ExtensionManager::class)) extends ExtensionAdminController {
            public array $attempts = [];
            protected function runUpdateCommand(string $id): array
            {
                $this->attempts[] = $id;
                if ($id === 'audit/first') throw new \RuntimeException('download unavailable');
                return ['exitCode' => 0, 'output' => 'updated'];
            }
        };
        $response = $controller->updateAll($registry);
        $this->assertSame(['audit/first', 'audit/second'], $controller->attempts);
        $this->assertStringContainsString('Updated 1 extension(s)', $response->getSession()->get('error'));
        $this->assertStringContainsString('audit/first', $response->getSession()->get('error'));
    }

    public function test_stale_enable_action_returns_feedback_instead_of_server_error(): void
    {
        $controller = new ExtensionAdminController($this->app->make(\Notur\ExtensionManager::class));
        $response = $controller->enable('audit/missing');
        $this->assertSame(route('admin.notur.extensions'), $response->getTargetUrl());
        $this->assertNotEmpty($response->getSession()->get('error'));
    }

    private function archive(string $version): UploadedFile
    {
        $source = $this->scratch . '/' . bin2hex(random_bytes(6));
        mkdir($source . '/frontend', 0755, true);
        file_put_contents($source . '/extension.yaml', "id: audit/demo\nname: Audit Demo\nversion: {$version}\nfrontend:\n  bundle: frontend/main.js\n");
        file_put_contents($source . '/frontend/main.js', 'asset ' . $version);
        NoturArchive::pack($source, $source . '.notur');
        return new UploadedFile($source . '.notur', 'extension.notur', null, null, true);
    }
}
