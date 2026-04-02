<?php

declare(strict_types=1);

namespace Notur\Console\Commands;

use Notur\Events\ExtensionRemoved;
use Notur\ExtensionManager;
use Notur\ExtensionManifest;
use Notur\MigrationManager;
use Notur\Models\InstalledExtension;
use Notur\Support\ExtensionPath;

class RemoveCommand extends ExtensionLifecycleCommand
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

        // Remove files and public assets
        $this->removeExtensionFiles($extensionId);
        $this->info('Removed extension files.');

        // Unregister from manifest
        $manager->unregisterExtension($extensionId);

        // Remove from database
        $record->delete();

        // Fire event
        ExtensionRemoved::dispatch($extensionId);

        // Clear caches
        $this->clearNoturCaches();

        $this->info("Extension '{$extensionId}' has been removed.");

        return 0;
    }

}
