<?php

declare(strict_types=1);

namespace Notur\Console\Commands;

use Illuminate\Console\Command;
use Notur\ExtensionManifest;
use Notur\Support\PackageManagerResolver;

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
        $packageJsonPath = $path . '/package.json';
        if (!file_exists($packageJsonPath)) {
            $this->warn('No package.json found — skipping frontend build.');
            return 0;
        }

        $resolver = new PackageManagerResolver();
        $packageManager = $resolver->detect($path);
        if ($packageManager === null) {
            $this->error('No supported package manager found (bun, pnpm, yarn, npm).');
            return 1;
        }

        $package = json_decode((string) file_get_contents($packageJsonPath), true);
        $scripts = is_array($package) && isset($package['scripts']) && is_array($package['scripts'])
            ? $package['scripts']
            : [];

        // Install dependencies
        $this->info("Installing dependencies with {$packageManager}...");
        $result = $this->runProcess($resolver->installCommand($packageManager), $path, ['NODE_ENV' => 'development', 'npm_config_production' => 'false', 'npm_config_omit' => '']);
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

        if ($mode === 'production' && isset($scripts['build:production'])) {
            $cmd = $resolver->runScriptCommand($packageManager, 'build:production');
        } elseif (isset($scripts['build'])) {
            $cmd = $resolver->runScriptCommand($packageManager, 'build');
        } else {
            $cmd = $resolver->execCommand($packageManager, [
                'webpack-cli',
                '--mode',
                $mode,
                '--config',
                $webpackConfig,
            ]);
        }

        $result = $this->runProcess($cmd, $path);

        if ($result !== 0) {
            $this->error('Build failed.');
            return 1;
        }

        $this->info('Build complete.');

        return 0;
    }

    private function runProcess(string $command, string $cwd, array $env = []): int
    {
        // Drain stdout and stderr concurrently so noisy native builds cannot deadlock.
        $process = \Symfony\Component\Process\Process::fromShellCommandline($command, $cwd, $env, null, null);
        return $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });
    }
}
