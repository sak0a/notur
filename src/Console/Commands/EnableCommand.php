<?php

declare(strict_types=1);

namespace Notur\Console\Commands;

use Illuminate\Console\Command;
use Notur\Events\ExtensionEnabled;
use Notur\ExtensionManager;
use Notur\Models\InstalledExtension;

class EnableCommand extends Command
{
    protected $signature = 'notur:enable {extension : The extension ID (vendor/name)}';
    protected $description = 'Enable a Notur extension';

    public function handle(ExtensionManager $manager): int
    {
        $extensionId = $this->argument('extension');

        $record = InstalledExtension::where('extension_id', $extensionId)->first();
        if (!$record) {
            $this->error("Extension '{$extensionId}' is not installed.");
            return 1;
        }

        if ($record->enabled) {
            $this->info("Extension '{$extensionId}' is already enabled.");
            return 0;
        }

        $manager->enable($extensionId);
        ExtensionEnabled::dispatch($extensionId);

        $this->call('cache:clear');
        $this->info("Extension '{$extensionId}' has been enabled.");

        return 0;
    }
}
