<?php

declare(strict_types=1);

namespace Notur\Cs2Modframework\Services;

use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Illuminate\Support\Facades\Log;

class FrameworkInstaller
{
    private const ADDONS_DIR = '/game/csgo/addons';
    private const CSGO_DIR = '/game/csgo';

    private const FRAMEWORK_DIRS = [
        'swiftly' => 'swiftlys2',
        'counterstrikesharp' => 'counterstrikesharp',
        'metamod' => 'metamod',
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
    ) {
    }

    public function getStatus(): array
    {
        $addons = $this->listAddons();
        $gameinfoContent = $this->readGameInfoSafe();

        $status = [];
        foreach (self::FRAMEWORK_DIRS as $framework => $dir) {
            $installed = $this->dirExistsInList($addons, $dir);
            $status[$framework] = [
                'installed' => $installed,
                'directory' => $installed ? "game/csgo/addons/{$dir}" : null,
            ];
        }

        $status['gameinfo_entries'] = [];
        foreach (self::GAMEINFO_ENTRIES as $framework => $entry) {
            $status['gameinfo_entries'][$framework] = $gameinfoContent !== null && str_contains($gameinfoContent, $entry);
        }

        return $status;
    }

    public function install(string $framework): array
    {
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

        return $this->doInstall($framework);
    }

    public function uninstall(string $framework): array
    {
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

        $dir = self::FRAMEWORK_DIRS[$framework];

        try {
            // Delete the framework directory
            $this->fileRepository->deleteFiles(self::ADDONS_DIR, [$dir]);

            // Remove gameinfo.gi entry if applicable
            $entry = self::GAMEINFO_ENTRIES[$framework] ?? null;
            if ($entry !== null) {
                $this->gameInfoModifier->removeEntry($entry);
            }

            // For CSS, also clean up the metamod VDF reference
            if ($framework === 'counterstrikesharp') {
                $this->cleanupCssMetamodFiles();
            }

            return [
                'success' => true,
                'framework' => $framework,
                'message' => "{$label} uninstalled successfully.",
            ];
        } catch (DaemonConnectionException $e) {
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

    private function doInstall(string $framework): array
    {
        $label = self::FRAMEWORK_LABELS[$framework];

        // Resolve download info
        $release = match ($framework) {
            'swiftly' => $this->releaseResolver->resolveSwiftly(),
            'counterstrikesharp' => $this->releaseResolver->resolveCounterStrikeSharp(),
            'metamod' => $this->releaseResolver->resolveMetamod(),
        };

        if ($release === null) {
            return [
                'success' => false,
                'framework' => $framework,
                'message' => "Could not resolve latest {$label} release. Please try again later.",
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

            return [
                'success' => true,
                'framework' => $framework,
                'version' => $version,
                'message' => "{$label} v{$version} installed successfully.",
            ];
        } catch (DaemonConnectionException $e) {
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
                if (str_contains(strtolower($name), 'counterstrikesharp') && str_ends_with($name, '.vdf')) {
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
        } catch (DaemonConnectionException) {
            return [];
        }
    }

    private function dirExistsInList(array $listing, string $name): bool
    {
        foreach ($listing as $item) {
            if (($item['name'] ?? '') === $name && ($item['is_file'] ?? true) === false) {
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
}
