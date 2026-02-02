<?php

declare(strict_types=1);

namespace Notur\Tests\Integration;

use Illuminate\Support\Facades\Schema;
use Notur\MigrationManager;
use Notur\Models\ExtensionMigration;
use Notur\NoturServiceProvider;
use Orchestra\Testbench\TestCase;

class MigrationManagerTest extends TestCase
{
    private MigrationManager $manager;
    private string $fixturesPath;

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

        $this->runNoturMigrations();

        $this->manager = $this->app->make(MigrationManager::class);
        $this->fixturesPath = $this->createMigrationFixtures();
    }

    protected function tearDown(): void
    {
        // Clean up the temp fixtures directory
        if (isset($this->fixturesPath) && is_dir($this->fixturesPath)) {
            $this->deleteDirectory($this->fixturesPath);
        }

        parent::tearDown();
    }

    public function test_migrate_runs_pending_migrations(): void
    {
        $ran = $this->manager->migrate('acme/test', $this->fixturesPath);

        $this->assertCount(2, $ran);
        $this->assertSame('2024_01_15_000001_create_acme_items_table', $ran[0]);
        $this->assertSame('2024_01_15_000002_create_acme_logs_table', $ran[1]);

        $this->assertTrue(Schema::hasTable('acme_items'));
        $this->assertTrue(Schema::hasTable('acme_logs'));
    }

    public function test_migrate_skips_already_executed_migrations(): void
    {
        // Run once
        $this->manager->migrate('acme/test', $this->fixturesPath);

        // Run again — should return empty
        $ran = $this->manager->migrate('acme/test', $this->fixturesPath);

        $this->assertEmpty($ran);
    }

    public function test_migrate_returns_empty_for_nonexistent_path(): void
    {
        $ran = $this->manager->migrate('acme/test', '/nonexistent/path');

        $this->assertEmpty($ran);
    }

    public function test_rollback_reverts_all_migrations(): void
    {
        $this->manager->migrate('acme/test', $this->fixturesPath);

        $this->assertTrue(Schema::hasTable('acme_items'));
        $this->assertTrue(Schema::hasTable('acme_logs'));

        $rolledBack = $this->manager->rollback('acme/test', $this->fixturesPath);

        $this->assertCount(2, $rolledBack);
        $this->assertFalse(Schema::hasTable('acme_items'));
        $this->assertFalse(Schema::hasTable('acme_logs'));
    }

    public function test_rollback_removes_records_from_tracking_table(): void
    {
        $this->manager->migrate('acme/test', $this->fixturesPath);

        $this->assertSame(2, ExtensionMigration::where('extension_id', 'acme/test')->count());

        $this->manager->rollback('acme/test', $this->fixturesPath);

        $this->assertSame(0, ExtensionMigration::where('extension_id', 'acme/test')->count());
    }

    public function test_rollback_order_is_reverse_of_migrate(): void
    {
        $this->manager->migrate('acme/test', $this->fixturesPath);

        $rolledBack = $this->manager->rollback('acme/test', $this->fixturesPath);

        // Should be rolled back in reverse order (latest first)
        $this->assertSame('2024_01_15_000002_create_acme_logs_table', $rolledBack[0]);
        $this->assertSame('2024_01_15_000001_create_acme_items_table', $rolledBack[1]);
    }

    public function test_status_reports_migration_state(): void
    {
        // Before migrating
        $status = $this->manager->status('acme/test', $this->fixturesPath);

        $this->assertCount(2, $status);
        $this->assertFalse($status[0]['ran']);
        $this->assertFalse($status[1]['ran']);

        // After migrating
        $this->manager->migrate('acme/test', $this->fixturesPath);
        $status = $this->manager->status('acme/test', $this->fixturesPath);

        $this->assertCount(2, $status);
        $this->assertTrue($status[0]['ran']);
        $this->assertTrue($status[1]['ran']);
    }

    public function test_status_returns_empty_for_nonexistent_path(): void
    {
        $status = $this->manager->status('acme/test', '/nonexistent/path');

        $this->assertEmpty($status);
    }

    public function test_batch_numbering_increments(): void
    {
        // Run first migration set
        $this->manager->migrate('acme/test', $this->fixturesPath);

        $batches = ExtensionMigration::where('extension_id', 'acme/test')
            ->pluck('batch', 'migration')
            ->toArray();

        // Each migration in a single migrate() call gets its own incrementing batch
        // because getNextBatch is called per migration
        $this->assertSame(1, $batches['2024_01_15_000001_create_acme_items_table']);
        $this->assertSame(2, $batches['2024_01_15_000002_create_acme_logs_table']);
    }

    public function test_migrations_are_scoped_per_extension(): void
    {
        // Create a second fixture set for a different extension
        $secondPath = $this->createSecondExtensionFixtures();

        $this->manager->migrate('acme/test', $this->fixturesPath);
        $this->manager->migrate('acme/other', $secondPath);

        $this->assertSame(2, ExtensionMigration::where('extension_id', 'acme/test')->count());
        $this->assertSame(1, ExtensionMigration::where('extension_id', 'acme/other')->count());

        // Rolling back one extension should not affect the other
        $this->manager->rollback('acme/test', $this->fixturesPath);

        $this->assertSame(0, ExtensionMigration::where('extension_id', 'acme/test')->count());
        $this->assertSame(1, ExtensionMigration::where('extension_id', 'acme/other')->count());

        $this->assertTrue(Schema::hasTable('acme_other_data'));

        $this->deleteDirectory($secondPath);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function runNoturMigrations(): void
    {
        $migrationsPath = dirname(__DIR__, 2) . '/database/migrations';
        $this->loadMigrationsFrom($migrationsPath);
        $this->artisan('migrate', ['--database' => 'testing'])->run();
    }

    private function createMigrationFixtures(): string
    {
        $dir = sys_get_temp_dir() . '/notur-test-migrations-' . uniqid();
        mkdir($dir, 0755, true);

        file_put_contents($dir . '/2024_01_15_000001_create_acme_items_table.php', <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acme_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acme_items');
    }
};
PHP);

        file_put_contents($dir . '/2024_01_15_000002_create_acme_logs_table.php', <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acme_logs', function (Blueprint $table) {
            $table->id();
            $table->string('message');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acme_logs');
    }
};
PHP);

        return $dir;
    }

    private function createSecondExtensionFixtures(): string
    {
        $dir = sys_get_temp_dir() . '/notur-test-migrations-other-' . uniqid();
        mkdir($dir, 0755, true);

        file_put_contents($dir . '/2024_02_01_000001_create_acme_other_data_table.php', <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acme_other_data', function (Blueprint $table) {
            $table->id();
            $table->text('payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acme_other_data');
    }
};
PHP);

        return $dir;
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
