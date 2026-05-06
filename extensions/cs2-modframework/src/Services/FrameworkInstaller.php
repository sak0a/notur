<?php

declare(strict_types=1);

namespace Notur\Cs2Modframework\Services;

use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Illuminate\Support\Facades\Log;

class FrameworkInstaller
{
    private const ADDONS_DIR = '/game/csgo/addons';
    private const CSGO_DIR = '/game/csgo';
    private const VERSION_STATE_PATH = '/game/csgo/addons/.notur-framework-versions.json';

    private const FRAMEWORK_DIRS = [
        'swiftly' => 'swiftlys2',
        'counterstrikesharp' => 'counterstrikesharp',
        'metamod' => 'metamod',
    ];

    private const FRAMEWORK_DIR_ALIASES = [
        'swiftly' => ['swiftlys2', 'swiftly'],
        'counterstrikesharp' => ['counterstrikesharp'],
        'metamod' => ['metamod'],
    ];

    private const FRAMEWORK_MARKERS = [
        'swiftly' => [
            '/game/csgo/addons/swiftlys2' => ['core', 'extensions', 'plugins', 'bin'],
            '/game/csgo/addons/swiftly' => ['core', 'extensions', 'plugins', 'bin'],
        ],
        'counterstrikesharp' => [
            '/game/csgo/addons/counterstrikesharp' => ['api', 'bin', 'plugins', 'gamedata', 'configs', 'CounterStrikeSharp.API.dll'],
            '/game/csgo/addons/CounterStrikeSharp' => ['api', 'bin', 'plugins', 'gamedata', 'configs', 'CounterStrikeSharp.API.dll'],
            '/game/csgo/addons/metamod' => ['counterstrikesharp.vdf'],
            '/game/csgo/addons/MetaMod' => ['counterstrikesharp.vdf'],
        ],
        'metamod' => [
            '/game/csgo/addons/metamod' => ['bin', 'metaplugins.ini', 'plugins'],
            '/game/csgo/addons/MetaMod' => ['bin', 'metaplugins.ini', 'plugins'],
            '/game/csgo/addons' => ['metamod.vdf'],
        ],
    ];

    private const GAMEINFO_ENTRIES = [
        'swiftly' => 'Game	csgo/addons/swiftlys2',
        'metamod' => 'Game	csgo/addons/metamod',
    ];

    private const FRAMEWORK_LABELS = [
        'swiftly' => 'SwiftlyS2',
        'counterstrikesharp' => 'CounterStrikeSharp',
        'metamod' => 'Metamod:Source',
    ];

    public function __construct(
        private readonly DaemonFileRepository $fileRepository,
        private readonly GitHubReleaseResolver $releaseResolver,
        private readonly GameInfoModifier $gameInfoModifier,
        private readonly array $serverEligibility = ['supported' => true, 'reason' => null],
    ) {
    }

    public function getStatus(): array
    {
        $addons = $this->listAddons();
        $gameinfoContent = $this->readGameInfoSafe();
        $versions = $this->readVersionState();

        $status = [];
        foreach (self::FRAMEWORK_DIRS as $framework => $dir) {
            $detectedDirectory = $this->detectFrameworkDirectory($framework, $addons, $gameinfoContent);
            $installed = $detectedDirectory !== null;
            $installedVersion = $installed ? ($versions[$framework]['version'] ?? null) : null;
            $status[$framework] = [
                'installed' => $installed,
                'directory' => $detectedDirectory !== null ? "game/csgo/addons/{$detectedDirectory}" : null,
                'installed_version' => is_string($installedVersion) && $installedVersion !== '' ? $installedVersion : null,
                'restart_required' => $installed,
            ];
        }

        $status['gameinfo_entries'] = [];
        foreach (self::GAMEINFO_ENTRIES as $framework => $entry) {
            $status['gameinfo_entries'][$framework] = $gameinfoContent !== null && GameInfoModifier::hasEntryInContent($gameinfoContent, $entry);
        }

        return $status;
    }

    public function install(string $framework, ?string $version = null): array
    {
        if (!$this->isEligible()) {
            return [
                'success' => false,
                'framework' => $framework,
                'message' => $this->serverEligibility['reason'] ?? 'This server is not eligible for CS2 mod framework installation.',
            ];
        }

        $label = self::FRAMEWORK_LABELS[$framework];

        // CSS requires Metamod — auto-install if missing
        if ($framework === 'counterstrikesharp') {
            $status = $this->getStatus();
            if (!$status['metamod']['installed']) {
                $metamodResult = $this->doInstall('metamod');
                if (!$metamodResult['success']) {
                    return [
                        'success' => false,
                        'framework' => $framework,
                        'message' => "Failed to auto-install Metamod (required by {$label}): {$metamodResult['message']}",
                    ];
                }
            }
        }

        return $this->doInstall($framework, $version);
    }

    public function uninstall(string $framework): array
    {
        if (!$this->isEligible()) {
            return [
                'success' => false,
                'framework' => $framework,
                'message' => $this->serverEligibility['reason'] ?? 'This server is not eligible for CS2 mod framework management.',
            ];
        }

        $label = self::FRAMEWORK_LABELS[$framework];

        // Warn if trying to uninstall Metamod while CSS is installed
        if ($framework === 'metamod') {
            $status = $this->getStatus();
            if ($status['counterstrikesharp']['installed']) {
                return [
                    'success' => false,
                    'framework' => $framework,
                    'message' => 'Cannot uninstall Metamod while CounterStrikeSharp is installed. Please uninstall CounterStrikeSharp first.',
                ];
            }
        }

        try {
            $addons = $this->listAddons();
            $dir = $this->detectFrameworkDirectory($framework, $addons, $this->readGameInfoSafe());
            if ($dir === null) {
                return [
                    'success' => true,
                    'framework' => $framework,
                    'message' => "{$label} is not installed.",
                ];
            }

            $backupDir = $this->backupFrameworkDirectory($dir);

            // Remove gameinfo.gi entry if applicable
            $entry = self::GAMEINFO_ENTRIES[$framework] ?? null;
            if ($entry !== null) {
                $this->gameInfoModifier->removeEntry($entry);
            }

            // For CSS, also clean up the metamod VDF reference
            if ($framework === 'counterstrikesharp') {
                $this->cleanupCssMetamodFiles();
            }
            $this->removeInstalledVersion($framework);

            return [
                'success' => true,
                'framework' => $framework,
                'backup' => "game/csgo/addons/{$backupDir}",
                'message' => "{$label} uninstalled safely. Existing files were moved to game/csgo/addons/{$backupDir}.",
            ];
        } catch (\Throwable $e) {
            Log::error("CS2 ModFramework: Failed to uninstall {$framework}", [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'framework' => $framework,
                'message' => "Failed to uninstall {$label}: " . $e->getMessage(),
            ];
        }
    }

    private function doInstall(string $framework, ?string $version = null): array
    {
        $label = self::FRAMEWORK_LABELS[$framework];

        // Resolve download info
        $release = match ($framework) {
            'swiftly' => $this->releaseResolver->resolveSwiftly($version),
            'counterstrikesharp' => $this->releaseResolver->resolveCounterStrikeSharp($version),
            'metamod' => $this->releaseResolver->resolveMetamod($version),
        };

        if ($release === null) {
            return [
                'success' => false,
                'framework' => $framework,
                'message' => $version
                    ? "Could not resolve {$label} release {$version}. Check the version and try again."
                    : "Could not resolve latest {$label} release. Please try again later.",
            ];
        }

        $downloadUrl = $release['download_url'];
        $filename = $release['filename'];
        $version = $release['version'];

        try {
            // 1. Download archive to server
            $this->fileRepository->pull($downloadUrl, self::CSGO_DIR, [
                'filename' => $filename,
                'foreground' => true,
            ]);

            // 2. Extract archive
            $this->fileRepository->decompressFile(self::CSGO_DIR, $filename);

            // 3. Clean up archive
            $this->fileRepository->deleteFiles(self::CSGO_DIR, [$filename]);

            // 4. Modify gameinfo.gi if needed
            $entry = self::GAMEINFO_ENTRIES[$framework] ?? null;
            if ($entry !== null) {
                $this->gameInfoModifier->addEntry($entry);
            }
            $this->recordInstalledVersion($framework, $version);

            return [
                'success' => true,
                'framework' => $framework,
                'version' => $version,
                'message' => "{$label} v{$version} installed successfully.",
            ];
        } catch (\Throwable $e) {
            Log::error("CS2 ModFramework: Failed to install {$framework}", [
                'error' => $e->getMessage(),
                'url' => $downloadUrl,
            ]);

            // Attempt cleanup of partially downloaded file
            try {
                $this->fileRepository->deleteFiles(self::CSGO_DIR, [$filename]);
            } catch (\Throwable) {
                // Ignore cleanup failures
            }

            return [
                'success' => false,
                'framework' => $framework,
                'message' => "Failed to install {$label}: " . $e->getMessage(),
            ];
        }
    }

    private function cleanupCssMetamodFiles(): void
    {
        try {
            // CSS registers itself with Metamod via a VDF file
            $metamodDir = $this->fileRepository->getDirectory(self::ADDONS_DIR . '/metamod');
            foreach ($metamodDir as $file) {
                $name = $file['name'] ?? '';
                if (strtolower($name) === 'counterstrikesharp.vdf') {
                    $this->fileRepository->deleteFiles(self::ADDONS_DIR . '/metamod', [$name]);
                }
            }
        } catch (\Throwable) {
            // Non-critical cleanup
        }
    }

    private function listAddons(): array
    {
        try {
            return $this->fileRepository->getDirectory(self::ADDONS_DIR);
        } catch (\Throwable) {
            return [];
        }
    }

    private function detectFrameworkDirectory(string $framework, array $addons, ?string $gameinfoContent): ?string
    {
        $directory = $this->findDirectoryInList($addons, self::FRAMEWORK_DIR_ALIASES[$framework] ?? [self::FRAMEWORK_DIRS[$framework]]);
        if ($directory !== null) {
            return $directory;
        }

        if ($this->hasFrameworkMarker($framework)) {
            return self::FRAMEWORK_DIRS[$framework];
        }

        $entry = self::GAMEINFO_ENTRIES[$framework] ?? null;
        if ($entry !== null && $gameinfoContent !== null && GameInfoModifier::hasEntryInContent($gameinfoContent, $entry)) {
            return self::FRAMEWORK_DIRS[$framework];
        }

        return null;
    }

    private function hasFrameworkMarker(string $framework): bool
    {
        foreach (self::FRAMEWORK_MARKERS[$framework] ?? [] as $path => $markers) {
            $listing = $this->listDirectorySafe($path);
            if ($listing === null) {
                continue;
            }

            foreach ($markers as $marker) {
                if ($this->entryExistsInList($listing, $marker)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function listDirectorySafe(string $path): ?array
    {
        try {
            return $this->fileRepository->getDirectory($path);
        } catch (\Throwable) {
            return $this->listDirectoryByCaseInsensitiveBasename($path);
        }
    }

    private function listDirectoryByCaseInsensitiveBasename(string $path): ?array
    {
        $parent = dirname($path);
        $basename = basename($path);

        if ($parent === '.' || $parent === '/' || $basename === '') {
            return null;
        }

        try {
            $parentListing = $this->fileRepository->getDirectory($parent);
        } catch (\Throwable) {
            return null;
        }

        $actualName = $this->findDirectoryInList($parentListing, [$basename]);
        if ($actualName === null) {
            return null;
        }

        try {
            return $this->fileRepository->getDirectory(rtrim($parent, '/') . '/' . $actualName);
        } catch (\Throwable) {
            return null;
        }
    }

    private function findDirectoryInList(array $listing, array $names): ?string
    {
        $normalizedNames = array_map(
            static fn (string $name): string => strtolower($name),
            $names,
        );

        foreach ($listing as $item) {
            $itemName = (string) ($item['name'] ?? '');
            if (in_array(strtolower($itemName), $normalizedNames, true) && ($item['is_file'] ?? true) === false) {
                return $itemName;
            }
        }

        return null;
    }

    private function entryExistsInList(array $listing, string $name): bool
    {
        $normalizedName = strtolower($name);

        foreach ($listing as $item) {
            if (strtolower((string) ($item['name'] ?? '')) === $normalizedName) {
                return true;
            }
        }

        return false;
    }

    private function readGameInfoSafe(): ?string
    {
        try {
            return $this->fileRepository->getContent('/game/csgo/gameinfo.gi');
        } catch (\Throwable) {
            return null;
        }
    }

    private function backupFrameworkDirectory(string $dir): string
    {
        $backupDir = $dir . '.notur-backup-' . gmdate('Ymd-His');

        $this->fileRepository->renameFiles(self::ADDONS_DIR, [[
            'from' => $dir,
            'to' => $backupDir,
        ]]);

        return $backupDir;
    }

    private function isEligible(): bool
    {
        return (bool) ($this->serverEligibility['supported'] ?? false);
    }

    /**
     * @return array<string, array{version?: string, updated_at?: string}>
     */
    private function readVersionState(): array
    {
        try {
            $decoded = json_decode($this->fileRepository->getContent(self::VERSION_STATE_PATH), true);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function writeVersionState(array $state): void
    {
        try {
            $this->fileRepository->putContent(
                self::VERSION_STATE_PATH,
                json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
            );
        } catch (\Throwable $e) {
            Log::warning('CS2 ModFramework: Failed to write version state', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function recordInstalledVersion(string $framework, string $version): void
    {
        $state = $this->readVersionState();
        $state[$framework] = [
            'version' => $version,
            'updated_at' => gmdate('c'),
        ];

        $this->writeVersionState($state);
    }

    private function removeInstalledVersion(string $framework): void
    {
        $state = $this->readVersionState();
        unset($state[$framework]);

        $this->writeVersionState($state);
    }
}
