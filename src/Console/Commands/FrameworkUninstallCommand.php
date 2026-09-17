<?php

declare(strict_types=1);

namespace Notur\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Notur\Console\Concerns\ManagesFilesystem;

class FrameworkUninstallCommand extends Command
{
    use ManagesFilesystem;

    protected $signature = 'notur:framework:uninstall
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
        try {
            $backup = app(\Notur\Support\LifecycleBackup::class)->create([
                'resources' => base_path('resources'),
                'notur' => base_path('notur'),
                'extensions' => \Notur\Support\ExtensionPath::extensionsDir(),
                'public' => base_path('public/notur'),
                'public-assets' => base_path('public/assets'),
                'composer.json' => base_path('composer.json'),
                'composer.lock' => base_path('composer.lock'),
                'config.php' => config_path('notur.php'),
            ], 'uninstall');
            $this->info("File backup: {$backup} (database not included).");
            $this->restoreReactPatches();
            // A failed build must not remove the running framework or its data.
            if ($this->rebuildFrontend() !== 0) {
                return 1;
            }
            $pending = [];
            // Keep DB-only legacy entries, then include authoritative JSON state.
            foreach (\Notur\Models\InstalledExtension::all() as $extension) {
                // Dependents are removed first by retrying dependency-blocked entries.
                $pending[$extension->extension_id] = true;
            }
            $state = app(\Notur\ExtensionManager::class)->reconcileState();
            foreach (array_keys($state['extensions'] ?? []) as $id) {
                $pending[$id] = true;
            }
            while ($pending !== []) {
                $progress = false;
                foreach (array_keys($pending) as $id) {
                    try {
                        app(\Notur\ExtensionManager::class)->assertCanRemove($id);
                    } catch (\Notur\Exceptions\DependencyResolutionException) {
                        continue;
                    }
                    if ($this->call('notur:remove', ['extension' => $id, '--force' => true]) !== 0) {
                        return 1;
                    }
                    unset($pending[$id]);
                    $progress = true;
                }
                if (!$progress) {
                    throw new \RuntimeException('Cannot resolve extension removal order. Framework retained.');
                }
            }
            $this->rollbackMigrations();
            $this->removeBladeInjection();
            $this->removeNoturDirectories();
            if (is_file(config_path('notur.php')) && !unlink(config_path('notur.php'))) {
                throw new \RuntimeException('Could not remove published Notur configuration.');
            }
            if ($this->call('optimize:clear') !== 0 || $this->runComposerRemove() !== 0) {
                return 1;
            }
        } catch (\Throwable $e) {
            $this->error('Uninstall stopped: ' . $e->getMessage());
            return 1;
        }

        $this->newLine();
        $this->info('Notur has been uninstalled. File backups were retained in storage/notur/backups.');
        return 0;
    }

    /**
     * Step 1: Restore patched React source files.
     *
     * Applies reverse patches; refuses conflicts to preserve subsequent local edits.
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
                        sprintf('cd %s && patch --batch --forward --dry-run -p1 < %s 2>/dev/null', escapeshellarg($panelDir), escapeshellarg($patch)),
                        $output,
                        $dryRun,
                    );

                    if ($dryRun === 0) {
                        $result = 0;
                        exec(
                            sprintf('cd %s && patch --batch --forward -p1 < %s 2>/dev/null', escapeshellarg($panelDir), escapeshellarg($patch)),
                            $output,
                            $result,
                        );

                        if ($result !== 0) {
                            $this->warn("  Failed to apply reverse patch: {$patchName}");
                            $allApplied = false;
                        }
                    } else {
                        $alreadyClean = 0;
                        exec(sprintf('cd %s && patch --batch --reverse --dry-run -p1 < %s 2>/dev/null', escapeshellarg($panelDir), escapeshellarg($patch)), $output, $alreadyClean);
                        if ($alreadyClean !== 0) {
                            $this->warn("  Reverse patch conflicts with source: {$patchName}");
                            $allApplied = false;
                        }
                    }
                }

                if ($allApplied) {
                    $reversePatchesApplied = true;
                    $this->info('  Reverse patches applied successfully.');
                }
            }
        }

        // Do not overwrite unrelated panel edits from old backup copies.
        if (!$reversePatchesApplied) {
            throw new \RuntimeException('React patches could not be safely removed. Resolve source conflicts using the retained backup and retry.');
        }
    }

    /**
     * Locate the patch directory from vendor or installer.
     */
    private function findPatchDirectory(): ?string
    {
        $patchVersion = $this->detectPatchVersion();

        $candidates = [
            base_path("vendor/notur/notur/installer/patches/{$patchVersion}"),
            dirname(__DIR__, 3) . "/installer/patches/{$patchVersion}",
            base_path('vendor/notur/notur/installer/patches/v1.12'),
            dirname(__DIR__, 3) . '/installer/patches/v1.12',
        ];

        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function detectPatchVersion(): string
    {
        // The panel is the root Composer package, so it is absent from the lock.
        $panelVersion = ltrim((string) config('app.version', ''), 'v');
        if (in_array($panelVersion, ['1.15.0', '1.15.1'], true)) {
            return 'v1.15';
        }

        $lockFile = base_path('composer.lock');
        if (!file_exists($lockFile)) {
            Log::warning('Notur uninstall: composer.lock not found; assuming v1.12 patches.');
            return 'v1.12';
        }

        $decoded = json_decode((string) file_get_contents($lockFile), true);
        if (!is_array($decoded)) {
            Log::warning('Notur uninstall: composer.lock could not be parsed; assuming v1.12 patches.');
            return 'v1.12';
        }

        $packages = array_merge($decoded['packages'] ?? [], $decoded['packages-dev'] ?? []);
        foreach ($packages as $package) {
            if (!is_array($package) || ($package['name'] ?? null) !== 'pterodactyl/panel') {
                continue;
            }

            $version = (string) ($package['version'] ?? $package['pretty_version'] ?? '');
            $version = ltrim($version, 'v');
            if (in_array($version, ['1.15.0', '1.15.1'], true)) {
                return 'v1.15';
            }
            if (str_starts_with($version, '1.12.')) {
                return 'v1.12';
            }

            // Found pterodactyl/panel but not a supported release. install.sh now
            // hard-fails on unsupported versions, so this branch typically only fires for
            // a manually-installed Notur on an unsupported panel. Reverse
            // patches will likely fail and stop uninstall for manual recovery.
            Log::warning(sprintf(
                'Notur uninstall: pterodactyl/panel version "%s" is unsupported; reverse patches may not apply cleanly.',
                $version,
            ));
            return 'v1.12';
        }

        Log::warning('Notur uninstall: pterodactyl/panel not present in composer.lock; assuming v1.12 patches.');
        return 'v1.12';
    }

    /**
     * Step 2: Roll back Notur framework database migrations.
     */
    private function rollbackMigrations(): void
    {
        $this->info('Step 2/6: Rolling back Notur database migrations...');

        // Remove extension records before the remote push keys they reference.
        $tables = ['notur_activity_logs', 'notur_settings', 'notur_migrations', 'notur_extensions', 'notur_remote_push_keys'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::drop($table);
                $this->line("  Dropped table: {$table}");
            }
        }

        // Also clean up Laravel's migrations table
        if (Schema::hasTable('migrations')) {
            \Illuminate\Support\Facades\DB::table('migrations')
                ->whereIn('migration', array_map(static fn (string $path): string => basename($path, '.php'), glob(dirname(__DIR__, 3) . '/database/migrations/*.php')))
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
    private function rebuildFrontend(): int
    {
        $this->info('Step 6/6: Rebuilding frontend assets...');

        $panelDir = base_path();
        $result = 0;
        $packageManager = $this->detectPackageManager($panelDir);

        if ($packageManager === null) {
            $this->warn('  No supported package manager found (bun, pnpm, yarn, npm).');
            $this->warn('  Frontend rebuild skipped. Run manually once a package manager is installed:');
            $this->warn('  bun/pnpm/yarn/npm run build:production');
            return 1;
        }

        $command = match ($packageManager) {
            'bun' => 'bun run build:production',
            'pnpm' => 'pnpm run build:production',
            'yarn' => 'yarn run build:production',
            'npm' => 'npm run build:production',
            default => null,
        };

        if ($command === null) {
            $this->warn('  Frontend rebuild skipped due to unknown package manager.');
            return 1;
        }

        exec(
            sprintf('cd %s && NODE_OPTIONS=--openssl-legacy-provider %s 2>&1', escapeshellarg($panelDir), $command),
            $output,
            $result,
        );

        if ($result !== 0) {
            $this->warn('  Frontend rebuild failed. Run manually:');
            $this->warn("  NODE_OPTIONS=--openssl-legacy-provider {$command}");
            return 1;
        } else {
            $this->info('  Frontend rebuilt successfully.');
            return 0;
        }
    }

    /**
     * Detect preferred package manager (bun > pnpm > yarn > npm).
     */
    private function detectPackageManager(string $panelDir): ?string
    {
        $env = getenv('PKG_MANAGER');
        if (is_string($env) && in_array($env, ['bun', 'pnpm', 'yarn', 'npm'], true)) {
            if ($this->commandExists($env)) {
                return $env;
            }
            $this->warn("  PKG_MANAGER={$env} was set, but {$env} is not available. Auto-detecting...");
        }

        $lockfileMap = [
            'bun.lockb' => 'bun',
            'bun.lock' => 'bun',
            'pnpm-lock.yaml' => 'pnpm',
            'yarn.lock' => 'yarn',
            'package-lock.json' => 'npm',
        ];

        foreach ($lockfileMap as $file => $manager) {
            if (file_exists($panelDir . '/' . $file) && $this->commandExists($manager)) {
                return $manager;
            }
        }

        foreach (['bun', 'pnpm', 'yarn', 'npm'] as $manager) {
            if ($this->commandExists($manager)) {
                return $manager;
            }
        }

        return null;
    }

    private function commandExists(string $command): bool
    {
        $result = 0;
        exec(sprintf('command -v %s >/dev/null 2>&1', escapeshellarg($command)), $output, $result);

        return $result === 0;
    }

}
