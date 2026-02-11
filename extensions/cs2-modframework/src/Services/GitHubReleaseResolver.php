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
                'swiftly' => $this->resolveSwiftly(),
                'counterstrikesharp' => $this->resolveCounterStrikeSharp(),
                'metamod' => $this->resolveMetamod(),
            ];
        });
    }

    public function resolveSwiftly(): ?array
    {
        return $this->resolveGitHubRelease(
            self::SWIFTLY_REPO,
            fn (string $name) => str_contains($name, 'linux') && str_contains($name, 'with-runtimes') && str_ends_with($name, '.zip'),
        );
    }

    public function resolveCounterStrikeSharp(): ?array
    {
        return $this->resolveGitHubRelease(
            self::CSS_REPO,
            fn (string $name) => str_contains($name, 'with-runtime') && str_contains($name, 'linux') && str_ends_with($name, '.zip'),
        );
    }

    public function resolveMetamod(): ?array
    {
        try {
            $response = $this->client->get(self::METAMOD_LATEST_URL);
            $filename = trim($response->getBody()->__toString());

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

    private function resolveGitHubRelease(string $repo, callable $assetMatcher): ?array
    {
        try {
            $response = $this->client->get("https://api.github.com/repos/{$repo}/releases/latest");
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
}
