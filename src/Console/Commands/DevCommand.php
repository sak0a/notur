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
        {--link : Create a symlink instead of copying}
        {--watch : Watch and rebuild the frontend bundle}
        {--watch-bridge : Also watch the Notur bridge runtime}';

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
        $watch = (bool) $this->option('watch');
        $watchBridge = (bool) $this->option('watch-bridge');

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
            $bundleTarget = $publicPath . '/' . ltrim($bundle, '/');
            $bundleDir = dirname($bundleTarget);
            if (!is_dir($bundleDir)) {
                mkdir($bundleDir, 0755, true);
            }

            if (is_link($bundleTarget)) {
                unlink($bundleTarget);
            }

            if (file_exists($bundleSource) || $watch) {
                @symlink($bundleSource, $bundleTarget);
                if (file_exists($bundleSource)) {
                    $this->info('Linked frontend bundle.');
                } else {
                    $this->warn('Frontend bundle not found yet — symlink created for watch mode.');
                }
            }
        }

        // Register in manifest
        app(\Notur\ExtensionManager::class)->registerExtension($extensionId, $manifest->getVersion());

        $this->info("Extension '{$extensionId}' is now in development mode.");
        $this->info('Changes to PHP files will take effect immediately.');
        $this->info('For frontend changes, rebuild the extension\'s JS bundle.');

        if ($watch) {
            return $this->runWatchers($devPath, $watchBridge);
        }

        return 0;
    }

    private function runWatchers(string $devPath, bool $watchBridge): int
    {
        $watchCommand = $this->resolveWatchCommand($devPath);
        if ($watchCommand === null) {
            $this->warn('No frontend watch command found. Ensure package.json and a dev script or webpack config are present.');
            return 0;
        }

        $this->info('Starting frontend watcher...');
        $processes = [];
        $processes[] = $this->startProcess($watchCommand, $devPath, 'Extension watcher');

        if ($watchBridge) {
            $bridgeProcess = $this->startBridgeWatcher();
            if ($bridgeProcess) {
                $processes[] = $bridgeProcess;
            }
        }

        $this->line('Watching for changes. Press Ctrl+C to stop.');

        return $this->waitForProcesses($processes);
    }

    private function resolveWatchCommand(string $devPath): ?string
    {
        $packageJson = $devPath . '/package.json';
        if (!file_exists($packageJson)) {
            return null;
        }

        $package = json_decode(file_get_contents($packageJson), true);
        if (is_array($package) && isset($package['scripts']['dev'])) {
            return 'bun run dev';
        }

        $webpackConfig = $devPath . '/webpack.config.js';
        if (!file_exists($webpackConfig)) {
            $sdkConfig = dirname(__DIR__, 3) . '/sdk/webpack.extension.config.js';
            if (file_exists($sdkConfig)) {
                $webpackConfig = $sdkConfig;
            }
        }

        if (file_exists($webpackConfig)) {
            return 'bunx webpack --mode development --watch --config ' . escapeshellarg($webpackConfig);
        }

        return null;
    }

    private function startBridgeWatcher(): ?array
    {
        $noturRoot = base_path('vendor/notur/notur');
        $packageJson = $noturRoot . '/package.json';

        if (!file_exists($packageJson)) {
            $this->warn('Notur bridge sources not found — skipping bridge watch.');
            return null;
        }

        $publicBridge = ExtensionPath::bridgeJs();
        $bridgeSource = $noturRoot . '/bridge/dist/bridge.js';

        if (file_exists($publicBridge) && !is_link($publicBridge)) {
            $this->warn('public/notur/bridge.js exists and is not a symlink; skipping auto-link.');
        } else {
            if (is_link($publicBridge)) {
                unlink($publicBridge);
            }
            if (!is_dir(dirname($publicBridge))) {
                mkdir(dirname($publicBridge), 0755, true);
            }
            @symlink($bridgeSource, $publicBridge);
            $this->info('Linked bridge runtime for watch mode.');
        }

        $this->info('Starting bridge watcher...');
        return $this->startProcess('bun run dev:bridge', $noturRoot, 'Bridge watcher');
    }

    private function startProcess(string $command, string $cwd, string $label): array
    {
        $process = proc_open(
            $command,
            [0 => STDIN, 1 => STDOUT, 2 => STDERR],
            $pipes,
            $cwd,
        );

        if (!is_resource($process)) {
            $this->error("Failed to start {$label}.");
            return ['process' => null, 'label' => $label];
        }

        return ['process' => $process, 'label' => $label];
    }

    private function waitForProcesses(array $processes): int
    {
        $running = true;
        $exitCode = 0;

        while ($running) {
            $running = false;

            foreach ($processes as $entry) {
                $proc = $entry['process'];
                if (!$proc) {
                    continue;
                }

                $status = proc_get_status($proc);
                if ($status['running']) {
                    $running = true;
                    continue;
                }

                $exitCode = $status['exitcode'] ?? 0;
                $running = false;
                break;
            }

            if ($running) {
                usleep(200_000);
            }
        }

        foreach ($processes as $entry) {
            $proc = $entry['process'];
            if (!$proc) {
                continue;
            }

            $status = proc_get_status($proc);
            if ($status['running']) {
                proc_terminate($proc);
            }
        }

        foreach ($processes as $entry) {
            if (is_resource($entry['process'])) {
                proc_close($entry['process']);
            }
        }

        return $exitCode;
    }
}
