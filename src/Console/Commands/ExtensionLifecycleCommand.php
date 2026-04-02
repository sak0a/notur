<?php

declare(strict_types=1);

namespace Notur\Console\Commands;

use Illuminate\Console\Command;
use Notur\Console\Concerns\ManagesFilesystem;
use Notur\Support\ExtensionPath;

abstract class ExtensionLifecycleCommand extends Command
{
    use ManagesFilesystem;

    /**
     * Clear Notur-related caches.
     */
    protected function clearNoturCaches(): void
    {
        $this->call('cache:clear');
        $this->call('view:clear');
    }

    /**
     * Remove an extension's files and public assets.
     */
    protected function removeExtensionFiles(string $extensionId): void
    {
        $extensionPath = ExtensionPath::base($extensionId);
        if (is_dir($extensionPath)) {
            $this->deleteDirectory($extensionPath);
        }

        $publicPath = ExtensionPath::public($extensionId);
        if (is_dir($publicPath)) {
            $this->deleteDirectory($publicPath);
        }
    }
}
