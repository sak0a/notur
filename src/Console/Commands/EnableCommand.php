<?php

declare(strict_types=1);

namespace Notur\Console\Commands;

use Illuminate\Console\Command;
use Notur\Events\ExtensionEnabled;
use Notur\Exceptions\DependencyResolutionException;
use Notur\Exceptions\ExtensionNotFoundException;
use Notur\ExtensionManager;
use Notur\Models\InstalledExtension;

class EnableCommand extends Command
{
    protected $signature = 'notur:enable {extension : The extension ID (vendor/name)}';
    protected $description = 'Enable a Notur extension';

    public function handle(ExtensionManager $manager): int
    {
        $extensionId = $this->argument('extension');

        $entry = $manager->getInstalledState($extensionId);
        if ($entry === null) {
            $record = InstalledExtension::where('extension_id', $extensionId)->first();
            if (!$record) {
                $this->error("Extension '{$extensionId}' is not installed.");
                return 1;
            }
            $enabled = (bool) $record->enabled;
        } else {
            $enabled = $entry['enabled'];
        }

        if ($enabled) {
            $this->info("Extension '{$extensionId}' is already enabled.");
            return 0;
        }

        try {
            $manager->enable($extensionId);
        } catch (DependencyResolutionException | ExtensionNotFoundException $e) {
            $this->error($e->getMessage());
            return 1;
        }
        ExtensionEnabled::dispatch($extensionId);

        $this->call('cache:clear');
        $this->info("Extension '{$extensionId}' has been enabled.");

        return 0;
    }
}
