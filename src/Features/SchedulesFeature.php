<?php

declare(strict_types=1);

namespace Notur\Features;

use Illuminate\Console\Scheduling\Schedule;

final class SchedulesFeature implements ExtensionFeature
{
    public function getCapabilityId(): ?string
    {
        return 'schedules';
    }

    public function getCapabilityVersion(): int
    {
        return 1;
    }

    public function isEnabledByDefault(): bool
    {
        return false;
    }

    public function supports(ExtensionContext $context): bool
    {
        $tasks = $context->manifest->get('schedules.tasks', []);
        return is_array($tasks) && !empty($tasks);
    }

    public function register(ExtensionContext $context): void
    {
        if (!$context->app->runningInConsole()) {
            return;
        }

        $tasks = $context->manifest->get('schedules.tasks', []);
        if (!is_array($tasks) || $tasks === []) {
            return;
        }

        $context->app->booted(function () use ($context, $tasks): void {
            $schedule = $context->app->make(Schedule::class);

            foreach ($tasks as $task) {
                if (!is_array($task)) {
                    continue;
                }

                $enabled = $task['enabled'] ?? true;
                if ($enabled === false) {
                    continue;
                }

                $command = $task['command'] ?? null;
                $cron = $task['cron'] ?? null;

                if (!is_string($command) || $command === '' || !is_string($cron) || $cron === '') {
                    continue;
                }

                $event = $schedule->command($command)->cron($cron);

                if (is_string($task['timezone'] ?? null)) {
                    $event->timezone($task['timezone']);
                }

                if (!empty($task['without_overlapping'])) {
                    $event->withoutOverlapping();
                }

                if (!empty($task['on_one_server'])) {
                    $event->onOneServer();
                }

                if (!empty($task['run_in_maintenance'])) {
                    $event->evenInMaintenanceMode();
                }

                if (is_string($task['label'] ?? null)) {
                    $event->description($task['label']);
                }
            }
        });
    }

    public function boot(ExtensionContext $context): void
    {
        // No-op.
    }
}
