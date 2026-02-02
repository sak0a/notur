<?php

declare(strict_types=1);

/**
 * Notur PHP Installer Logic
 *
 * Handles the Laravel-specific installation steps:
 * - Service provider registration
 * - Migration execution
 * - Directory setup
 * - Configuration publishing
 */

class NoturInstaller
{
    private string $panelPath;
    private array $errors = [];
    private array $log = [];

    public function __construct(string $panelPath)
    {
        $this->panelPath = rtrim($panelPath, '/');
    }

    public function install(): bool
    {
        $this->log('Starting Notur installation...');

        if (!$this->validatePanel()) {
            return false;
        }

        $this->createDirectories();
        $this->initializeManifest();
        $this->publishConfig();

        $this->log('PHP installation steps complete.');
        return empty($this->errors);
    }

    private function validatePanel(): bool
    {
        if (!file_exists($this->panelPath . '/artisan')) {
            $this->error('Not a Laravel application: artisan file not found.');
            return false;
        }

        if (!file_exists($this->panelPath . '/composer.json')) {
            $this->error('composer.json not found.');
            return false;
        }

        $composerJson = json_decode(file_get_contents($this->panelPath . '/composer.json'), true);
        $name = $composerJson['name'] ?? '';

        if ($name !== 'pterodactyl/panel') {
            $this->log("Warning: Panel package name is '{$name}', expected 'pterodactyl/panel'.");
        }

        return true;
    }

    private function createDirectories(): void
    {
        $dirs = [
            $this->panelPath . '/notur',
            $this->panelPath . '/notur/extensions',
            $this->panelPath . '/public/notur',
            $this->panelPath . '/public/notur/extensions',
            $this->panelPath . '/storage/notur',
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                if (mkdir($dir, 0755, true)) {
                    $this->log("Created directory: {$dir}");
                } else {
                    $this->error("Failed to create directory: {$dir}");
                }
            }
        }
    }

    private function initializeManifest(): void
    {
        $manifestPath = $this->panelPath . '/notur/extensions.json';

        if (!file_exists($manifestPath)) {
            $manifest = ['extensions' => new \stdClass()];
            file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT));
            $this->log('Initialized extensions.json');
        }
    }

    private function publishConfig(): void
    {
        $configSource = dirname(__DIR__) . '/config/notur.php';
        $configTarget = $this->panelPath . '/config/notur.php';

        if (!file_exists($configTarget) && file_exists($configSource)) {
            copy($configSource, $configTarget);
            $this->log('Published notur.php config.');
        }
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getLog(): array
    {
        return $this->log;
    }

    private function log(string $message): void
    {
        $this->log[] = $message;
        echo "[Notur] {$message}\n";
    }

    private function error(string $message): void
    {
        $this->errors[] = $message;
        echo "[Notur ERROR] {$message}\n";
    }
}

// CLI usage
if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    $panelPath = $argv[1] ?? '/var/www/pterodactyl';
    $installer = new NoturInstaller($panelPath);

    if ($installer->install()) {
        echo "\nNotur installation steps complete.\n";
        exit(0);
    } else {
        echo "\nInstallation failed with errors:\n";
        foreach ($installer->getErrors() as $error) {
            echo "  - {$error}\n";
        }
        exit(1);
    }
}
