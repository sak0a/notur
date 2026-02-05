<?php

declare(strict_types=1);

namespace Notur\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class UninstallCommand extends Command
{
    protected $signature = 'notur:uninstall
        {--confirm : Skip interactive confirmation}';

    protected $description = 'Completely remove the Notur extension framework from this Pterodactyl panel';

    public function handle(): int
    {
        $this->warn('This will completely remove the Notur extension framework.');
        $this->warn('All extensions, data, and frontend patches will be removed.');

        if (!$this->option('confirm') && !$this->confirm('Are you sure you want to uninstall Notur?')) {
            $this->info('Uninstall cancelled.');
            return 0;
        }

        $this->info('Uninstalling Notur...');
        $exitCode = 0;

        // Step 1: Restore patched React files
        $this->restoreReactPatches();

        // Step 2: Roll back all Notur database migrations
        $this->rollbackMigrations();

        // Step 3: Remove Blade injection
        $this->removeBladeInjection();

        // Step 4: Delete notur/ directory and public/notur/ assets
        $this->removeNoturDirectories();

        // Step 5: Run composer remove
        $exitCode = $this->runComposerRemove();

        // Step 6: Trigger frontend rebuild
        $this->rebuildFrontend();

        $this->newLine();
        $this->info('Notur has been uninstalled.');

        return $exitCode;
    }

    /**
     * Step 1: Restore patched React source files.
     *
     * Attempts reverse patches first. Falls back to .notur-backup copies.
     */
    private function restoreReactPatches(): void
    {
        $this->info('Step 1/6: Restoring patched React files...');

        $panelDir = base_path();
        $patchDir = $this->findPatchDirectory();
        $reversePatchesApplied = false;

        // Try reverse patches first
        if ($patchDir !== null) {
            $reversePatches = glob($patchDir . '/*.reverse.patch');

            if (!empty($reversePatches)) {
                $allApplied = true;

                foreach ($reversePatches as $patch) {
                    $patchName = basename($patch);
                    $this->line("  Applying reverse patch: {$patchName}");

                    $dryRun = 0;
                    exec(
                        sprintf('cd %s && patch --dry-run -p1 < %s 2>/dev/null', escapeshellarg($panelDir), escapeshellarg($patch)),
                        $output,
                        $dryRun,
                    );

                    if ($dryRun === 0) {
                        $result = 0;
                        exec(
                            sprintf('cd %s && patch -p1 < %s 2>/dev/null', escapeshellarg($panelDir), escapeshellarg($patch)),
                            $output,
                            $result,
                        );

                        if ($result !== 0) {
                            $this->warn("  Failed to apply reverse patch: {$patchName}");
                            $allApplied = false;
                        }
                    } else {
                        $this->warn("  Reverse patch cannot be applied cleanly: {$patchName}");
                        $allApplied = false;
                    }
                }

                if ($allApplied) {
                    $reversePatchesApplied = true;
                    $this->info('  Reverse patches applied successfully.');
                }
            }
        }

        // Fall back to .notur-backup copies
        if (!$reversePatchesApplied) {
            $this->line('  Falling back to .notur-backup copies...');
            $this->restoreFromBackups($panelDir);
        }
    }

    /**
     * Locate the patch directory from vendor or installer.
     */
    private function findPatchDirectory(): ?string
    {
        $vendorPath = base_path('vendor/notur/notur/installer/patches/v1.11');
        if (is_dir($vendorPath)) {
            return $vendorPath;
        }

        // Try alongside the install script
        $installerPath = dirname(__DIR__, 3) . '/installer/patches/v1.11';
        if (is_dir($installerPath)) {
            return $installerPath;
        }

        return null;
    }

    /**
     * Restore files from .notur-backup copies.
     */
    private function restoreFromBackups(string $panelDir): void
    {
        $backupFiles = [
            'resources/scripts/routers/routes.ts',
            'resources/scripts/routers/ServerRouter.tsx',
            'resources/scripts/routers/DashboardRouter.tsx',
            'resources/scripts/components/NavigationBar.tsx',
        ];

        $restored = 0;

        foreach ($backupFiles as $relativePath) {
            $backupFile = $panelDir . '/' . $relativePath . '.notur-backup';

            if (file_exists($backupFile)) {
                $targetFile = $panelDir . '/' . $relativePath;
                copy($backupFile, $targetFile);
                unlink($backupFile);
                $this->line("  Restored: {$relativePath}");
                $restored++;
            }
        }

        // Also check Blade backup
        $bladeFiles = [
            'resources/views/layouts/scripts.blade.php',
            'resources/views/templates/wrapper.blade.php',
        ];

        foreach ($bladeFiles as $relativePath) {
            $backupFile = $panelDir . '/' . $relativePath . '.notur-backup';
            if (file_exists($backupFile)) {
                copy($backupFile, $panelDir . '/' . $relativePath);
                unlink($backupFile);
                $this->line("  Restored: {$relativePath}");
                $restored++;
            }
        }

        if ($restored === 0) {
            $this->warn('  No backup files found. React files may need manual cleanup.');
        } else {
            $this->info("  Restored {$restored} file(s) from backups.");
        }
    }

    /**
     * Step 2: Roll back Notur framework database migrations.
     */
    private function rollbackMigrations(): void
    {
        $this->info('Step 2/6: Rolling back Notur database migrations...');

        $tables = ['notur_activity_logs', 'notur_settings', 'notur_migrations', 'notur_extensions'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::drop($table);
                $this->line("  Dropped table: {$table}");
            }
        }

        // Also clean up Laravel's migrations table
        if (Schema::hasTable('migrations')) {
            \Illuminate\Support\Facades\DB::table('migrations')
                ->where('migration', 'like', '%notur%')
                ->delete();
            $this->line('  Cleaned Notur entries from migrations table.');
        }

        $remainingTables = array_values(array_filter($tables, static fn (string $table): bool => Schema::hasTable($table)));

        if (!empty($remainingTables)) {
            $this->warn('  Some Notur tables still exist after uninstall: ' . implode(', ', $remainingTables));
        } else {
            $this->info('  Verified Notur migration tables were removed.');
        }

        $this->info('  Database cleanup complete.');
    }

    /**
     * Step 3: Remove Blade injection.
     */
    private function removeBladeInjection(): void
    {
        $this->info('Step 3/6: Removing Blade injection...');

        $bladeFiles = [
            base_path('resources/views/layouts/scripts.blade.php'),
            base_path('resources/views/templates/wrapper.blade.php'),
        ];

        $cleaned = false;

        foreach ($bladeFiles as $file) {
            if (!file_exists($file)) {
                continue;
            }

            $content = file_get_contents($file);

            if (str_contains($content, 'notur::scripts')) {
                // Remove the @include line (and any surrounding blank line it created)
                $content = preg_replace(
                    '/\n?\s*@include\([\'"]notur::scripts[\'"]\)\s*\n?/',
                    "\n",
                    $content,
                );

                file_put_contents($file, $content);
                $this->line("  Cleaned: {$file}");
                $cleaned = true;
            }
        }

        if (!$cleaned) {
            $this->line('  No Blade injection found (already clean).');
        }
    }

    /**
     * Step 4: Delete notur/ directory and public/notur/ assets.
     */
    private function removeNoturDirectories(): void
    {
        $this->info('Step 4/6: Removing Notur directories...');

        $directories = [
            base_path('notur'),
            base_path('public/notur'),
        ];

        foreach ($directories as $dir) {
            if (is_dir($dir)) {
                $this->deleteDirectory($dir);
                $this->line("  Deleted: {$dir}");
            }
        }

        $this->info('  Directories removed.');
    }

    /**
     * Step 5: Run composer remove.
     */
    private function runComposerRemove(): int
    {
        $this->info('Step 5/6: Running composer remove notur/notur...');

        $panelDir = base_path();
        $result = 0;

        exec(
            sprintf('cd %s && composer remove notur/notur --no-interaction 2>&1', escapeshellarg($panelDir)),
            $output,
            $result,
        );

        if ($result !== 0) {
            $this->warn('  Composer remove failed. You may need to run it manually:');
            $this->warn('  composer remove notur/notur');
            foreach ($output as $line) {
                $this->line("  > {$line}");
            }
            return 1;
        }

        $this->info('  Composer package removed.');
        return 0;
    }

    /**
     * Step 6: Trigger frontend rebuild.
     */
    private function rebuildFrontend(): void
    {
        $this->info('Step 6/6: Rebuilding frontend assets...');

        $panelDir = base_path();
        $result = 0;

        // Use bun for frontend rebuild
        exec(
            sprintf('cd %s && bun run build:production 2>&1', escapeshellarg($panelDir)),
            $output,
            $result,
        );

        if ($result !== 0) {
            $this->warn('  Frontend rebuild failed. Run manually:');
            $this->warn('  bun run build:production');
        } else {
            $this->info('  Frontend rebuilt successfully.');
        }
    }

    /**
     * Recursively delete a directory.
     */
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
