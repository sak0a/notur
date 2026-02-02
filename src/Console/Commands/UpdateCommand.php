<?php

declare(strict_types=1);

namespace Notur\Console\Commands;

use Illuminate\Console\Command;
use Notur\Models\InstalledExtension;
use Notur\Support\RegistryClient;

class UpdateCommand extends Command
{
    protected $signature = 'notur:update
        {extension? : The extension ID (vendor/name). Updates all if omitted.}
        {--check : Only check for available updates, do not install}';

    protected $description = 'Update Notur extensions';

    public function handle(RegistryClient $registry): int
    {
        $extensionId = $this->argument('extension');

        if ($extensionId) {
            return $this->updateSingle($extensionId, $registry);
        }

        return $this->updateAll($registry);
    }

    private function updateSingle(string $extensionId, RegistryClient $registry): int
    {
        $record = InstalledExtension::where('extension_id', $extensionId)->first();
        if (!$record) {
            $this->error("Extension '{$extensionId}' is not installed.");
            return 1;
        }

        $extInfo = $registry->getExtension($extensionId);
        if (!$extInfo) {
            $this->error("Extension '{$extensionId}' not found in registry.");
            return 1;
        }

        $latestVersion = $extInfo['latest_version'] ?? $extInfo['version'] ?? '0.0.0';
        $currentVersion = $record->version;

        if (version_compare($currentVersion, $latestVersion, '>=')) {
            $this->info("Extension '{$extensionId}' is already up to date (v{$currentVersion}).");
            return 0;
        }

        $this->info("Update available: {$extensionId} v{$currentVersion} → v{$latestVersion}");

        if ($this->option('check')) {
            return 0;
        }

        // Reinstall with --force to update
        return $this->call('notur:install', [
            'extension' => $extensionId,
            '--force' => true,
        ]);
    }

    private function updateAll(RegistryClient $registry): int
    {
        $extensions = InstalledExtension::all();

        if ($extensions->isEmpty()) {
            $this->info('No extensions installed.');
            return 0;
        }

        $updates = [];

        foreach ($extensions as $record) {
            $extInfo = $registry->getExtension($record->extension_id);
            if (!$extInfo) {
                continue;
            }

            $latestVersion = $extInfo['latest_version'] ?? $extInfo['version'] ?? '0.0.0';
            if (version_compare($record->version, $latestVersion, '<')) {
                $updates[] = [
                    'id' => $record->extension_id,
                    'current' => $record->version,
                    'latest' => $latestVersion,
                ];
            }
        }

        if (empty($updates)) {
            $this->info('All extensions are up to date.');
            return 0;
        }

        $this->table(
            ['Extension', 'Current', 'Available'],
            array_map(fn ($u) => [$u['id'], $u['current'], $u['latest']], $updates),
        );

        if ($this->option('check')) {
            return 0;
        }

        if (!$this->confirm('Update all?')) {
            return 0;
        }

        foreach ($updates as $update) {
            $this->call('notur:install', [
                'extension' => $update['id'],
                '--force' => true,
            ]);
        }

        return 0;
    }
}
