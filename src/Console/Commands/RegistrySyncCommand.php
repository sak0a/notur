<?php

declare(strict_types=1);

namespace Notur\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Notur\Support\RegistryClient;

class RegistrySyncCommand extends Command
{
    protected $signature = 'notur:registry:sync
        {--search= : Search for extensions by keyword}
        {--force : Bypass cache TTL and force a fresh fetch}';

    protected $description = 'Sync the extension registry index';

    public function handle(RegistryClient $registry): int
    {
        $cachePath = config('notur.registry_cache_path');

        if ($search = $this->option('search')) {
            return $this->handleSearch($registry, $search);
        }

        return $this->handleSync($registry, $cachePath);
    }

    private function handleSearch(RegistryClient $registry, string $search): int
    {
        $this->info("Searching registry for: {$search}");

        try {
            $results = $registry->search($search);
        } catch (\Throwable $e) {
            $this->error("Registry search failed: {$e->getMessage()}");
            return 1;
        }

        if (empty($results)) {
            $this->info('No extensions found.');
            return 0;
        }

        $this->table(
            ['ID', 'Name', 'Version', 'Description'],
            array_map(fn (array $r): array => [
                $r['id'],
                $r['name'] ?? '',
                $r['latest_version'] ?? $r['version'] ?? '',
                Str::limit($r['description'] ?? '', 50),
            ], $results),
        );

        return 0;
    }

    private function handleSync(RegistryClient $registry, string $cachePath): int
    {
        $force = (bool) $this->option('force');

        // Check if cache is still fresh (unless --force is used)
        if (!$force && $registry->isCacheFresh($cachePath)) {
            $cached = $registry->loadFromCache($cachePath);
            $count = count($cached['extensions'] ?? []);
            $this->info("Registry cache is up to date ({$count} extension(s) available).");
            $this->line("Use --force to bypass cache TTL.");
            return 0;
        }

        $this->info('Syncing registry index...');

        try {
            $count = $registry->syncToCache($cachePath);
        } catch (\Throwable $e) {
            $this->error("Sync failed: {$e->getMessage()}");
            $this->line('Check your network connection and the registry URL in config/notur.php.');
            return 1;
        }

        $this->info("Registry synced to: {$cachePath}");
        $this->info("{$count} extension(s) available.");

        return 0;
    }
}
