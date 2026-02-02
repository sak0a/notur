<?php

declare(strict_types=1);

namespace Notur\Console\Commands;

use Illuminate\Console\Command;
use Notur\Events\ExtensionRemoved;
use Notur\ExtensionManager;
use Notur\ExtensionManifest;
use Notur\MigrationManager;
use Notur\Models\InstalledExtension;
use Notur\Support\ExtensionPath;

class RemoveCommand extends Command
{
    protected $signature = 'notur:remove
        {extension : The extension ID (vendor/name)}
        {--keep-data : Keep extension data (skip migration rollback)}';

    protected $description = 'Remove a Notur extension';

    public function handle(ExtensionManager $manager, MigrationManager $migrationManager): int
    {
        $extensionId = $this->argument('extension');

        $record = InstalledExtension::where('extension_id', $extensionId)->first();
        if (!$record) {
            $this->error("Extension '{$extensionId}' is not installed.");
            return 1;
        }

        if (!$this->confirm("Are you sure you want to remove '{$extensionId}'?")) {
            return 0;
        }

        $this->info("Removing extension '{$extensionId}'...");

        // Disable first
        $manager->disable($extensionId);

        // Roll back migrations
        if (!$this->option('keep-data')) {
            $extensionPath = ExtensionPath::base($extensionId);
            try {
                $manifest = ExtensionManifest::load($extensionPath);
                $migrationsPath = $extensionPath . '/' . $manifest->getMigrationsPath();

                if ($manifest->getMigrationsPath()) {
                    $rolledBack = $migrationManager->rollback($extensionId, $migrationsPath);
                    if (!empty($rolledBack)) {
                        $this->info('Rolled back ' . count($rolledBack) . ' migration(s).');
                    }
                }
            } catch (\Throwable $e) {
                $this->warn("Could not roll back migrations: {$e->getMessage()}");
            }
        }

        // Remove files
        $extensionPath = ExtensionPath::base($extensionId);
        if (is_dir($extensionPath)) {
            $this->deleteDirectory($extensionPath);
            $this->info('Removed extension files.');
        }

        // Remove public assets
        $publicPath = ExtensionPath::public($extensionId);
        if (is_dir($publicPath)) {
            $this->deleteDirectory($publicPath);
        }

        // Unregister from manifest
        $manager->unregisterExtension($extensionId);

        // Remove from database
        $record->delete();

        // Fire event
        ExtensionRemoved::dispatch($extensionId);

        // Clear caches
        $this->call('cache:clear');
        $this->call('view:clear');

        $this->info("Extension '{$extensionId}' has been removed.");

        return 0;
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
