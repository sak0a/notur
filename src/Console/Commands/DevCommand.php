<?php

declare(strict_types=1);

namespace Notur\Console\Commands;

use Illuminate\Console\Command;
use Notur\ExtensionManifest;
use Notur\Support\ExtensionPath;

class DevCommand extends Command
{
    protected $signature = 'notur:dev
        {path : Path to the extension being developed}
        {--link : Create a symlink instead of copying}';

    protected $description = 'Link a local extension for development (watch mode)';

    public function handle(): int
    {
        $devPath = realpath($this->argument('path'));

        if (!$devPath || !is_dir($devPath)) {
            $this->error("Path does not exist: {$this->argument('path')}");
            return 1;
        }

        try {
            $manifest = ExtensionManifest::load($devPath);
        } catch (\Throwable $e) {
            $this->error("Invalid extension: {$e->getMessage()}");
            return 1;
        }

        $extensionId = $manifest->getId();
        $targetPath = ExtensionPath::base($extensionId);

        // Create symlink
        $parentDir = dirname($targetPath);
        if (!is_dir($parentDir)) {
            mkdir($parentDir, 0755, true);
        }

        if (is_link($targetPath)) {
            unlink($targetPath);
        } elseif (is_dir($targetPath)) {
            $this->error("Extension '{$extensionId}' is already installed (not a symlink). Remove it first.");
            return 1;
        }

        symlink($devPath, $targetPath);
        $this->info("Linked {$devPath} → {$targetPath}");

        // Also symlink frontend bundle to public
        $bundle = $manifest->getFrontendBundle();
        if ($bundle) {
            $publicPath = ExtensionPath::public($extensionId);
            if (!is_dir($publicPath)) {
                mkdir($publicPath, 0755, true);
            }

            $bundleSource = $devPath . '/' . $bundle;
            $bundleTarget = $publicPath . '/' . basename($bundle);

            if (file_exists($bundleSource)) {
                if (is_link($bundleTarget)) {
                    unlink($bundleTarget);
                }
                symlink($bundleSource, $bundleTarget);
                $this->info("Linked frontend bundle.");
            }
        }

        // Register in manifest
        app(\Notur\ExtensionManager::class)->registerExtension($extensionId, $manifest->getVersion());

        $this->info("Extension '{$extensionId}' is now in development mode.");
        $this->info("Changes to PHP files will take effect immediately.");
        $this->info("For frontend changes, rebuild the extension's JS bundle.");

        return 0;
    }
}
