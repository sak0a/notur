<?php

declare(strict_types=1);

namespace Notur\Cs2Modframework\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GitHubReleaseResolver
{
    private const CACHE_TTL = 300; // 5 minutes
    private const CACHE_KEY = 'cs2-modframework:versions';
    private const VERSION_LIMIT = 30;

    private const SWIFTLY_REPO = 'swiftly-solution/swiftlys2';
    private const CSS_REPO = 'roflmuffin/CounterStrikeSharp';
    private const METAMOD_LATEST_URL = 'https://mms.alliedmods.net/mmsdrop/2.0/mmsource-latest-linux';
    private const METAMOD_BASE_URL = 'https://mms.alliedmods.net/mmsdrop/2.0/';

    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'Notur-CS2-ModFramework/1.0',
            ],
        ]);
    }

    public function getLatestVersions(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return [
                'swiftly' => [
                    'latest' => $this->resolveSwiftly(),
                    'versions' => $this->resolveSwiftlyVersions(),
                    'project_url' => 'https://github.com/swiftly-solution/swiftlys2',
                ],
                'counterstrikesharp' => [
                    'latest' => $this->resolveCounterStrikeSharp(),
                    'versions' => $this->resolveCounterStrikeSharpVersions(),
                    'project_url' => 'https://github.com/roflmuffin/CounterStrikeSharp',
                ],
                'metamod' => [
                    'latest' => $this->resolveMetamod(),
                    'versions' => $this->resolveMetamodVersions(),
                    'project_url' => 'https://www.sourcemm.net/',
                ],
            ];
        });
    }

    public function resolveSwiftly(?string $version = null): ?array
    {
        return $this->resolveGitHubRelease(
            self::SWIFTLY_REPO,
            fn (string $name) => str_contains($name, 'linux') && str_contains($name, 'with-runtimes') && str_ends_with($name, '.zip'),
            $version,
        );
    }

    public function resolveCounterStrikeSharp(?string $version = null): ?array
    {
        return $this->resolveGitHubRelease(
            self::CSS_REPO,
            fn (string $name) => str_contains($name, 'with-runtime') && str_contains($name, 'linux') && str_ends_with($name, '.zip'),
            $version,
        );
    }

    public function resolveSwiftlyVersions(): array
    {
        return $this->resolveGitHubReleaseList(
            self::SWIFTLY_REPO,
            fn (string $name) => str_contains($name, 'linux') && str_contains($name, 'with-runtimes') && str_ends_with($name, '.zip'),
        );
    }

    public function resolveCounterStrikeSharpVersions(): array
    {
        return $this->resolveGitHubReleaseList(
            self::CSS_REPO,
            fn (string $name) => str_contains($name, 'with-runtime') && str_contains($name, 'linux') && str_ends_with($name, '.zip'),
        );
    }

    public function resolveMetamod(?string $version = null): ?array
    {
        try {
            if ($version !== null && $version !== '') {
                if (preg_match('/^[\d.]+-git\d+$/', $version) !== 1) {
                    return null;
                }

                $filename = "mmsource-{$version}-linux.tar.gz";
                $this->client->head(self::METAMOD_BASE_URL . $filename);
            } else {
                $response = $this->client->get(self::METAMOD_LATEST_URL);
                $filename = trim($response->getBody()->__toString());
            }

            if (empty($filename)) {
                return null;
            }

            // Parse version from filename: mmsource-2.0.0-git1384-linux.tar.gz
            $version = $filename;
            if (preg_match('/mmsource-([\d.]+-git\d+)/', $filename, $matches)) {
                $version = $matches[1];
            }

            return [
                'version' => $version,
                'download_url' => self::METAMOD_BASE_URL . $filename,
                'filename' => $filename,
            ];
        } catch (GuzzleException $e) {
            Log::warning('CS2 ModFramework: Failed to resolve Metamod version', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function resolveMetamodVersions(): array
    {
        try {
            $response = $this->client->get(self::METAMOD_BASE_URL);
            $html = $response->getBody()->__toString();
            preg_match_all('/mmsource-([\d.]+-git\d+)-linux\.tar\.gz/', $html, $matches);

            $versions = array_values(array_unique($matches[1] ?? []));
            usort($versions, function (string $a, string $b): int {
                preg_match('/git(\d+)$/', $a, $aMatch);
                preg_match('/git(\d+)$/', $b, $bMatch);

                return ((int) ($bMatch[1] ?? 0)) <=> ((int) ($aMatch[1] ?? 0));
            });

            $items = [];
            foreach (array_slice($versions, 0, self::VERSION_LIMIT) as $version) {
                $filename = "mmsource-{$version}-linux.tar.gz";
                $items[] = [
                    'version' => $version,
                    'download_url' => self::METAMOD_BASE_URL . $filename,
                    'filename' => $filename,
                ];
            }

            return $items;
        } catch (GuzzleException $e) {
            Log::warning('CS2 ModFramework: Failed to resolve Metamod version list', [
                'error' => $e->getMessage(),
            ]);

            $latest = $this->resolveMetamod();
            return $latest !== null ? [$latest] : [];
        }
    }

    private function resolveGitHubRelease(string $repo, callable $assetMatcher, ?string $version = null): ?array
    {
        try {
            $endpoint = $version !== null && $version !== ''
                ? "https://api.github.com/repos/{$repo}/releases/tags/" . (str_starts_with($version, 'v') ? $version : "v{$version}")
                : "https://api.github.com/repos/{$repo}/releases/latest";
            $response = $this->client->get($endpoint);
            $release = json_decode($response->getBody()->__toString(), true);

            $tagName = $release['tag_name'] ?? null;
            $assets = $release['assets'] ?? [];

            foreach ($assets as $asset) {
                $name = $asset['name'] ?? '';
                if ($assetMatcher($name)) {
                    return [
                        'version' => ltrim($tagName, 'v'),
                        'download_url' => $asset['browser_download_url'],
                        'filename' => $name,
                    ];
                }
            }

            Log::warning("CS2 ModFramework: No matching asset found for {$repo}", [
                'tag' => $tagName,
                'assets' => array_column($assets, 'name'),
            ]);

            return null;
        } catch (GuzzleException $e) {
            Log::warning("CS2 ModFramework: Failed to resolve {$repo} release", [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function resolveGitHubReleaseList(string $repo, callable $assetMatcher): array
    {
        try {
            $response = $this->client->get("https://api.github.com/repos/{$repo}/releases", [
                'query' => ['per_page' => self::VERSION_LIMIT],
            ]);
            $releases = json_decode($response->getBody()->__toString(), true);

            if (!is_array($releases)) {
                return [];
            }

            $items = [];
            foreach ($releases as $release) {
                $tagName = $release['tag_name'] ?? null;
                if (!is_string($tagName) || $tagName === '') {
                    continue;
                }

                foreach (($release['assets'] ?? []) as $asset) {
                    $name = $asset['name'] ?? '';
                    $downloadUrl = $asset['browser_download_url'] ?? '';
                    if (is_string($name) && is_string($downloadUrl) && $assetMatcher($name)) {
                        $items[] = [
                            'version' => ltrim($tagName, 'v'),
                            'download_url' => $downloadUrl,
                            'filename' => $name,
                        ];
                        break;
                    }
                }
            }

            return $items;
        } catch (GuzzleException $e) {
            Log::warning("CS2 ModFramework: Failed to resolve {$repo} release list", [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
