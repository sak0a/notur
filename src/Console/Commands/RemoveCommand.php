<?php

declare(strict_types=1);

namespace Notur\Console\Commands;

use Notur\Events\ExtensionRemoved;
use Notur\Exceptions\ExtensionNotFoundException;
use Notur\Exceptions\DependencyResolutionException;
use Notur\ExtensionManager;
use Notur\ExtensionManifest;
use Notur\MigrationManager;
use Notur\Models\InstalledExtension;
use Notur\Support\ExtensionPath;

class RemoveCommand extends ExtensionLifecycleCommand
{
    protected $signature = 'notur:remove
        {extension : The extension ID (vendor/name)}
        {--keep-data : Keep extension data (skip migration rollback)}
        {--force : Skip confirmation prompts}';

    protected $description = 'Remove a Notur extension';

    public function handle(ExtensionManager $manager, MigrationManager $migrationManager): int
    {
        $extensionId = $this->argument('extension');

        $record = InstalledExtension::where('extension_id', $extensionId)->first();
        if (!$record && $manager->getInstalledState($extensionId) === null) {
            $this->error("Extension '{$extensionId}' is not installed.");
            return 1;
        }

        try {
            $manager->assertCanRemove($extensionId);
        } catch (DependencyResolutionException $e) {
            $this->error($e->getMessage());
            return 1;
        }

        if (!$this->option('force') && !$this->input->isInteractive()) {
            $this->error("Non-interactive removal requires --force for extension '{$extensionId}'.");
            return 1;
        }

        if (!$this->option('force') && !$this->confirm("Are you sure you want to remove '{$extensionId}'?")) {
            return 0;
        }

        $this->info("Removing extension '{$extensionId}'...");

        try {
            $backup = app(\Notur\Support\LifecycleBackup::class)->extension($extensionId);
            $this->info("File backup: {$backup} (database not included).");
        } catch (\Throwable $e) {
            $this->error("Backup failed; removal stopped: {$e->getMessage()}");
            return 1;
        }

        // Disable first when the extension still exists in the master manifest.
        try {
            $manager->disable($extensionId);
        } catch (ExtensionNotFoundException $e) {
            $this->warn("Extension '{$extensionId}' was missing from the Notur manifest; continuing database and file cleanup.");
        }

        // Roll back migrations
        if (!$this->option('keep-data')) {
            $extensionPath = ExtensionPath::base($extensionId);
            try {
                $hasMigrations = \Notur\Models\ExtensionMigration::where('extension_id', $extensionId)->exists();
                $manifest = !is_dir($extensionPath) && !$hasMigrations ? null : ExtensionManifest::load($extensionPath);
                $migrationsPath = $extensionPath . '/' . $manifest?->getMigrationsPath();

                if ($hasMigrations && !$manifest?->getMigrationsPath()) {
                    throw new \RuntimeException('Tracked migrations exist but the manifest has no migrations path.');
                }
                if ($manifest?->getMigrationsPath()) {
                    $rolledBack = $migrationManager->rollback($extensionId, $migrationsPath);
                    if (!empty($rolledBack)) {
                        $this->info('Rolled back ' . count($rolledBack) . ' migration(s).');
                    }
                }
            } catch (\Throwable $e) {
                $this->error("Could not roll back migrations: {$e->getMessage()}");
                $this->warn('Removal stopped. Files and registration were retained; the extension remains disabled. Repair the migration or explicitly use --keep-data.');
                return 1;
            }
        }

        // Remove files and public assets
        $this->removeExtensionFiles($extensionId);
        $this->info('Removed extension files.');

        if (!$this->option('keep-data')) {
            \Notur\Models\ExtensionSetting::where('extension_id', $extensionId)->delete();
        }

        // Unregister from manifest
        $manager->unregisterExtension($extensionId);

        // Fire event
        ExtensionRemoved::dispatch($extensionId);

        // Clear caches
        $this->clearNoturCaches();

        $this->info("Extension '{$extensionId}' has been removed.");

        return 0;
    }

}
