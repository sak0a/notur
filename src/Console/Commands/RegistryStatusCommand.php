<?php

declare(strict_types=1);

namespace Notur\Console\Commands;

use Illuminate\Console\Command;

class RegistryStatusCommand extends Command
{
    protected $signature = 'notur:registry:status
        {--json : Output status as JSON}';

    protected $description = 'Show extension registry cache status';

    public function handle(): int
    {
        $cachePath = config('notur.registry_cache_path');

        if (!is_string($cachePath) || $cachePath === '') {
            $this->error('Registry cache path is not configured.');
            return 1;
        }

        if (!file_exists($cachePath)) {
            $this->info('Registry cache not found. Run `notur:registry:sync` to create it.');
            return 0;
        }

        $raw = file_get_contents($cachePath);
        if ($raw === false) {
            $this->error('Failed to read registry cache.');
            return 1;
        }

        $cacheData = json_decode($raw, true);
        if (!is_array($cacheData)) {
            $this->error('Registry cache is not valid JSON.');
            return 1;
        }

        $fetchedAtRaw = is_string($cacheData['fetched_at'] ?? null) ? $cacheData['fetched_at'] : null;
        $registryData = is_array($cacheData['registry'] ?? null) ? $cacheData['registry'] : $cacheData;

        $count = is_array($registryData['extensions'] ?? null)
            ? count($registryData['extensions'])
            : 0;

        $cacheSize = filesize($cachePath) ?: 0;
        $ttl = (int) config('notur.registry_cache_ttl', 3600);

        $fetchedAtTs = $fetchedAtRaw ? strtotime($fetchedAtRaw) : false;
        $ageSeconds = $fetchedAtTs !== false ? max(0, time() - $fetchedAtTs) : null;

        $status = 'unknown';
        if ($ttl <= 0) {
            $status = 'no-expiry';
        } elseif ($ageSeconds !== null) {
            $status = $ageSeconds <= $ttl ? 'fresh' : 'expired';
        }

        if ((bool) $this->option('json')) {
            $payload = [
                'path' => $cachePath,
                'last_sync' => $fetchedAtRaw,
                'age_seconds' => $ageSeconds,
                'ttl_seconds' => $ttl,
                'status' => $status,
                'extensions' => $count,
                'cache_size_bytes' => $cacheSize,
            ];

            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return 0;
        }

        $this->info('Registry Cache Status');
        $this->line('Path: ' . $cachePath);
        $this->line('Last sync: ' . ($fetchedAtRaw ?: 'unknown'));
        if ($fetchedAtTs !== false) {
            $this->line('Last sync (local): ' . date('Y-m-d H:i:s', $fetchedAtTs));
        }
        if ($ageSeconds !== null) {
            $this->line('Age: ' . $this->formatDuration($ageSeconds));
        }
        $this->line('TTL: ' . ($ttl <= 0 ? 'disabled' : $this->formatDuration($ttl)));
        $this->line('Status: ' . $status);
        $this->line('Extensions: ' . $count);
        $this->line('Cache size: ' . $this->formatBytes($cacheSize));

        return 0;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = $bytes;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return sprintf('%.1f %s', $size, $units[$unitIndex]);
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's';
        }

        $minutes = intdiv($seconds, 60);
        if ($minutes < 60) {
            return $minutes . 'm';
        }

        $hours = intdiv($minutes, 60);
        $minutes = $minutes % 60;
        if ($hours < 24) {
            return sprintf('%dh %dm', $hours, $minutes);
        }

        $days = intdiv($hours, 24);
        $hours = $hours % 24;

        return sprintf('%dd %dh', $days, $hours);
    }
}
