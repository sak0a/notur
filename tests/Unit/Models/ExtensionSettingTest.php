<?php

declare(strict_types=1);

namespace Notur\Tests\Unit\Models;

use Notur\Models\ExtensionSetting;
use Notur\NoturServiceProvider;
use Orchestra\Testbench\TestCase;

class ExtensionSettingTest extends TestCase
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
        $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
    }

    public function test_creates_setting_record(): void
    {
        $setting = ExtensionSetting::create([
            'extension_id' => 'acme/test',
            'key' => 'api_key',
            'value' => 'secret-123',
        ]);

        $this->assertDatabaseHas('notur_settings', [
            'extension_id' => 'acme/test',
            'key' => 'api_key',
        ]);
    }

    public function test_get_value_returns_setting_value(): void
    {
        ExtensionSetting::create([
            'extension_id' => 'acme/test',
            'key' => 'mode',
            'value' => 'production',
        ]);

        $value = ExtensionSetting::getValue('acme/test', 'mode');

        $this->assertSame('production', $value);
    }

    public function test_get_value_returns_default_when_not_found(): void
    {
        $value = ExtensionSetting::getValue('acme/test', 'nonexistent', 'default-value');

        $this->assertSame('default-value', $value);
    }

    public function test_set_value_creates_new_setting(): void
    {
        ExtensionSetting::setValue('acme/test', 'new_key', 'new_value');

        $this->assertDatabaseHas('notur_settings', [
            'extension_id' => 'acme/test',
            'key' => 'new_key',
        ]);

        $value = ExtensionSetting::getValue('acme/test', 'new_key');
        $this->assertSame('new_value', $value);
    }

    public function test_set_value_updates_existing_setting(): void
    {
        ExtensionSetting::create([
            'extension_id' => 'acme/test',
            'key' => 'mode',
            'value' => 'development',
        ]);

        ExtensionSetting::setValue('acme/test', 'mode', 'production');

        $value = ExtensionSetting::getValue('acme/test', 'mode');
        $this->assertSame('production', $value);

        // Ensure only one record exists
        $count = ExtensionSetting::where('extension_id', 'acme/test')
            ->where('key', 'mode')
            ->count();
        $this->assertSame(1, $count);
    }

    public function test_delete_value_removes_setting(): void
    {
        ExtensionSetting::create([
            'extension_id' => 'acme/test',
            'key' => 'to_delete',
            'value' => 'value',
        ]);

        ExtensionSetting::where('extension_id', 'acme/test')
            ->where('key', 'to_delete')
            ->delete();

        $this->assertDatabaseMissing('notur_settings', [
            'extension_id' => 'acme/test',
            'key' => 'to_delete',
        ]);
    }
}
