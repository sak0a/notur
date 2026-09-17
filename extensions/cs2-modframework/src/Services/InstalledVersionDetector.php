<?php

declare(strict_types=1);

namespace Notur\Cs2Modframework\Services;

use Pterodactyl\Repositories\Wings\DaemonFileRepository;

/** Reads packaged metadata, never executes server binaries or console commands. */
class InstalledVersionDetector
{
    public function __construct(private readonly DaemonFileRepository $files) {}

    public function detect(string $framework, string $directory): ?string
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/D', $directory)) {
            return null;
        }
        $base = '/game/csgo/addons/' . $directory;
        try {
            if ($framework === 'metamod') {
                $binary = $this->files->getContent($base . '/bin/linuxsteamrt64/metamod.2.cs2.so', 8 * 1024 * 1024);
                if (strlen($binary) > 8 * 1024 * 1024 || !str_starts_with($binary, "\x7fELF")) {
                    return null;
                }
                preg_match_all('/\x00(\d+\.\d+\.\d+)-dev\+(\d+)\x00/', $binary, $matches, PREG_SET_ORDER);
                $versions = array_unique(array_map(fn ($m) => $m[1] . '-git' . $m[2], $matches));
                return count($versions) === 1 ? reset($versions) : null;
            }
            [$path, $package] = match ($framework) {
                'counterstrikesharp' => ['/api/CounterStrikeSharp.API.deps.json', 'CounterStrikeSharp.API'],
                'swiftly' => ['/bin/managed/SwiftlyS2.CS2.deps.json', 'SwiftlyS2.CS2'],
                default => [null, null],
            };
            if ($path === null) return null;
            $raw = $this->files->getContent($base . $path, 256 * 1024);
            if (strlen($raw) > 256 * 1024) return null;
            $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            $versions = [];
            foreach (array_keys($data['libraries'] ?? []) as $library) {
                if (!str_starts_with($library, $package . '/')) continue;
                $version = substr($library, strlen($package) + 1);
                if (preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/D', $version)) {
                    $versions[] = $version;
                }
            }
            $versions = array_unique($versions);
            return count($versions) === 1 ? reset($versions) : null;
        } catch (\Throwable) {
            // Missing, unreadable, oversized or unsupported metadata is unknown.
            return null;
        }
    }
}
