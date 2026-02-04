<?php

declare(strict_types=1);

namespace Notur\Console\UI\Components;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Notur\Console\UI\Themes\NoturTheme;
use Notur\ExtensionManager;
use Notur\Support\ConsoleBanner;

/**
 * Status dashboard component for displaying system health.
 *
 * Shows Notur version, installed extensions, updates available,
 * and system compatibility information.
 */
class StatusDashboard
{
    public function __construct(
        private readonly Command $command,
        private readonly ExtensionManager $manager,
    ) {}

    /**
     * Render the full dashboard.
     */
    public function render(): void
    {
        // Banner
        ConsoleBanner::render($this->command->getOutput());

        // System Health Section
        $this->renderSystemHealth();

        // Extensions Section
        $this->renderExtensions();

        // Quick Actions hint
        $this->renderQuickActions();
    }

    /**
     * Render system health section.
     */
    public function renderSystemHealth(): void
    {
        $this->command->newLine();
        $this->renderSectionHeader('System Health');

        $checks = $this->getHealthChecks();

        foreach ($checks as $check) {
            $this->renderHealthRow(
                $check['label'],
                $check['value'],
                $check['status'],
                $check['message'] ?? null
            );
        }

        $this->command->newLine();
    }

    /**
     * Get system health checks.
     *
     * @return array<int, array{label: string, value: string, status: string, message?: string}>
     */
    private function getHealthChecks(): array
    {
        $checks = [];

        // Notur Version
        $noturVersion = $this->getNoturVersion();
        $checks[] = [
            'label' => 'Notur Version',
            'value' => $noturVersion,
            'status' => 'success',
            'message' => 'Up to date',
        ];

        // PHP Version
        $phpVersion = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;
        $phpStatus = version_compare(PHP_VERSION, '8.2.0', '>=') ? 'success' : 'error';
        $checks[] = [
            'label' => 'PHP Version',
            'value' => $phpVersion,
            'status' => $phpStatus,
            'message' => $phpStatus === 'success' ? 'Compatible' : 'Requires PHP 8.2+',
        ];

        // Laravel Version
        $laravelVersion = app()->version();
        $laravelStatus = version_compare($laravelVersion, '10.0.0', '>=') ? 'success' : 'warning';
        $checks[] = [
            'label' => 'Laravel',
            'value' => $laravelVersion,
            'status' => $laravelStatus,
            'message' => $laravelStatus === 'success' ? 'Compatible' : 'Recommend Laravel 10+',
        ];

        // Panel Version
        $panelVersion = $this->getPanelVersion();
        $checks[] = [
            'label' => 'Panel Version',
            'value' => $panelVersion ?: 'Unknown',
            'status' => $panelVersion ? 'success' : 'warning',
            'message' => $panelVersion ? 'Supported' : 'Could not detect',
        ];

        // Extensions count
        $extCount = count($this->manager->all());
        $enabledCount = count(array_filter(
            $this->manager->all(),
            fn ($ext) => $ext->enabled ?? true
        ));
        $checks[] = [
            'label' => 'Extensions',
            'value' => "{$enabledCount}/{$extCount}",
            'status' => 'success',
            'message' => "{$enabledCount} enabled",
        ];

        return $checks;
    }

    /**
     * Render a health check row.
     */
    private function renderHealthRow(
        string $label,
        string $value,
        string $status,
        ?string $message = null
    ): void {
        $indicator = match ($status) {
            'success' => NoturTheme::successIndicator(),
            'warning' => NoturTheme::warningIndicator(),
            'error' => NoturTheme::errorIndicator(),
            default => NoturTheme::pendingIndicator(),
        };

        $statusText = $message ?? ucfirst($status);
        $statusColored = match ($status) {
            'success' => NoturTheme::success($statusText),
            'warning' => NoturTheme::warning($statusText),
            'error' => NoturTheme::error($statusText),
            default => NoturTheme::muted($statusText),
        };

        $this->command->line(sprintf(
            '  %-18s %-12s %s %s',
            $label,
            $value,
            $indicator,
            $statusColored
        ));
    }

    /**
     * Render extensions section.
     */
    public function renderExtensions(): void
    {
        $this->renderSectionHeader('Installed Extensions');

        $extensions = $this->manager->all();

        if (empty($extensions)) {
            $this->command->line('  ' . NoturTheme::muted('No extensions installed.'));
            $this->command->line('  ' . NoturTheme::muted('Run: php artisan notur:install --browse'));
            $this->command->newLine();

            return;
        }

        foreach ($extensions as $ext) {
            $this->renderExtensionRow($ext);
        }

        // Summary line
        $total = count($extensions);
        $enabled = count(array_filter($extensions, fn ($e) => $e->enabled ?? true));
        $disabled = $total - $enabled;

        $this->command->newLine();
        $this->command->line(sprintf(
            '  %s installed  •  %s enabled  •  %s disabled',
            NoturTheme::bold((string) $total),
            NoturTheme::success((string) $enabled),
            $disabled > 0 ? NoturTheme::warning((string) $disabled) : NoturTheme::muted('0')
        ));

        $this->command->newLine();
    }

    /**
     * Render a single extension row.
     *
     * @param object $extension
     */
    private function renderExtensionRow(object $extension): void
    {
        $id = $extension->extension_id ?? 'unknown';
        $version = $extension->version ?? '?';
        $enabled = $extension->enabled ?? true;

        $indicator = $enabled
            ? NoturTheme::successIndicator()
            : NoturTheme::errorIndicator();

        $status = $enabled
            ? NoturTheme::success('Enabled')
            : NoturTheme::muted('Disabled');

        // Check for updates (simplified - would need registry check)
        $updateAvailable = '';

        $this->command->line(sprintf(
            '  %s %-30s %-8s %-10s %s',
            $indicator,
            $id,
            "v{$version}",
            $status,
            $updateAvailable
        ));
    }

    /**
     * Render quick actions section.
     */
    public function renderQuickActions(): void
    {
        $this->renderSectionHeader('Quick Actions');

        $actions = [
            ['key' => 'notur:install --browse', 'desc' => 'Browse & install extensions'],
            ['key' => 'notur:update --all', 'desc' => 'Update all extensions'],
            ['key' => 'notur:list', 'desc' => 'List installed extensions'],
            ['key' => 'notur:new', 'desc' => 'Create a new extension'],
        ];

        foreach ($actions as $action) {
            $this->command->line(sprintf(
                '  %s  %s',
                NoturTheme::primary($action['key']),
                NoturTheme::muted($action['desc'])
            ));
        }

        $this->command->newLine();
    }

    /**
     * Render a section header.
     */
    private function renderSectionHeader(string $title): void
    {
        $box = NoturTheme::BOX_T_RIGHT . NoturTheme::line(2) . ' ' . $title . ' ';
        $this->command->line($box . NoturTheme::line(50 - mb_strlen($title)));
        $this->command->newLine();
    }

    /**
     * Get Notur version.
     */
    private function getNoturVersion(): string
    {
        $composerFile = base_path('vendor/notur/notur/composer.json');

        if (file_exists($composerFile)) {
            $composer = json_decode(file_get_contents($composerFile), true);

            return $composer['version'] ?? '1.x';
        }

        return '1.x';
    }

    /**
     * Get Pterodactyl Panel version.
     */
    private function getPanelVersion(): ?string
    {
        // Try config
        $version = config('app.version');
        if ($version) {
            return $version;
        }

        // Try composer.lock
        $lockFile = base_path('composer.lock');
        if (file_exists($lockFile)) {
            $lock = json_decode(file_get_contents($lockFile), true);
            foreach ($lock['packages'] ?? [] as $package) {
                if ($package['name'] === 'pterodactyl/panel') {
                    return $package['version'] ?? null;
                }
            }
        }

        return null;
    }

    /**
     * Render as JSON.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $extensions = [];
        foreach ($this->manager->all() as $ext) {
            $extensions[] = [
                'id' => $ext->extension_id ?? 'unknown',
                'version' => $ext->version ?? '?',
                'enabled' => $ext->enabled ?? true,
            ];
        }

        return [
            'notur_version' => $this->getNoturVersion(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'panel_version' => $this->getPanelVersion(),
            'extensions' => $extensions,
            'extensions_total' => count($extensions),
            'extensions_enabled' => count(array_filter($extensions, fn ($e) => $e['enabled'])),
        ];
    }
}
