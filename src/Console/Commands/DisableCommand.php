<?php

declare(strict_types=1);

namespace Notur\Console\Commands;

use Illuminate\Console\Command;
use Notur\Events\ExtensionDisabled;
use Notur\Exceptions\DependencyResolutionException;
use Notur\ExtensionManager;
use Notur\Models\InstalledExtension;

class DisableCommand extends Command
{
    protected $signature = 'notur:disable {extension : The extension ID (vendor/name)}';
    protected $description = 'Disable a Notur extension';

    public function handle(ExtensionManager $manager): int
    {
        $extensionId = $this->argument('extension');

        $record = InstalledExtension::where('extension_id', $extensionId)->first();
        if (!$record) {
            $this->error("Extension '{$extensionId}' is not installed.");
            return 1;
        }

        if (!$record->enabled) {
            $this->info("Extension '{$extensionId}' is already disabled.");
            return 0;
        }

        try {
            $manager->disable($extensionId);
        } catch (DependencyResolutionException $e) {
            $this->error($e->getMessage());
            return 1;
        }
        ExtensionDisabled::dispatch($extensionId);

        $this->call('cache:clear');
        $this->info("Extension '{$extensionId}' has been disabled.");

        return 0;
    }
}
