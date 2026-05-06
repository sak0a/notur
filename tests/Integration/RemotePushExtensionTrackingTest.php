<?php

declare(strict_types=1);

namespace Notur\Tests\Integration;

use Notur\Http\Controllers\ExtensionRemotePushController;
use Notur\Models\InstalledExtension;
use Notur\Models\RemotePushApiKey;
use Notur\NoturServiceProvider;
use Orchestra\Testbench\TestCase;

class RemotePushExtensionTrackingTest extends TestCase
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
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    public function test_record_success_with_db_key_populates_all_columns(): void
    {
        $plaintext = RemotePushApiKey::generatePlaintext();
        $key = RemotePushApiKey::create([
            'name' => 'CI',
            'prefix' => RemotePushApiKey::prefixOf($plaintext),
            'token_hash' => RemotePushApiKey::hashToken($plaintext),
        ]);

        InstalledExtension::create([
            'extension_id' => 'acme/tracked',
            'name' => 'Tracked',
            'version' => '1.0.0',
            'enabled' => true,
            'manifest' => ['id' => 'acme/tracked', 'name' => 'Tracked', 'version' => '1.0.0'],
        ]);

        (new ExtensionRemotePushController())->recordSuccess('acme/tracked', $key, str_repeat('a', 64), 4096);

        $row = InstalledExtension::where('extension_id', 'acme/tracked')->first();
        $this->assertSame('remote_push', $row->source);
        $this->assertSame($key->id, $row->pushed_via_key_id);
        $this->assertNotNull($row->last_pushed_at);
        $this->assertSame(str_repeat('a', 64), $row->package_checksum);
        $this->assertSame(4096, $row->package_size);
        $this->assertNull($row->last_push_error);
    }

    public function test_record_success_with_null_key_records_source_only(): void
    {
        InstalledExtension::create([
            'extension_id' => 'acme/envpushed',
            'name' => 'Env',
            'version' => '1.0.0',
            'enabled' => true,
            'manifest' => ['id' => 'acme/envpushed', 'name' => 'Env', 'version' => '1.0.0'],
        ]);

        (new ExtensionRemotePushController())->recordSuccess('acme/envpushed', null, null, null);

        $row = InstalledExtension::where('extension_id', 'acme/envpushed')->first();
        $this->assertSame('remote_push', $row->source);
        $this->assertNull($row->pushed_via_key_id);
    }

    public function test_record_success_is_noop_when_row_does_not_exist(): void
    {
        (new ExtensionRemotePushController())->recordSuccess('acme/nope', null, null, null);

        $this->assertSame(0, InstalledExtension::query()->count());
    }

    public function test_record_failure_sets_last_push_error_on_existing_row(): void
    {
        InstalledExtension::create([
            'extension_id' => 'acme/already',
            'name' => 'Already',
            'version' => '0.1.0',
            'enabled' => true,
            'manifest' => ['id' => 'acme/already', 'name' => 'Already', 'version' => '0.1.0'],
            'source' => 'manual',
        ]);

        (new ExtensionRemotePushController())->recordFailure('acme/already', 'simulated install error');

        $row = InstalledExtension::where('extension_id', 'acme/already')->first();
        $this->assertStringContainsString('simulated install error', (string) $row->last_push_error);
    }

    public function test_record_failure_uses_default_message_when_output_empty(): void
    {
        InstalledExtension::create([
            'extension_id' => 'acme/silent',
            'name' => 'Silent',
            'version' => '0.1.0',
            'enabled' => true,
            'manifest' => ['id' => 'acme/silent', 'name' => 'Silent', 'version' => '0.1.0'],
        ]);

        (new ExtensionRemotePushController())->recordFailure('acme/silent', null);

        $row = InstalledExtension::where('extension_id', 'acme/silent')->first();
        $this->assertSame('Push failed.', $row->last_push_error);
    }

    public function test_record_failure_is_noop_when_row_does_not_exist(): void
    {
        (new ExtensionRemotePushController())->recordFailure('acme/nope', 'whatever');

        $this->assertSame(0, InstalledExtension::query()->count());
    }
}
