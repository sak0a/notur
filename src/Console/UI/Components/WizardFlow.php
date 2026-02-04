<?php

declare(strict_types=1);

namespace Notur\Console\UI\Components;

use Illuminate\Console\Command;
use Notur\Console\UI\Themes\NoturTheme;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;

/**
 * Multi-step wizard flow component.
 *
 * Provides a consistent structure for multi-step command wizards
 * with progress tracking, navigation, and data collection.
 */
class WizardFlow
{
    /**
     * @var array<int, array{name: string, handler: callable}>
     */
    private array $steps = [];

    private int $currentStep = 0;

    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    private bool $cancelled = false;

    public function __construct(
        private readonly Command $command,
        private readonly string $title,
    ) {}

    /**
     * Add a step to the wizard.
     *
     * @param callable(array<string, mixed>): array<string, mixed>|false $handler
     */
    public function addStep(string $name, callable $handler): self
    {
        $this->steps[] = [
            'name' => $name,
            'handler' => $handler,
        ];

        return $this;
    }

    /**
     * Run the wizard and return collected data.
     *
     * @return array<string, mixed>
     */
    public function run(): array
    {
        if (empty($this->steps)) {
            return [];
        }

        // Display intro
        $this->renderIntro();

        // Process each step
        foreach ($this->steps as $index => $step) {
            $this->currentStep = $index;

            // Render progress and step header
            $this->renderStepHeader($step['name']);

            // Execute step handler
            $result = ($step['handler'])($this->data);

            // Check for cancellation
            if ($result === false) {
                $this->cancelled = true;
                $this->command->newLine();
                $this->command->warn('Wizard cancelled.');

                return [];
            }

            // Merge step data
            if (is_array($result)) {
                $this->data = array_merge($this->data, $result);
            }
        }

        // Display outro
        $this->renderOutro();

        return $this->data;
    }

    /**
     * Get the current collected data.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Set initial data for the wizard.
     *
     * @param array<string, mixed> $data
     */
    public function setInitialData(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Check if the wizard was cancelled.
     */
    public function wasCancelled(): bool
    {
        return $this->cancelled;
    }

    /**
     * Render the wizard intro.
     */
    private function renderIntro(): void
    {
        $this->command->newLine();

        if (function_exists('Laravel\Prompts\intro')) {
            intro($this->title);
        } else {
            $this->command->info($this->title);
            $this->command->line(str_repeat('─', mb_strlen($this->title)));
        }

        $this->command->newLine();
    }

    /**
     * Render the wizard outro.
     */
    private function renderOutro(): void
    {
        $this->command->newLine();

        if (function_exists('Laravel\Prompts\outro')) {
            outro('Wizard completed successfully!');
        } else {
            $this->command->info('✓ Wizard completed successfully!');
        }
    }

    /**
     * Render the step header with progress indicator.
     */
    private function renderStepHeader(string $stepName): void
    {
        $total = count($this->steps);
        $current = $this->currentStep + 1;

        // Progress bar
        $this->renderProgress();

        // Step label
        if (function_exists('Laravel\Prompts\note')) {
            note("Step {$current} of {$total}: {$stepName}");
        } else {
            $this->command->newLine();
            $this->command->line(NoturTheme::bold("Step {$current} of {$total}: {$stepName}"));
        }

        $this->command->newLine();
    }

    /**
     * Render the progress indicator.
     */
    private function renderProgress(): void
    {
        $stepNames = array_column($this->steps, 'name');
        $progress = NoturTheme::wizardProgress($stepNames, $this->currentStep);

        $this->command->line($progress);

        // Step names below progress
        $labels = [];
        foreach ($stepNames as $index => $name) {
            $shortName = mb_strlen($name) > 12 ? mb_substr($name, 0, 12) . '…' : $name;

            if ($index < $this->currentStep) {
                $labels[] = NoturTheme::success($shortName);
            } elseif ($index === $this->currentStep) {
                $labels[] = NoturTheme::bold($shortName);
            } else {
                $labels[] = NoturTheme::muted($shortName);
            }
        }

        $this->command->line(implode('     ', $labels));
        $this->command->newLine();
    }

    /**
     * Create a summary of collected data for confirmation.
     *
     * @param array<string, string> $labels Mapping of data keys to display labels
     */
    public function renderSummary(array $labels): void
    {
        $this->command->newLine();
        $this->command->line(NoturTheme::bold('Summary'));
        $this->command->line(NoturTheme::line(40));
        $this->command->newLine();

        foreach ($labels as $key => $label) {
            $value = $this->data[$key] ?? null;

            if ($value === null) {
                continue;
            }

            $displayValue = match (true) {
                is_bool($value) => $value ? NoturTheme::success('Yes') : NoturTheme::muted('No'),
                is_array($value) => implode(', ', $value) ?: NoturTheme::muted('None'),
                default => (string) $value,
            };

            $this->command->line(
                NoturTheme::muted(str_pad($label . ':', 20)) . $displayValue
            );
        }

        $this->command->newLine();
    }

    /**
     * Render a preview of what will be created.
     *
     * @param array<int, array{type: string, path: string}> $items
     */
    public function renderPreview(array $items): void
    {
        $this->command->newLine();
        $this->command->line(NoturTheme::bold('Files to be created:'));
        $this->command->newLine();

        $grouped = [];
        foreach ($items as $item) {
            $type = $item['type'] ?? 'file';
            $grouped[$type][] = $item['path'];
        }

        foreach ($grouped as $type => $paths) {
            $icon = match ($type) {
                'directory' => '📁',
                'php' => '🐘',
                'typescript', 'tsx' => '📘',
                'json' => '📋',
                'yaml' => '📄',
                default => '📄',
            };

            foreach ($paths as $path) {
                $this->command->line("  {$icon} {$path}");
            }
        }

        $this->command->newLine();
    }
}
