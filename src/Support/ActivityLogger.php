<?php

declare(strict_types=1);

namespace Notur\Support;

use Illuminate\Support\Facades\Event;
use Notur\Events\ExtensionDisabled;
use Notur\Events\ExtensionEnabled;
use Notur\Events\ExtensionInstalled;
use Notur\Events\ExtensionRemoved;
use Notur\Events\ExtensionUpdated;
use Notur\Models\ExtensionActivity;

class ActivityLogger
{
    public function registerListeners(): void
    {
        Event::listen(ExtensionInstalled::class, function (ExtensionInstalled $event): void {
            $this->log(
                extensionId: $event->extensionId,
                action: 'installed',
                summary: "Installed v{$event->version}",
                context: ['version' => $event->version],
            );
        });

        Event::listen(ExtensionUpdated::class, function (ExtensionUpdated $event): void {
            $this->log(
                extensionId: $event->extensionId,
                action: 'updated',
                summary: "Updated {$event->fromVersion} → {$event->toVersion}",
                context: [
                    'from' => $event->fromVersion,
                    'to' => $event->toVersion,
                ],
            );
        });

        Event::listen(ExtensionEnabled::class, function (ExtensionEnabled $event): void {
            $this->log(
                extensionId: $event->extensionId,
                action: 'enabled',
                summary: 'Enabled extension',
            );
        });

        Event::listen(ExtensionDisabled::class, function (ExtensionDisabled $event): void {
            $this->log(
                extensionId: $event->extensionId,
                action: 'disabled',
                summary: 'Disabled extension',
            );
        });

        Event::listen(ExtensionRemoved::class, function (ExtensionRemoved $event): void {
            $this->log(
                extensionId: $event->extensionId,
                action: 'removed',
                summary: 'Removed extension',
            );
        });
    }

    /**
     * Persist an activity log entry for an extension.
     */
    public function log(
        string $extensionId,
        string $action,
        ?string $summary = null,
        array $context = [],
    ): void {
        ExtensionActivity::create([
            'extension_id' => $extensionId,
            'action' => $action,
            'summary' => $summary,
            'context' => $context ?: null,
        ]);
    }
}
