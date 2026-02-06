<?php

declare(strict_types=1);

namespace Notur\Tests\Unit\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Notur\NoturServiceProvider;
use Orchestra\Testbench\TestCase;

class CreateNoturActivityLogsMigrationTest extends TestCase
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

    public function test_activity_log_migration_can_run_when_table_already_exists(): void
    {
        /** @var Migration $migration */
        $migration = require __DIR__ . '/../../../database/migrations/2026_02_03_000004_create_notur_activity_logs_table.php';

        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasTable('notur_activity_logs'));
    }
}
