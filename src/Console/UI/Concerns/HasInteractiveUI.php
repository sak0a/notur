<?php

declare(strict_types=1);

namespace Notur\Console\UI\Concerns;

use Notur\Console\UI\Themes\NoturTheme;
use Notur\Support\ConsoleBanner;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

/**
 * Provides interactive UI capabilities to console commands.
 *
 * This trait adds methods for detecting interactive mode, rendering
 * beautiful prompts using Laravel Prompts, and gracefully falling
 * back to basic prompts in non-interactive environments.
 */
trait HasInteractiveUI
{
    /**
     * Check if the command is running in interactive mode.
     */
    protected function isInteractive(): bool
    {
        // Explicit no-interaction flag
        if ($this->option('no-interaction')) {
            return false;
        }

        // CI environment detection
        if ($this->isCI()) {
            return false;
        }

        // Check if stdout is a TTY
        if (function_exists('posix_isatty') && !posix_isatty(STDOUT)) {
            return false;
        }

        return $this->input->isInteractive();
    }

    /**
     * Detect if running in a CI environment.
     */
    protected function isCI(): bool
    {
        return getenv('CI') !== false
            || getenv('GITHUB_ACTIONS') !== false
            || getenv('GITLAB_CI') !== false
            || getenv('CIRCLECI') !== false
            || getenv('TRAVIS') !== false
            || getenv('JENKINS_URL') !== false;
    }

    /**
     * Render the Notur banner if in interactive mode.
     */
    protected function renderBanner(): void
    {
        if ($this->isInteractive()) {
            ConsoleBanner::render($this->output);
        }
    }

    /**
     * Display a styled note/info box.
     */
    protected function renderNote(string $message, ?string $type = null): void
    {
        if ($this->isInteractive() && function_exists('Laravel\Prompts\note')) {
            note($message, $type);
        } else {
            $this->info($message);
        }
    }

    /**
     * Display a warning message.
     */
    protected function renderWarning(string $message): void
    {
        if ($this->isInteractive() && function_exists('Laravel\Prompts\warning')) {
            warning($message);
        } else {
            $this->warn($message);
        }
    }

    /**
     * Interactive text input with fallback.
     */
    protected function interactiveText(
        string $label,
        string $placeholder = '',
        string $default = '',
        ?callable $validate = null,
        string $hint = '',
    ): string {
        if (!$this->isInteractive()) {
            return $default;
        }

        if (function_exists('Laravel\Prompts\text')) {
            return text(
                label: $label,
                placeholder: $placeholder,
                default: $default,
                validate: $validate,
                hint: $hint,
            );
        }

        return $this->ask($label, $default) ?? $default;
    }

    /**
     * Interactive select with fallback.
     *
     * @param array<string, string> $options
     */
    protected function interactiveSelect(
        string $label,
        array $options,
        ?string $default = null,
        int $scroll = 10,
        string $hint = '',
    ): string {
        if (!$this->isInteractive()) {
            return $default ?? array_key_first($options) ?? '';
        }

        if (function_exists('Laravel\Prompts\select')) {
            return select(
                label: $label,
                options: $options,
                default: $default,
                scroll: $scroll,
                hint: $hint,
            );
        }

        $result = $this->choice($label, array_values($options), $default);

        return array_search($result, $options, true) ?: $result;
    }

    /**
     * Interactive multiselect with fallback.
     *
     * @param array<string, string> $options
     * @param array<int, string> $default
     * @return array<int, string>
     */
    protected function interactiveMultiselect(
        string $label,
        array $options,
        array $default = [],
        int $scroll = 10,
        string $hint = '',
        bool $required = false,
    ): array {
        if (!$this->isInteractive()) {
            return $default;
        }

        if (function_exists('Laravel\Prompts\multiselect')) {
            return multiselect(
                label: $label,
                options: $options,
                default: $default,
                scroll: $scroll,
                hint: $hint,
                required: $required,
            );
        }

        // Fallback: ask for each option
        $selected = [];
        foreach ($options as $key => $value) {
            $isDefault = in_array($key, $default, true);
            if ($this->confirm("Include {$value}?", $isDefault)) {
                $selected[] = $key;
            }
        }

        return $selected;
    }

    /**
     * Interactive confirmation with fallback.
     */
    protected function interactiveConfirm(
        string $label,
        bool $default = false,
        string $yes = 'Yes',
        string $no = 'No',
        string $hint = '',
    ): bool {
        if (!$this->isInteractive()) {
            return $default;
        }

        if (function_exists('Laravel\Prompts\confirm')) {
            return confirm(
                label: $label,
                default: $default,
                yes: $yes,
                no: $no,
                hint: $hint,
            );
        }

        return $this->confirm($label, $default);
    }

    /**
     * Interactive search with callback.
     *
     * @param callable(string): array<string, string> $options
     */
    protected function interactiveSearch(
        string $label,
        callable $options,
        string $placeholder = 'Search...',
        int $scroll = 10,
        string $hint = '',
    ): string {
        if (!$this->isInteractive()) {
            return '';
        }

        if (function_exists('Laravel\Prompts\search')) {
            return search(
                label: $label,
                options: $options,
                placeholder: $placeholder,
                scroll: $scroll,
                hint: $hint,
            );
        }

        // Fallback: basic text input for search term
        $term = $this->ask($label);
        $results = $options($term ?? '');

        if (empty($results)) {
            $this->warn('No results found.');

            return '';
        }

        return $this->choice('Select from results', array_values($results));
    }

    /**
     * Execute a callback with a spinner animation.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    protected function withSpinner(string $message, callable $callback): mixed
    {
        if (!$this->isInteractive()) {
            $this->info($message);

            return $callback();
        }

        if (function_exists('Laravel\Prompts\spin')) {
            return spin(
                message: $message,
                callback: $callback,
            );
        }

        $this->info($message);

        return $callback();
    }

    /**
     * Confirm a dangerous/destructive action.
     *
     * @param array<int, string> $consequences
     */
    protected function confirmDangerous(
        string $message,
        array $consequences = [],
        bool $requireExplicitYes = false,
    ): bool {
        if (!$this->isInteractive()) {
            return $this->option('force') ?? false;
        }

        // Show consequences
        if (!empty($consequences)) {
            $this->newLine();
            $this->warn('This action will:');
            foreach ($consequences as $consequence) {
                $this->line('  ' . NoturTheme::warning('•') . ' ' . $consequence);
            }
            $this->newLine();
        }

        if ($requireExplicitYes) {
            $response = $this->interactiveText(
                label: $message . ' (type "yes" to confirm)',
                placeholder: 'yes',
            );

            return strtolower($response) === 'yes';
        }

        return $this->interactiveConfirm(
            label: $message,
            default: false,
            yes: 'Yes, proceed',
            no: 'Cancel',
        );
    }

    /**
     * Render a styled box with title and content.
     *
     * @param array<int, string> $lines
     */
    protected function renderBox(string $title, array $lines): void
    {
        $this->output->writeln(NoturTheme::box($lines, $title));
    }

    /**
     * Render extension info in a formatted way.
     *
     * @param array<string, mixed> $extension
     */
    protected function renderExtensionInfo(array $extension): void
    {
        $id = $extension['id'] ?? $extension['extension_id'] ?? 'unknown';
        $name = $extension['name'] ?? $id;
        $version = $extension['version'] ?? $extension['latest_version'] ?? '?';
        $description = $extension['description'] ?? 'No description';

        $this->newLine();
        $this->line(NoturTheme::bold(NoturTheme::primary($name)) . ' ' . NoturTheme::muted("v{$version}"));
        $this->line(NoturTheme::muted($id));
        $this->newLine();
        $this->line($description);

        // Optional metadata
        if (isset($extension['author'])) {
            $this->line(NoturTheme::muted('Author: ') . $extension['author']);
        }
        if (isset($extension['license'])) {
            $this->line(NoturTheme::muted('License: ') . $extension['license']);
        }
        if (isset($extension['downloads'])) {
            $this->line(NoturTheme::downloads((int) $extension['downloads']));
        }
        if (isset($extension['rating'])) {
            $this->line(NoturTheme::stars((float) $extension['rating']));
        }

        $this->newLine();
    }

    /**
     * Render a status line with indicator.
     */
    protected function renderStatus(string $label, string $value, string $status = 'success'): void
    {
        $indicator = match ($status) {
            'success' => NoturTheme::successIndicator(),
            'error' => NoturTheme::errorIndicator(),
            'warning' => NoturTheme::warningIndicator(),
            default => NoturTheme::pendingIndicator(),
        };

        $this->line(
            str_pad($label, 20) .
            str_pad($value, 15) .
            $indicator . ' ' . ucfirst($status)
        );
    }
}
