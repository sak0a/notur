<?php

declare(strict_types=1);

namespace Notur\Console\UI\Themes;

/**
 * Notur console theme constants and helpers.
 *
 * Provides consistent styling across all CLI commands with
 * brand colors, Unicode symbols, and formatting utilities.
 */
final class NoturTheme
{
    // ── Brand Colors ─────────────────────────────────────────────────────

    public const PRIMARY = '#7c3aed';        // Purple (brand)
    public const PRIMARY_LIGHT = '#a78bfa';
    public const PRIMARY_DARK = '#5b21b6';

    // ── Semantic Colors ──────────────────────────────────────────────────

    public const SUCCESS = '#22c55e';         // Green
    public const WARNING = '#f59e0b';         // Amber
    public const ERROR = '#ef4444';           // Red
    public const INFO = '#3b82f6';            // Blue

    // ── Text Colors ──────────────────────────────────────────────────────

    public const TEXT_PRIMARY = '#f8fafc';
    public const TEXT_SECONDARY = '#94a3b8';
    public const TEXT_MUTED = '#64748b';

    // ── Box Drawing Characters ───────────────────────────────────────────

    public const BOX_HORIZONTAL = '─';
    public const BOX_VERTICAL = '│';
    public const BOX_TOP_LEFT = '┌';
    public const BOX_TOP_RIGHT = '┐';
    public const BOX_BOTTOM_LEFT = '└';
    public const BOX_BOTTOM_RIGHT = '┘';
    public const BOX_T_DOWN = '┬';
    public const BOX_T_UP = '┴';
    public const BOX_T_RIGHT = '├';
    public const BOX_T_LEFT = '┤';
    public const BOX_CROSS = '┼';

    // ── Double Box Drawing ───────────────────────────────────────────────

    public const BOX_DOUBLE_HORIZONTAL = '═';
    public const BOX_DOUBLE_VERTICAL = '║';

    // ── Progress Bar Characters ──────────────────────────────────────────

    public const PROGRESS_FILLED = '━';
    public const PROGRESS_EMPTY = '░';
    public const PROGRESS_HEAD = '╸';

    // ── Status Indicators ────────────────────────────────────────────────

    public const INDICATOR_SUCCESS = '●';
    public const INDICATOR_WARNING = '◐';
    public const INDICATOR_ERROR = '○';
    public const INDICATOR_PENDING = '◌';
    public const INDICATOR_ARROW_UP = '↑';
    public const INDICATOR_ARROW_DOWN = '↓';
    public const INDICATOR_ARROW_RIGHT = '→';
    public const INDICATOR_CHECK = '✓';
    public const INDICATOR_CROSS = '✗';
    public const INDICATOR_STAR = '★';
    public const INDICATOR_STAR_EMPTY = '☆';

    // ── Spinner Frames ───────────────────────────────────────────────────

    public const SPINNER_FRAMES = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
    public const SPINNER_DOTS = ['⣾', '⣽', '⣻', '⢿', '⡿', '⣟', '⣯', '⣷'];

    // ── Wizard Progress ──────────────────────────────────────────────────

    public const WIZARD_STEP_COMPLETE = '━━━━';
    public const WIZARD_STEP_CURRENT = '●';
    public const WIZARD_STEP_PENDING = '○';
    public const WIZARD_CONNECTOR = '───';

    // ── Styling Helpers ──────────────────────────────────────────────────

    /**
     * Wrap text with ANSI foreground color.
     */
    public static function fg(string $text, string $color): string
    {
        return "<fg={$color}>{$text}</>";
    }

    /**
     * Wrap text with ANSI background color.
     */
    public static function bg(string $text, string $color): string
    {
        return "<bg={$color}>{$text}</>";
    }

    /**
     * Apply bold styling.
     */
    public static function bold(string $text): string
    {
        return "<options=bold>{$text}</>";
    }

    /**
     * Apply dim styling.
     */
    public static function dim(string $text): string
    {
        return "<fg=gray>{$text}</>";
    }

    // ── Semantic Styling ─────────────────────────────────────────────────

    public static function success(string $text): string
    {
        return self::fg($text, self::SUCCESS);
    }

    public static function error(string $text): string
    {
        return self::fg($text, self::ERROR);
    }

    public static function warning(string $text): string
    {
        return self::fg($text, self::WARNING);
    }

    public static function info(string $text): string
    {
        return self::fg($text, self::INFO);
    }

    public static function primary(string $text): string
    {
        return self::fg($text, self::PRIMARY);
    }

    public static function muted(string $text): string
    {
        return self::fg($text, self::TEXT_MUTED);
    }

    public static function secondary(string $text): string
    {
        return self::fg($text, self::TEXT_SECONDARY);
    }

    // ── Status Indicators with Colors ────────────────────────────────────

    public static function successIndicator(): string
    {
        return self::success(self::INDICATOR_SUCCESS);
    }

    public static function errorIndicator(): string
    {
        return self::error(self::INDICATOR_ERROR);
    }

    public static function warningIndicator(): string
    {
        return self::warning(self::INDICATOR_WARNING);
    }

    public static function pendingIndicator(): string
    {
        return self::muted(self::INDICATOR_PENDING);
    }

    public static function checkmark(): string
    {
        return self::success(self::INDICATOR_CHECK);
    }

    public static function crossmark(): string
    {
        return self::error(self::INDICATOR_CROSS);
    }

    // ── Box Drawing Helpers ──────────────────────────────────────────────

    /**
     * Draw a horizontal line.
     */
    public static function line(int $width, string $char = self::BOX_HORIZONTAL): string
    {
        return str_repeat($char, $width);
    }

    /**
     * Draw a simple box around text.
     *
     * @param array<int, string> $lines
     */
    public static function box(array $lines, ?string $title = null): string
    {
        $maxLen = max(array_map('mb_strlen', $lines));
        if ($title !== null) {
            $maxLen = max($maxLen, mb_strlen($title) + 2);
        }

        $output = [];

        // Top border
        $topLine = self::BOX_TOP_LEFT . self::line($maxLen + 2) . self::BOX_TOP_RIGHT;
        $output[] = $topLine;

        // Title (if provided)
        if ($title !== null) {
            $output[] = self::BOX_VERTICAL . ' ' . self::bold(str_pad($title, $maxLen)) . ' ' . self::BOX_VERTICAL;
            $output[] = self::BOX_T_RIGHT . self::line($maxLen + 2) . self::BOX_T_LEFT;
        }

        // Content lines
        foreach ($lines as $line) {
            $output[] = self::BOX_VERTICAL . ' ' . str_pad($line, $maxLen) . ' ' . self::BOX_VERTICAL;
        }

        // Bottom border
        $output[] = self::BOX_BOTTOM_LEFT . self::line($maxLen + 2) . self::BOX_BOTTOM_RIGHT;

        return implode("\n", $output);
    }

    // ── Progress Bar ─────────────────────────────────────────────────────

    /**
     * Generate a progress bar string.
     */
    public static function progressBar(int $percent, int $width = 40): string
    {
        $percent = max(0, min(100, $percent));
        $filled = (int) round($width * $percent / 100);
        $empty = $width - $filled;

        $bar = self::primary(str_repeat(self::PROGRESS_FILLED, $filled));
        if ($empty > 0) {
            $bar .= self::muted(str_repeat(self::PROGRESS_EMPTY, $empty));
        }

        return $bar . ' ' . self::muted($percent . '%');
    }

    // ── Wizard Progress ──────────────────────────────────────────────────

    /**
     * Generate wizard step progress indicator.
     *
     * @param array<int, string> $steps
     */
    public static function wizardProgress(array $steps, int $currentStep): string
    {
        $parts = [];

        foreach ($steps as $index => $step) {
            if ($index < $currentStep) {
                // Completed step
                $parts[] = self::success(self::WIZARD_STEP_COMPLETE);
            } elseif ($index === $currentStep) {
                // Current step
                $parts[] = self::primary(self::WIZARD_STEP_CURRENT);
            } else {
                // Pending step
                $parts[] = self::muted(self::WIZARD_STEP_PENDING);
            }

            // Add connector between steps
            if ($index < count($steps) - 1) {
                $parts[] = self::muted(self::WIZARD_CONNECTOR);
            }
        }

        return implode('', $parts);
    }

    // ── Rating Stars ─────────────────────────────────────────────────────

    /**
     * Generate star rating display.
     */
    public static function stars(float $rating, int $maxStars = 5): string
    {
        $fullStars = (int) floor($rating);
        $emptyStars = $maxStars - $fullStars;

        return self::warning(str_repeat(self::INDICATOR_STAR, $fullStars))
            . self::muted(str_repeat(self::INDICATOR_STAR_EMPTY, $emptyStars));
    }

    // ── Downloads Formatter ──────────────────────────────────────────────

    /**
     * Format download count with suffix.
     */
    public static function downloads(int $count): string
    {
        if ($count >= 1000000) {
            return self::muted(self::INDICATOR_ARROW_DOWN . ' ' . round($count / 1000000, 1) . 'M');
        }
        if ($count >= 1000) {
            return self::muted(self::INDICATOR_ARROW_DOWN . ' ' . round($count / 1000, 1) . 'k');
        }

        return self::muted(self::INDICATOR_ARROW_DOWN . ' ' . $count);
    }
}
