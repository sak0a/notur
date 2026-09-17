<?php

declare(strict_types=1);

namespace Notur\Console\Commands;

use Notur\Events\ExtensionInstalled;
use Notur\Events\ExtensionUpdated;
use Notur\Exceptions\DependencyResolutionException;
use Notur\ExtensionManager;
use Notur\ExtensionManifest;
use Notur\MigrationManager;
use Notur\Models\InstalledExtension;
use Notur\Support\ExtensionPath;
use Notur\Support\NoturArchive;
use Notur\Support\RegistryClient;
use Notur\Support\SignatureVerifier;
use RuntimeException;

class AddCommand extends ExtensionLifecycleCommand
{
    protected $signature = 'notur:add
        {extension : The extension ID (vendor/name) or path to a .notur file}
        {--force : Overwrite if already installed}
        {--no-migrate : Skip running migrations}';

    protected $description = 'Add a Notur extension';

    public function handle(
        ExtensionManager $manager,
        MigrationManager $migrationManager,
        RegistryClient $registry,
        SignatureVerifier $verifier,
    ): int {
        $extension = $this->argument('extension');

        // Check if it's a local .notur file
        if (str_ends_with($extension, '.notur') && file_exists($extension)) {
            return $this->installFromFile($extension, $manager, $migrationManager, $verifier);
        }

        // Otherwise, fetch from registry
        return $this->installFromRegistry($extension, $manager, $migrationManager, $registry, $verifier);
    }

    private function installFromFile(
        string $filePath,
        ExtensionManager $manager,
        MigrationManager $migrationManager,
        SignatureVerifier $verifier,
        bool $cleanupArchive = false,
    ): int {
        $this->info("Installing from local file: {$filePath}");

        try {
            // Verify signature if required
            if (config('notur.require_signatures')) {
                $sigFile = $filePath . '.sig';
                if (!file_exists($sigFile)) {
                    $this->error('Signature file not found and signatures are required.');
                    return 1;
                }

                $signature = file_get_contents($sigFile);
                if (!is_string($signature)) {
                    $this->error('Failed to read signature file.');
                    return 1;
                }

                $publicKey = config('notur.public_key');

                if (!$verifier->verify($filePath, trim($signature), $publicKey)) {
                    $this->error('Signature verification failed.');
                    return 1;
                }

                $this->info('Signature verified.');
            }

            // Extract archive using NoturArchive (validates checksums)
            $tmpDir = sys_get_temp_dir() . '/notur-' . uniqid();
            try {
                NoturArchive::unpack($filePath, $tmpDir, true, true);
                $manifest = ExtensionManifest::load($tmpDir);
            } catch (\Throwable $e) {
                $this->error("Archive extraction failed: {$e->getMessage()}");
                $this->cleanupPath($tmpDir);
                return 1;
            }

            $this->info('Archive extracted and checksums verified.');

            $extensionId = $manifest->getId();
            try {
                return $this->finalizeInstall($extensionId, $tmpDir, $manifest, $manager, $migrationManager);
            } finally {
                $this->cleanupPath($tmpDir);
            }
        } finally {
            if ($cleanupArchive) {
                $this->cleanupPath($filePath);
                $this->cleanupPath($filePath . '.sig');
            }
        }
    }

    private function installFromRegistry(
        string $extensionId,
        ExtensionManager $manager,
        MigrationManager $migrationManager,
        RegistryClient $registry,
        SignatureVerifier $verifier,
    ): int {
        $this->info("Searching registry for: {$extensionId}");

        $extInfo = $registry->getExtension($extensionId);

        if (!$extInfo) {
            $this->error("Extension '{$extensionId}' not found in registry.");
            return 1;
        }

        $version = $extInfo['latest_version'] ?? $extInfo['version'] ?? '0.0.0';
        $this->info("Found {$extensionId} v{$version}");

        // Download
        $tmpFile = sys_get_temp_dir() . '/notur-' . uniqid() . '.notur';
        $this->info('Downloading...');

        try {
            $registry->download($extensionId, $version, $tmpFile);
        } catch (\Throwable $e) {
            $this->error("Download failed: {$e->getMessage()}");
            return 1;
        }

        $expectedChecksum = $registry->getExpectedArchiveChecksum($extensionId, $version);
        if (is_string($expectedChecksum) && $expectedChecksum !== '') {
            if (!$verifier->verifyChecksum($tmpFile, $expectedChecksum)) {
                $this->error("Checksum verification failed for '{$extensionId}' v{$version}.");
                $this->cleanupPath($tmpFile);
                return 1;
            }
            $this->info('Registry checksum verified.');
        }

        if (config('notur.require_signatures')) {
            try {
                $registry->downloadSignature($extensionId, $version, $tmpFile . '.sig');
            } catch (\Throwable $e) {
                $this->error("Signature download failed: {$e->getMessage()}");
                $this->cleanupPath($tmpFile);
                return 1;
            }
        }

        return $this->installFromFile(
            $tmpFile,
            $manager,
            $migrationManager,
            $verifier,
            cleanupArchive: true,
        );
    }

    private function finalizeInstall(
        string $extensionId,
        string $sourcePath,
        ExtensionManifest $manifest,
        ExtensionManager $manager,
        MigrationManager $migrationManager,
    ): int {
        // Check if already installed
        $existing = InstalledExtension::where('extension_id', $extensionId)->first();
        $installedState = $manager->getInstalledState($extensionId);
        if (($installedState !== null || $existing !== null) && !$this->option('force')) {
            $this->error("Extension '{$extensionId}' is already installed. Use --force to overwrite.");
            return 1;
        }
        // Safe mode skips reconciliation, so database values may be stale.
        // Keep the database fallback for legacy entries not yet present in JSON.
        $previousVersion = $installedState['version'] ?? $existing?->version;
        $wasEnabled = $installedState['enabled'] ?? $existing?->enabled ?? true;

        try {
            $manager->assertCanInstall($manifest);
        } catch (DependencyResolutionException $e) {
            $this->error($e->getMessage());
            return 1;
        }

        $targetPath = ExtensionPath::base($extensionId);
        $publicPath = ExtensionPath::public($extensionId);
        $suffix = bin2hex(random_bytes(8));
        $stagedPath = $targetPath . '.stage-' . $suffix;
        $stagedPublicPath = $publicPath . '.stage-' . $suffix;
        $backupPath = $targetPath . '.backup-' . $suffix;
        $backupPublicPath = $publicPath . '.backup-' . $suffix;
        $oldFilesMoved = false;
        $oldPublicMoved = false;
        $newFilesMoved = false;
        $newPublicMoved = false;
        $migrationStarted = false;
        $registrationStarted = false;

        try {
            // Both trees are complete before either live tree is touched.
            $this->copyStagedDirectory($sourcePath, $stagedPath);
            $stagedManifest = ExtensionManifest::load($stagedPath);
            if ($stagedManifest->getId() !== $extensionId || $stagedManifest->getVersion() !== $manifest->getVersion()) {
                throw new RuntimeException('Staged manifest does not match the verified archive.');
            }
            $this->makeDirectory($stagedPublicPath);
            foreach (array_filter([$manifest->getFrontendBundle(), $manifest->getFrontendStyles()]) as $asset) {
                $asset = $this->validateRelativePath($asset);
                $source = $stagedPath . '/' . $asset;
                if (!is_file($source)) {
                    throw new RuntimeException("Declared frontend asset is missing: {$asset}");
                }
                $destination = $stagedPublicPath . '/' . $asset;
                $this->makeDirectory(dirname($destination));
                if (!copy($source, $destination)) {
                    throw new RuntimeException("Could not stage frontend asset: {$asset}");
                }
            }
            $migrations = $manifest->getMigrationsPath();
            if ($migrations !== '') {
                $migrations = $this->validateRelativePath($migrations);
                if (!is_dir($stagedPath . '/' . $migrations)) {
                    throw new RuntimeException("Declared migrations directory is missing: {$migrations}");
                }
            }

            $this->info("Installing to {$targetPath}...");
            if (is_dir($targetPath)) {
                $this->moveDirectory($targetPath, $backupPath);
                $oldFilesMoved = true;
            }
            $this->moveDirectory($stagedPath, $targetPath);
            $newFilesMoved = true;
            if (is_dir($publicPath)) {
                $this->moveDirectory($publicPath, $backupPublicPath);
                $oldPublicMoved = true;
            }
            $this->moveDirectory($stagedPublicPath, $publicPath);
            $newPublicMoved = true;

            if (!$this->option('no-migrate') && $migrations !== '') {
                $migrationStarted = true;
                $ran = $migrationManager->migrate($extensionId, $targetPath . '/' . $migrations);
                if ($ran !== []) {
                    $this->info('Ran ' . count($ran) . ' migration(s).');
                }
            }

            $registrationStarted = true;
            // StateStore owns both its file lock and the database transaction.
            $manager->registerExtension($extensionId, $manifest->getVersion(), $manifest, $wasEnabled);
        } catch (\Throwable $e) {
            $recoveryErrors = [];
            // Restore each prior tree even if restoring the other one fails.
            foreach ([
                [$publicPath, $backupPublicPath, $oldPublicMoved, $newPublicMoved],
                [$targetPath, $backupPath, $oldFilesMoved, $newFilesMoved],
            ] as [$live, $backup, $oldMoved, $newMoved]) {
                try {
                    if ($newMoved) {
                        $this->cleanupPath($live);
                    }
                    if ($oldMoved) {
                        $this->moveDirectory($backup, $live);
                    }
                } catch (\Throwable $recoveryError) {
                    $recoveryErrors[] = "files at {$backup}: " . $recoveryError->getMessage();
                }
            }
            // Re-project metadata only after the old manifest is back on disk.
            if ($registrationStarted) {
                try {
                    if ($previousVersion === null) {
                        $manager->unregisterExtension($extensionId);
                    } else {
                        $manager->registerExtension($extensionId, $previousVersion, null, $wasEnabled);
                    }
                } catch (\Throwable $recoveryError) {
                    $recoveryErrors[] = 'manifest: ' . $recoveryError->getMessage();
                }
            }
            $this->error("Installation failed: {$e->getMessage()}");
            if ($migrationStarted) {
                $this->warn('Previous files and public assets were restored where possible. Completed migrations may have changed the database; inspect notur_migrations and recover data manually before retrying.');
            }
            foreach ($recoveryErrors as $recoveryError) {
                $this->error("Recovery failed ({$recoveryError}); retained backup paths for manual recovery.");
            }
            return 1;
        } finally {
            $this->cleanupPath($stagedPath);
            $this->cleanupPath($stagedPublicPath);
        }

        foreach ([$backupPath, $backupPublicPath] as $backup) {
            try {
                $this->cleanupPath($backup);
                if (file_exists($backup) || is_link($backup)) {
                    $this->warn("Upgrade completed, but old files remain at {$backup}.");
                }
            } catch (\Throwable $e) {
                $this->warn("Upgrade completed, but old files at {$backup} could not be removed: {$e->getMessage()}");
            }
        }

        // Fire event
        if ($previousVersion !== null && $previousVersion !== $manifest->getVersion()) {
            ExtensionUpdated::dispatch($extensionId, $previousVersion, $manifest->getVersion());
        } else {
            ExtensionInstalled::dispatch($extensionId, $manifest->getVersion());
        }

        // Clear caches
        $this->clearNoturCaches();

        $state = $wasEnabled ? 'enabled' : 'disabled';
        $this->info("Extension '{$extensionId}' v{$manifest->getVersion()} installed and {$state}.");

        return 0;
    }

    private function validateRelativePath(string $path): string
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, "\\")
            || preg_match('#(^|/)\.\.?(/|$)#', $path) || str_contains($path, "\0")) {
            throw new RuntimeException("Invalid package path: {$path}");
        }
        return $path;
    }

    private function makeDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
            throw new RuntimeException("Could not create directory: {$path}");
        }
    }

    private function moveDirectory(string $from, string $to): void
    {
        $this->makeDirectory(dirname($to));
        if (!rename($from, $to)) {
            throw new RuntimeException("Could not move {$from} to {$to}");
        }
    }

    private function copyStagedDirectory(string $source, string $destination): void
    {
        $this->makeDirectory($destination);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $item) {
            if ($item->isLink()) {
                throw new RuntimeException('Package contains a symbolic link: ' . $iterator->getSubPathname());
            }
            $target = $destination . '/' . $iterator->getSubPathname();
            if ($item->isDir()) {
                $this->makeDirectory($target);
            } elseif (!$item->isFile() || !copy($item->getPathname(), $target)) {
                throw new RuntimeException('Could not stage package file: ' . $iterator->getSubPathname());
            }
        }
    }

}
