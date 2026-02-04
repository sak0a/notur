<?php

declare(strict_types=1);

namespace Notur\Console\Commands;

use Illuminate\Console\Command;
use Notur\Events\ExtensionInstalled;
use Notur\Events\ExtensionUpdated;
use Notur\ExtensionManager;
use Notur\ExtensionManifest;
use Notur\MigrationManager;
use Notur\Models\InstalledExtension;
use Notur\Support\ExtensionPath;
use Notur\Support\NoturArchive;
use Notur\Support\RegistryClient;
use Notur\Support\SignatureVerifier;

class InstallCommand extends Command
{
    protected $signature = 'notur:install
        {extension : The extension ID (vendor/name) or path to a .notur file}
        {--force : Overwrite if already installed}
        {--no-migrate : Skip running migrations}';

    protected $description = 'Install a Notur extension';

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
    ): int {
        $this->info("Installing from local file: {$filePath}");

        // Verify signature if required
        if (config('notur.require_signatures')) {
            $sigFile = $filePath . '.sig';
            if (!file_exists($sigFile)) {
                $this->error('Signature file not found and signatures are required.');
                return 1;
            }

            $signature = file_get_contents($sigFile);
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
            NoturArchive::unpack($filePath, $tmpDir, true);
        } catch (\Throwable $e) {
            $this->error("Archive extraction failed: {$e->getMessage()}");
            return 1;
        }

        $this->info('Archive extracted and checksums verified.');

        // Load manifest
        $manifest = ExtensionManifest::load($tmpDir);
        $extensionId = $manifest->getId();

        return $this->finalizeInstall($extensionId, $tmpDir, $manifest, $manager, $migrationManager);
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

        return $this->installFromFile($tmpFile, $manager, $migrationManager, $verifier);
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
        if ($existing && !$this->option('force')) {
            $this->error("Extension '{$extensionId}' is already installed. Use --force to overwrite.");
            return 1;
        }
        $previousVersion = $existing?->version;

        $targetPath = ExtensionPath::base($extensionId);

        // Copy files
        $this->info("Installing to {$targetPath}...");

        if (is_dir($targetPath)) {
            $this->deleteDirectory($targetPath);
        }

        $this->copyDirectory($sourcePath, $targetPath);

        // Copy frontend bundle to public
        $bundle = $manifest->getFrontendBundle();
        $styles = $manifest->getFrontendStyles();
        $publicPath = ExtensionPath::public($extensionId);

        if ($bundle || $styles) {
            if (!is_dir($publicPath)) {
                mkdir($publicPath, 0755, true);
            }

            if ($bundle && file_exists($targetPath . '/' . $bundle)) {
                $bundleTarget = $publicPath . '/' . ltrim($bundle, '/');
                $bundleDir = dirname($bundleTarget);
                if (!is_dir($bundleDir)) {
                    mkdir($bundleDir, 0755, true);
                }
                copy($targetPath . '/' . $bundle, $bundleTarget);
            }

            if ($styles && file_exists($targetPath . '/' . $styles)) {
                $stylesTarget = $publicPath . '/' . ltrim($styles, '/');
                $stylesDir = dirname($stylesTarget);
                if (!is_dir($stylesDir)) {
                    mkdir($stylesDir, 0755, true);
                }
                copy($targetPath . '/' . $styles, $stylesTarget);
            }
        }

        // Run migrations
        if (!$this->option('no-migrate') && $manifest->getMigrationsPath()) {
            $migrationsPath = $targetPath . '/' . $manifest->getMigrationsPath();
            $ran = $migrationManager->migrate($extensionId, $migrationsPath);

            if (!empty($ran)) {
                $this->info('Ran ' . count($ran) . ' migration(s).');
            }
        }

        // Register in manifest
        $manager->registerExtension($extensionId, $manifest->getVersion());

        // Save to database
        InstalledExtension::updateOrCreate(
            ['extension_id' => $extensionId],
            [
                'name' => $manifest->getName(),
                'version' => $manifest->getVersion(),
                'enabled' => true,
                'manifest' => $manifest->getRaw(),
            ],
        );

        // Fire event
        if ($previousVersion !== null && $previousVersion !== $manifest->getVersion()) {
            ExtensionUpdated::dispatch($extensionId, $previousVersion, $manifest->getVersion());
        } else {
            ExtensionInstalled::dispatch($extensionId, $manifest->getVersion());
        }

        // Clear caches
        $this->call('cache:clear');
        $this->call('view:clear');

        $this->info("Extension '{$extensionId}' v{$manifest->getVersion()} installed and enabled.");

        return 0;
    }

    private function copyDirectory(string $source, string $dest): void
    {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $target = $dest . '/' . $iterator->getSubPathname();
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                copy($item->getPathname(), $target);
            }
        }
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
