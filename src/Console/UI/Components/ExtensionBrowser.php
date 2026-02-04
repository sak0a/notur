<?php

declare(strict_types=1);

namespace Notur\Console\UI\Components;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Notur\Console\UI\Themes\NoturTheme;
use Notur\Support\RegistryClient;

use function Laravel\Prompts\note;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;

/**
 * Interactive extension browser for the registry.
 *
 * Provides search, filtering, and detail views for
 * discovering and selecting extensions to install.
 */
class ExtensionBrowser
{
    public function __construct(
        private readonly Command $command,
        private readonly RegistryClient $registry,
    ) {}

    /**
     * Browse extensions interactively.
     *
     * @return array<string, mixed>|null Selected extension data or null if cancelled
     */
    public function browse(?string $initialSearch = null): ?array
    {
        while (true) {
            $selected = $this->searchExtensions($initialSearch);

            if ($selected === null || $selected === '' || $selected === 'cancel') {
                return null;
            }

            $extension = $this->getExtensionDetails($selected);

            if ($extension === null) {
                $this->command->error("Could not fetch details for: {$selected}");

                continue;
            }

            $action = $this->showExtensionDetail($extension);

            if ($action === 'install') {
                return $extension;
            }

            if ($action === 'cancel') {
                return null;
            }

            // action === 'back' - continue loop to search again
            $initialSearch = null;
        }
    }

    /**
     * Search for extensions.
     */
    private function searchExtensions(?string $initialSearch): ?string
    {
        if (function_exists('Laravel\Prompts\search')) {
            return search(
                label: 'Search extensions',
                placeholder: $initialSearch ?? 'Type to search the registry...',
                options: fn (string $query) => $this->formatSearchResults(
                    $this->registry->search($query ?: '')
                ),
                scroll: 10,
                hint: 'Enter to select, Esc to cancel',
            );
        }

        // Fallback for non-interactive or missing Prompts
        $query = $this->command->ask('Search for extensions', $initialSearch ?? '');
        $results = $this->registry->search($query ?? '');

        if (empty($results)) {
            $this->command->warn('No extensions found.');

            return null;
        }

        $options = $this->formatSearchResults($results);
        $options['cancel'] = 'Cancel';

        return $this->command->choice('Select an extension', array_values($options));
    }

    /**
     * Format search results for display.
     *
     * @param array<int, array<string, mixed>> $results
     * @return array<string, string>
     */
    private function formatSearchResults(array $results): array
    {
        $options = [];

        foreach ($results as $ext) {
            $id = $ext['id'] ?? $ext['extension_id'] ?? 'unknown';
            $version = $ext['latest_version'] ?? $ext['version'] ?? '?';
            $description = Str::limit($ext['description'] ?? 'No description', 45);

            // Format: id    version    description
            $label = sprintf(
                '%-30s  v%-8s  %s',
                $id,
                $version,
                $description
            );

            // Add rating and downloads if available
            if (isset($ext['rating']) || isset($ext['downloads'])) {
                $meta = [];
                if (isset($ext['rating'])) {
                    $meta[] = NoturTheme::INDICATOR_STAR . ' ' . number_format((float) $ext['rating'], 1);
                }
                if (isset($ext['downloads'])) {
                    $downloads = (int) $ext['downloads'];
                    $meta[] = NoturTheme::INDICATOR_ARROW_DOWN . ' ' . $this->formatDownloads($downloads);
                }
                $label .= '  ' . implode('  ', $meta);
            }

            $options[$id] = $label;
        }

        return $options;
    }

    /**
     * Get detailed extension information.
     *
     * @return array<string, mixed>|null
     */
    private function getExtensionDetails(string $extensionId): ?array
    {
        try {
            return $this->registry->getExtension($extensionId);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Show extension detail view with actions.
     *
     * @param array<string, mixed> $extension
     */
    private function showExtensionDetail(array $extension): string
    {
        $id = $extension['id'] ?? $extension['extension_id'] ?? 'unknown';
        $name = $extension['name'] ?? $id;
        $version = $extension['latest_version'] ?? $extension['version'] ?? '?';
        $description = $extension['description'] ?? 'No description';

        // Build detail display
        $this->command->newLine();
        $this->command->line(NoturTheme::bold(NoturTheme::primary($name)));
        $this->command->line(NoturTheme::muted($id) . '  ' . NoturTheme::info("v{$version}"));
        $this->command->newLine();
        $this->command->line($description);
        $this->command->newLine();

        // Metadata table
        $this->renderMetadataTable($extension);

        // Features list
        if (isset($extension['features']) && is_array($extension['features'])) {
            $this->command->newLine();
            $this->command->line(NoturTheme::bold('Features:'));
            foreach ($extension['features'] as $feature) {
                $this->command->line('  ' . NoturTheme::success('•') . ' ' . $feature);
            }
        }

        // Dependencies
        if (isset($extension['requires']) && is_array($extension['requires'])) {
            $this->command->newLine();
            $this->command->line(NoturTheme::bold('Requirements:'));
            foreach ($extension['requires'] as $dep => $constraint) {
                $this->command->line('  ' . NoturTheme::muted('•') . " {$dep} {$constraint}");
            }
        }

        $this->command->newLine();

        // Action selection
        if (function_exists('Laravel\Prompts\select')) {
            return select(
                label: 'Action',
                options: [
                    'install' => NoturTheme::success('Install this extension'),
                    'back' => 'Back to search',
                    'cancel' => 'Cancel',
                ],
                default: 'install',
            );
        }

        $choice = $this->command->choice('Action', [
            'Install this extension',
            'Back to search',
            'Cancel',
        ], 0);

        return match ($choice) {
            'Install this extension' => 'install',
            'Back to search' => 'back',
            default => 'cancel',
        };
    }

    /**
     * Render metadata as a formatted table.
     *
     * @param array<string, mixed> $extension
     */
    private function renderMetadataTable(array $extension): void
    {
        $rows = [];

        if (isset($extension['author'])) {
            $rows[] = ['Author', $extension['author']];
        }
        if (isset($extension['license'])) {
            $rows[] = ['License', $extension['license']];
        }
        if (isset($extension['downloads'])) {
            $rows[] = ['Downloads', $this->formatDownloads((int) $extension['downloads'])];
        }
        if (isset($extension['rating'])) {
            $rating = (float) $extension['rating'];
            $rows[] = ['Rating', NoturTheme::stars($rating) . " ({$rating})"];
        }
        if (isset($extension['updated_at'])) {
            $rows[] = ['Updated', $extension['updated_at']];
        }

        if (empty($rows)) {
            return;
        }

        foreach ($rows as [$label, $value]) {
            $this->command->line(
                NoturTheme::muted(str_pad($label . ':', 15)) . $value
            );
        }
    }

    /**
     * Format download count.
     */
    private function formatDownloads(int $count): string
    {
        if ($count >= 1000000) {
            return number_format($count / 1000000, 1) . 'M';
        }
        if ($count >= 1000) {
            return number_format($count / 1000, 1) . 'k';
        }

        return (string) $count;
    }

    /**
     * Browse by category.
     *
     * @return array<string, mixed>|null
     */
    public function browseByCategory(): ?array
    {
        $categories = $this->getCategories();

        if (empty($categories)) {
            $this->command->warn('No categories available.');

            return $this->browse();
        }

        if (function_exists('Laravel\Prompts\select')) {
            $category = select(
                label: 'Select a category',
                options: $categories,
                scroll: 10,
            );
        } else {
            $category = $this->command->choice('Select a category', array_values($categories));
            $category = array_search($category, $categories, true) ?: $category;
        }

        if ($category === 'all' || $category === '') {
            return $this->browse();
        }

        // Search within category
        return $this->browse($category);
    }

    /**
     * Get available categories.
     *
     * @return array<string, string>
     */
    private function getCategories(): array
    {
        return [
            'all' => 'All Extensions',
            'themes' => 'Themes & Styling',
            'tools' => 'Tools & Utilities',
            'integrations' => 'Integrations',
            'admin' => 'Admin Extensions',
            'security' => 'Security',
        ];
    }
}
