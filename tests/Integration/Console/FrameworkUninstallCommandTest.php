<?php

declare(strict_types=1);

namespace Notur\Tests\Integration\Console;

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Notur\Console\Commands\FrameworkUninstallCommand;
use Notur\NoturServiceProvider;
use Orchestra\Testbench\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class FrameworkUninstallCommandTest extends TestCase
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
            'foreign_key_constraints' => true,
        ]);
    }

    public function test_database_cleanup_removes_every_framework_table_and_migration(): void
    {
        $migrationPath = __DIR__ . '/../../../database/migrations';
        $this->loadMigrationsFrom($migrationPath);

        // Discover the actual migrated schema, so a newly added table cannot be
        // omitted from uninstall while a duplicated hard-coded test list passes.
        $tables = array_values(array_filter(
            array_column(DB::select("SELECT name FROM sqlite_master WHERE type = 'table'"), 'name'),
            static fn (string $table): bool => str_starts_with($table, 'notur_'),
        ));
        $this->assertContains('notur_remote_push_keys', $tables);
        $migrations = array_map(
            static fn (string $file): string => basename($file, '.php'),
            glob($migrationPath . '/*.php'),
        );
        $this->assertSame(count($migrations), DB::table('migrations')->whereIn('migration', $migrations)->count());
        DB::table('migrations')->insert(['migration' => '2026_01_01_000001_create_panel_users_table', 'batch' => 1]);

        $output = new BufferedOutput();
        $command = new FrameworkUninstallCommand();
        $command->setLaravel($this->app);
        $command->setOutput(new OutputStyle(new ArrayInput([]), $output));

        // Exercise the database phase without running Composer removal or
        // modifying the host panel's files and frontend assets.
        $cleanup = new \ReflectionMethod($command, 'rollbackMigrations');
        $cleanup->invoke($command);

        foreach ($tables as $table) {
            $this->assertFalse(Schema::hasTable($table), "Uninstall left framework table {$table} behind.");
        }
        $this->assertSame(0, DB::table('migrations')->whereIn('migration', $migrations)->count());
        $this->assertTrue(DB::table('migrations')->where('migration', '2026_01_01_000001_create_panel_users_table')->exists());
        $this->assertStringContainsString('Dropped table: notur_remote_push_keys', $output->fetch());

        // Database cleanup must also tolerate an already-clean installation.
        $cleanup->invoke($command);
        $this->assertStringContainsString('Verified Notur migration tables were removed.', $output->fetch());
    }
}
