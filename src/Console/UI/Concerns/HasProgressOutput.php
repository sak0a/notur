<?php

declare(strict_types=1);

namespace Notur\Console\UI\Concerns;

use Notur\Console\UI\Themes\NoturTheme;

use function Laravel\Prompts\progress;
use function Laravel\Prompts\spin;

/**
 * Provides progress output capabilities to console commands.
 *
 * Includes progress bars, spinners, step tracking, and
 * formatted status output for long-running operations.
 */
trait HasProgressOutput
{
    /**
     * @var array<int, array{message: string, status: string}>
     */
    private array $steps = [];

    private int $currentStep = 0;

    /**
     * Execute a callback with a progress bar.
     *
     * @template TKey
     * @template TValue
     * @param iterable<TKey, TValue> $items
     * @param callable(TValue, TKey): mixed $callback
     */
    protected function withProgress(string $label, iterable $items, callable $callback): void
    {
        if (!$this->isInteractive()) {
            $this->info($label);
            foreach ($items as $key => $item) {
                $callback($item, $key);
            }

            return;
        }

        if (function_exists('Laravel\Prompts\progress')) {
            progress(
                label: $label,
                steps: $items,
                callback: $callback,
            );

            return;
        }

        // Fallback: manual progress display
        $items = is_array($items) ? $items : iterator_to_array($items);
        $total = count($items);
        $current = 0;

        $this->info($label);

        foreach ($items as $key => $item) {
            $current++;
            $percent = (int) round(($current / $total) * 100);
            $this->output->write("\r  " . NoturTheme::progressBar($percent, 30) . " ({$current}/{$total})");
            $callback($item, $key);
        }

        $this->newLine();
    }

    /**
     * Initialize step tracking for a multi-step process.
     *
     * @param array<int, string> $stepMessages
     */
    protected function initializeSteps(array $stepMessages): void
    {
        $this->steps = [];
        $this->currentStep = 0;

        foreach ($stepMessages as $message) {
            $this->steps[] = [
                'message' => $message,
                'status' => 'pending',
            ];
        }
    }

    /**
     * Start the next step in the process.
     */
    protected function startStep(?string $customMessage = null): void
    {
        if ($this->currentStep < count($this->steps)) {
            $this->steps[$this->currentStep]['status'] = 'running';
            $message = $customMessage ?? $this->steps[$this->currentStep]['message'];

            if ($this->isInteractive()) {
                $spinner = NoturTheme::SPINNER_FRAMES[0];
                $this->output->write("  {$spinner} {$message}");
            } else {
                $this->info($message);
            }
        }
    }

    /**
     * Complete the current step successfully.
     */
    protected function completeStep(?string $customMessage = null): void
    {
        if ($this->currentStep < count($this->steps)) {
            $this->steps[$this->currentStep]['status'] = 'complete';
            $message = $customMessage ?? $this->steps[$this->currentStep]['message'];

            if ($this->isInteractive()) {
                $this->output->write("\r  " . NoturTheme::checkmark() . " {$message}");
                $this->newLine();
            } else {
                $this->info(NoturTheme::INDICATOR_CHECK . ' ' . $message);
            }

            $this->currentStep++;
        }
    }

    /**
     * Fail the current step.
     */
    protected function failStep(?string $errorMessage = null): void
    {
        if ($this->currentStep < count($this->steps)) {
            $this->steps[$this->currentStep]['status'] = 'failed';
            $message = $errorMessage ?? $this->steps[$this->currentStep]['message'];

            if ($this->isInteractive()) {
                $this->output->write("\r  " . NoturTheme::crossmark() . " {$message}");
                $this->newLine();
            } else {
                $this->error(NoturTheme::INDICATOR_CROSS . ' ' . $message);
            }
        }
    }

    /**
     * Skip the current step.
     */
    protected function skipStep(?string $reason = null): void
    {
        if ($this->currentStep < count($this->steps)) {
            $this->steps[$this->currentStep]['status'] = 'skipped';
            $message = $this->steps[$this->currentStep]['message'];
            $suffix = $reason ? " ({$reason})" : '';

            if ($this->isInteractive()) {
                $this->output->write("\r  " . NoturTheme::muted('○') . " " . NoturTheme::muted($message . $suffix));
                $this->newLine();
            } else {
                $this->line('  ○ ' . $message . $suffix);
            }

            $this->currentStep++;
        }
    }

    /**
     * Render a summary of all steps.
     */
    protected function renderStepsSummary(): void
    {
        $this->newLine();

        $completed = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($this->steps as $step) {
            match ($step['status']) {
                'complete' => $completed++,
                'failed' => $failed++,
                'skipped' => $skipped++,
                default => null,
            };
        }

        $parts = [];
        if ($completed > 0) {
            $parts[] = NoturTheme::success("{$completed} completed");
        }
        if ($failed > 0) {
            $parts[] = NoturTheme::error("{$failed} failed");
        }
        if ($skipped > 0) {
            $parts[] = NoturTheme::muted("{$skipped} skipped");
        }

        $this->line('  ' . implode('  •  ', $parts));
    }

    /**
     * Display a task list with checkboxes.
     *
     * @param array<int, array{label: string, done: bool}> $tasks
     */
    protected function renderTaskList(array $tasks): void
    {
        foreach ($tasks as $task) {
            $checkbox = $task['done']
                ? NoturTheme::success('[✓]')
                : NoturTheme::muted('[ ]');

            $label = $task['done']
                ? NoturTheme::success($task['label'])
                : $task['label'];

            $this->line("  {$checkbox} {$label}");
        }
    }

    /**
     * Display a countdown or timer.
     */
    protected function countdown(int $seconds, string $message = 'Waiting'): void
    {
        if (!$this->isInteractive()) {
            $this->info("{$message}...");
            sleep($seconds);

            return;
        }

        for ($i = $seconds; $i > 0; $i--) {
            $this->output->write("\r  {$message}... {$i}s ");
            sleep(1);
        }

        $this->output->write("\r" . str_repeat(' ', strlen($message) + 20) . "\r");
    }

    /**
     * Display file operation progress.
     */
    protected function fileOperation(string $operation, string $path, string $status = 'success'): void
    {
        $icon = match ($status) {
            'success' => NoturTheme::checkmark(),
            'error' => NoturTheme::crossmark(),
            'skip' => NoturTheme::muted('○'),
            default => NoturTheme::muted('•'),
        };

        $opLabel = match ($operation) {
            'create' => NoturTheme::success('CREATE'),
            'update' => NoturTheme::info('UPDATE'),
            'delete' => NoturTheme::error('DELETE'),
            'skip' => NoturTheme::muted('SKIP'),
            default => NoturTheme::muted(strtoupper($operation)),
        };

        $this->line("  {$icon} {$opLabel} {$path}");
    }

    /**
     * Display download progress with speed.
     */
    protected function downloadProgress(int $downloaded, int $total, float $speed): void
    {
        if (!$this->isInteractive()) {
            return;
        }

        $percent = $total > 0 ? (int) round(($downloaded / $total) * 100) : 0;
        $bar = NoturTheme::progressBar($percent, 40);

        $downloadedMB = round($downloaded / 1024 / 1024, 1);
        $totalMB = round($total / 1024 / 1024, 1);
        $speedMB = round($speed / 1024 / 1024, 1);

        $this->output->write("\r  {$bar}  {$downloadedMB}/{$totalMB} MB  {$speedMB} MB/s");
    }

    /**
     * Clear the current line (for updating progress).
     */
    protected function clearLine(): void
    {
        if ($this->isInteractive()) {
            $this->output->write("\r\033[K");
        }
    }

    /**
     * Check if interactive mode is available.
     * This method should be provided by HasInteractiveUI trait.
     */
    abstract protected function isInteractive(): bool;
}
