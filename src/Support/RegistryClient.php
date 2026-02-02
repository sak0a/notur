<?php

declare(strict_types=1);

namespace Notur\Support;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

class RegistryClient
{
    private const INDEX_FILE = 'registry.json';
    private const DEFAULT_CACHE_TTL = 3600; // 1 hour

    private ?array $index = null;

    public function __construct(
        private readonly Client $client,
        private readonly string $registryUrl = 'https://raw.githubusercontent.com/notur/registry/main',
        private readonly int $cacheTtl = self::DEFAULT_CACHE_TTL,
        private readonly ?string $cachePath = null,
    ) {}

    /**
     * Fetch the registry index from the remote registry.
     *
     * @throws RuntimeException If the HTTP request fails.
     */
    public function fetchIndex(): array
    {
        if ($this->index !== null) {
            return $this->index;
        }

        $url = rtrim($this->registryUrl, '/') . '/' . self::INDEX_FILE;

        try {
            $response = $this->client->get($url, [
                'timeout' => 30,
                'connect_timeout' => 10,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'Notur-RegistryClient/1.0',
                ],
            ]);
        } catch (GuzzleException $e) {
            throw new RuntimeException(
                "Failed to fetch registry index from {$url}: {$e->getMessage()}",
                (int) $e->getCode(),
                $e,
            );
        }

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException(
                "Registry returned HTTP {$response->getStatusCode()} for {$url}"
            );
        }

        $body = $response->getBody()->getContents();
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Registry index is not valid JSON');
        }

        $this->index = $decoded;

        return $this->index;
    }

    /**
     * Search for extensions matching a keyword query.
     *
     * Searches against extension ID, name, description, and tags.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query): array
    {
        $index = $this->getIndexWithCache();
        $results = [];
        $lowerQuery = strtolower($query);

        foreach ($index['extensions'] ?? [] as $ext) {
            if (
                str_contains(strtolower($ext['id'] ?? ''), $lowerQuery)
                || str_contains(strtolower($ext['name'] ?? ''), $lowerQuery)
                || str_contains(strtolower($ext['description'] ?? ''), $lowerQuery)
                || $this->matchesTags($ext['tags'] ?? [], $lowerQuery)
            ) {
                $results[] = $ext;
            }
        }

        return $results;
    }

    /**
     * Get metadata for a specific extension by its ID.
     *
     * @return array<string, mixed>|null Extension metadata or null if not found.
     */
    public function getExtension(string $extensionId): ?array
    {
        $index = $this->getIndexWithCache();

        foreach ($index['extensions'] ?? [] as $ext) {
            if ($ext['id'] === $extensionId) {
                return $ext;
            }
        }

        return null;
    }

    /**
     * Download an extension archive from its repository.
     *
     * @throws RuntimeException If the extension is not found or download fails.
     */
    public function download(string $extensionId, string $version, string $targetPath): void
    {
        $ext = $this->getExtension($extensionId);

        if ($ext === null) {
            throw new RuntimeException("Extension '{$extensionId}' not found in registry");
        }

        $repo = $ext['repository'] ?? null;
        if (!$repo) {
            throw new RuntimeException("Extension '{$extensionId}' has no repository URL");
        }

        // Build download URL: {repo}/releases/download/v{version}/{vendor-name}-{version}.notur
        $archiveName = str_replace('/', '-', $extensionId) . "-{$version}.notur";
        $downloadUrl = rtrim($repo, '/') . "/releases/download/v{$version}/{$archiveName}";

        try {
            $response = $this->client->get($downloadUrl, [
                'sink' => $targetPath,
                'timeout' => 120,
                'connect_timeout' => 10,
            ]);
        } catch (GuzzleException $e) {
            throw new RuntimeException(
                "Failed to download extension '{$extensionId}' v{$version}: {$e->getMessage()}",
                (int) $e->getCode(),
                $e,
            );
        }

        if ($response->getStatusCode() !== 200) {
            @unlink($targetPath);
            throw new RuntimeException(
                "Download returned HTTP {$response->getStatusCode()} for '{$extensionId}' v{$version}"
            );
        }
    }

    /**
     * Sync the remote registry index to a local cache file.
     *
     * @return int Number of extensions in the synced index.
     */
    public function syncToCache(string $cachePath): int
    {
        $index = $this->fetchIndex();

        $dir = dirname($cachePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $cacheData = [
            'fetched_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'registry' => $index,
        ];

        file_put_contents(
            $cachePath,
            json_encode($cacheData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );

        return count($index['extensions'] ?? []);
    }

    /**
     * Load registry data from a local cache file.
     *
     * @return array<string, mixed>|null Cached registry data, or null if cache is missing/expired.
     */
    public function loadFromCache(string $cachePath, bool $ignoreExpiry = false): ?array
    {
        if (!file_exists($cachePath)) {
            return null;
        }

        $raw = file_get_contents($cachePath);
        if ($raw === false) {
            return null;
        }

        $cacheData = json_decode($raw, true);
        if (!is_array($cacheData)) {
            return null;
        }

        // Support both wrapped (fetched_at + registry) and unwrapped formats
        if (isset($cacheData['fetched_at'], $cacheData['registry'])) {
            if (!$ignoreExpiry) {
                $fetchedAt = strtotime($cacheData['fetched_at']);
                if ($fetchedAt !== false && (time() - $fetchedAt) > $this->cacheTtl) {
                    return null; // Cache expired
                }
            }
            return $cacheData['registry'];
        }

        // Unwrapped legacy format — no expiry check possible
        return $cacheData;
    }

    /**
     * Check if the local cache is still valid (not expired).
     */
    public function isCacheFresh(string $cachePath): bool
    {
        return $this->loadFromCache($cachePath) !== null;
    }

    /**
     * Get the index, preferring local cache if available and fresh.
     */
    private function getIndexWithCache(): array
    {
        if ($this->index !== null) {
            return $this->index;
        }

        $cachePath = $this->resolveCachePath();
        if ($cachePath !== null) {
            $cached = $this->loadFromCache($cachePath);
            if ($cached !== null) {
                $this->index = $cached;
                return $this->index;
            }
        }

        return $this->fetchIndex();
    }

    /**
     * Resolve the cache path from constructor arg or config.
     */
    private function resolveCachePath(): ?string
    {
        if ($this->cachePath !== null) {
            return $this->cachePath;
        }

        try {
            if (function_exists('config') && function_exists('app') && app()->bound('config')) {
                $path = config('notur.registry_cache_path');
                return is_string($path) ? $path : null;
            }
        } catch (\Throwable) {
            // No Laravel container available
        }

        return null;
    }

    /**
     * Check if any of the extension's tags match the query.
     */
    private function matchesTags(array $tags, string $lowerQuery): bool
    {
        foreach ($tags as $tag) {
            if (str_contains(strtolower((string) $tag), $lowerQuery)) {
                return true;
            }
        }

        return false;
    }
}
