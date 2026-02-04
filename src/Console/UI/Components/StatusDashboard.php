<?php

declare(strict_types=1);

namespace Notur\Console\UI\Components;

use Illuminate\Console\Command;
use Composer\InstalledVersions;
use Notur\Console\UI\Themes\NoturTheme;
use Notur\ExtensionManager;
use Notur\Models\InstalledExtension;
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
        $extensions = InstalledExtension::all();
        $extCount = $extensions->count();
        $enabledCount = $extensions->where('enabled', true)->count();
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

        $extensions = InstalledExtension::all();

        if ($extensions->isEmpty()) {
            $this->command->line('  ' . NoturTheme::muted('No extensions installed.'));
            $this->command->line('  ' . NoturTheme::muted('Run: php artisan notur:install <extension-id>'));
            $this->command->newLine();

            return;
        }

        foreach ($extensions as $ext) {
            $this->renderExtensionRow($ext);
        }

        // Summary line
        $total = $extensions->count();
        $enabled = $extensions->where('enabled', true)->count();
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
     */
    private function renderExtensionRow(InstalledExtension $extension): void
    {
        $id = $extension->extension_id;
        $version = $extension->version;
        $enabled = $extension->enabled;

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
            ['key' => 'notur:install <id>', 'desc' => 'Install an extension'],
            ['key' => 'notur:update', 'desc' => 'Update all extensions'],
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
        if (class_exists(InstalledVersions::class)) {
            $version = InstalledVersions::getPrettyVersion('notur/notur');
            if ($version) {
                return $version;
            }
        }

        $lockFile = base_path('composer.lock');
        if (file_exists($lockFile)) {
            $lock = json_decode(file_get_contents($lockFile), true);
            foreach (array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []) as $package) {
                if (($package['name'] ?? null) === 'notur/notur') {
                    return $package['version'] ?? $package['pretty_version'] ?? '1.x';
                }
            }
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
        $installedExtensions = InstalledExtension::all();

        $extensions = $installedExtensions->map(fn (InstalledExtension $ext) => [
            'id' => $ext->extension_id,
            'version' => $ext->version,
            'enabled' => $ext->enabled,
        ])->toArray();

        return [
            'notur_version' => $this->getNoturVersion(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'panel_version' => $this->getPanelVersion(),
            'extensions' => $extensions,
            'extensions_total' => count($extensions),
            'extensions_enabled' => $installedExtensions->where('enabled', true)->count(),
        ];
    }
}
