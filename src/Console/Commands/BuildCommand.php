<?php

declare(strict_types=1);

namespace Notur\Console\Commands;

use Illuminate\Console\Command;
use Notur\ExtensionManifest;

class BuildCommand extends Command
{
    protected $signature = 'notur:build
        {path? : Path to the extension to build (defaults to current directory)}
        {--production : Build for production}';

    protected $description = 'Build an extension\'s frontend bundle';

    public function handle(): int
    {
        $path = $this->argument('path') ?? getcwd();
        $path = realpath($path);

        if (!$path || !is_dir($path)) {
            $this->error("Path does not exist.");
            return 1;
        }

        try {
            $manifest = ExtensionManifest::load($path);
        } catch (\Throwable $e) {
            $this->error("Invalid extension: {$e->getMessage()}");
            return 1;
        }

        $this->info("Building {$manifest->getName()} v{$manifest->getVersion()}...");

        // Check for package.json
        if (!file_exists($path . '/package.json')) {
            $this->warn('No package.json found — skipping frontend build.');
            return 0;
        }

        // Install dependencies
        $this->info('Installing dependencies...');
        $result = $this->runProcess('bun install', $path);
        if ($result !== 0) {
            $this->error('Failed to install dependencies.');
            return 1;
        }

        // Build
        $mode = $this->option('production') ? 'production' : 'development';
        $this->info("Building frontend ({$mode})...");

        $webpackConfig = $path . '/webpack.config.js';
        if (!file_exists($webpackConfig)) {
            // Use the SDK's base webpack config
            $sdkConfig = dirname(__DIR__, 3) . '/sdk/webpack.extension.config.js';
            if (file_exists($sdkConfig)) {
                $webpackConfig = $sdkConfig;
            }
        }

        $cmd = "bunx webpack --mode {$mode} --config " . escapeshellarg($webpackConfig);
        $result = $this->runProcess($cmd, $path);

        if ($result !== 0) {
            $this->error('Build failed.');
            return 1;
        }

        $this->info('Build complete.');

        return 0;
    }

    private function runProcess(string $command, string $cwd): int
    {
        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $cwd,
        );

        if (!is_resource($process)) {
            return 1;
        }

        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($output) {
            $this->line($output);
        }
        if ($errors && $exitCode !== 0) {
            $this->error($errors);
        }

        return $exitCode;
    }
}
