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

                if (!is_string($command) || $command === '') {
                    continue;
                }

                $event = $schedule->command($command);

                if (!$this->applySchedule($event, $task)) {
                    continue;
                }

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

    private function applySchedule($event, array $task): bool
    {
        $cron = $task['cron'] ?? null;
        if (is_string($cron) && $cron !== '') {
            $event->cron($cron);
            return true;
        }

        $schedule = $task['schedule'] ?? null;
        if (!is_array($schedule)) {
            return false;
        }

        $type = strtolower((string) ($schedule['type'] ?? ''));

        return match ($type) {
            'hourly' => $this->applyHourly($event),
            'daily' => $this->applyDaily($event),
            'dailyat' => $this->applyDailyAt($event, $schedule),
            'weeklyon' => $this->applyWeeklyOn($event, $schedule),
            'everyminutes' => $this->applyEveryMinutes($event, $schedule),
            'everyhours' => $this->applyEveryHours($event, $schedule),
            default => false,
        };
    }

    private function applyHourly($event): bool
    {
        $event->hourly();
        return true;
    }

    private function applyDaily($event): bool
    {
        $event->daily();
        return true;
    }

    private function applyDailyAt($event, array $schedule): bool
    {
        $at = $schedule['at'] ?? null;
        if (!is_string($at) || $at === '') {
            return false;
        }

        $event->dailyAt($at);
        return true;
    }

    private function applyWeeklyOn($event, array $schedule): bool
    {
        $day = $this->parseDay($schedule['day'] ?? null);
        $at = $schedule['at'] ?? null;

        if ($day === null || !is_string($at) || $at === '') {
            return false;
        }

        $event->weeklyOn($day, $at);
        return true;
    }

    private function applyEveryMinutes($event, array $schedule): bool
    {
        $interval = $this->parsePositiveInt($schedule['interval'] ?? null);
        if ($interval === null) {
            return false;
        }

        $event->cron("*/{$interval} * * * *");
        return true;
    }

    private function applyEveryHours($event, array $schedule): bool
    {
        $interval = $this->parsePositiveInt($schedule['interval'] ?? null);
        if ($interval === null) {
            return false;
        }

        $event->cron("0 */{$interval} * * *");
        return true;
    }

    private function parseDay(mixed $value): ?int
    {
        if (is_int($value) && $value >= 0 && $value <= 6) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            $map = [
                'sun' => 0,
                'sunday' => 0,
                'mon' => 1,
                'monday' => 1,
                'tue' => 2,
                'tues' => 2,
                'tuesday' => 2,
                'wed' => 3,
                'wednesday' => 3,
                'thu' => 4,
                'thursday' => 4,
                'fri' => 5,
                'friday' => 5,
                'sat' => 6,
                'saturday' => 6,
            ];

            return $map[$normalized] ?? null;
        }

        return null;
    }

    private function parsePositiveInt(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && $value !== '' && ctype_digit($value)) {
            $intVal = (int) $value;
            return $intVal > 0 ? $intVal : null;
        }

        return null;
    }
}
