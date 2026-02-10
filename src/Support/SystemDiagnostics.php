<?php

declare(strict_types=1);

namespace Notur\Support;

use Composer\InstalledVersions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class SystemDiagnostics
{
    /**
     * Build a full diagnostics payload for the admin diagnostics page.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $noturVersion = $this->getNoturVersion();
        $latestNoturVersion = $this->getLatestNoturVersion();
        $packageManager = $this->detectPackageManager(base_path());

        return [
            'framework' => [
                'notur' => $noturVersion,
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
                'panel' => $this->getPanelVersion() ?? 'unknown',
            ],
            'runtime' => [
                'environment' => (string) app()->environment(),
                'debug' => (bool) config('app.debug', false),
                'timezone' => (string) (config('app.timezone') ?: date_default_timezone_get()),
                'sapi' => PHP_SAPI,
                'memory_limit' => (string) (ini_get('memory_limit') ?: 'unknown'),
                'max_execution_time' => (int) ini_get('max_execution_time'),
            ],
            'hardware' => [
                'os_family' => PHP_OS_FAMILY,
                'os' => php_uname('s'),
                'kernel' => php_uname('r'),
                'architecture' => php_uname('m'),
                'hostname' => gethostname() ?: 'unknown',
                'cpu_cores' => $this->detectCpuCores(),
                'disk_free_bytes' => $this->getDiskSpace(base_path(), free: true),
                'disk_total_bytes' => $this->getDiskSpace(base_path(), free: false),
                'disk_free' => $this->formatBytes($this->getDiskSpace(base_path(), free: true)),
                'disk_total' => $this->formatBytes($this->getDiskSpace(base_path(), free: false)),
            ],
            'package_manager' => $packageManager,
            'updates' => [
                'notur' => $this->buildNoturUpdateInfo($noturVersion, $latestNoturVersion),
            ],
        ];
    }

    public function getNoturVersion(): string
    {
        if (class_exists(InstalledVersions::class)) {
            $version = InstalledVersions::getPrettyVersion('notur/notur');
            if (is_string($version) && $version !== '') {
                return $version;
            }
        }

        $lockVersion = $this->getComposerPackageVersion('notur/notur');
        if ($lockVersion !== null) {
            return $lockVersion;
        }

        return '1.x';
    }

    public function getPanelVersion(): ?string
    {
        $configVersion = config('app.version');
        if (is_string($configVersion) && $configVersion !== '') {
            return $configVersion;
        }

        return $this->getComposerPackageVersion('pterodactyl/panel');
    }

    /**
     * @return array{
     *   manager: string,
     *   lockfile: string|null,
     *   command_available: bool,
     *   source: string
     * }
     */
    public function detectPackageManager(string $projectPath): array
    {
        $lockfileMap = [
            'bun.lockb' => 'bun',
            'bun.lock' => 'bun',
            'pnpm-lock.yaml' => 'pnpm',
            'yarn.lock' => 'yarn',
            'package-lock.json' => 'npm',
        ];

        foreach ($lockfileMap as $lockfile => $manager) {
            if (!file_exists($projectPath . DIRECTORY_SEPARATOR . $lockfile)) {
                continue;
            }

            return [
                'manager' => $manager,
                'lockfile' => $lockfile,
                'command_available' => $this->commandExists($manager),
                'source' => 'lockfile',
            ];
        }

        foreach (['bun', 'pnpm', 'yarn', 'npm'] as $manager) {
            if (!$this->commandExists($manager)) {
                continue;
            }

            return [
                'manager' => $manager,
                'lockfile' => null,
                'command_available' => true,
                'source' => 'binary',
            ];
        }

        return [
            'manager' => 'unknown',
            'lockfile' => null,
            'command_available' => false,
            'source' => 'none',
        ];
    }

    /**
     * @return array{
     *   current_version: string,
     *   latest_version: string|null,
     *   status: string,
     *   update_available: bool|null,
     *   checked_at: string,
     *   source: string
     * }
     */
    public function buildNoturUpdateInfo(string $currentVersion, ?string $latestVersion): array
    {
        $checkedAt = now()->toIso8601String();

        if (!$latestVersion) {
            return [
                'current_version' => $currentVersion,
                'latest_version' => null,
                'status' => 'unknown',
                'update_available' => null,
                'checked_at' => $checkedAt,
                'source' => 'packagist',
            ];
        }

        $currentComparable = $this->normalizeVersionForCompare($currentVersion);
        $latestComparable = $this->normalizeVersionForCompare($latestVersion);

        if ($currentComparable === null || $latestComparable === null) {
            return [
                'current_version' => $currentVersion,
                'latest_version' => $latestVersion,
                'status' => 'unknown',
                'update_available' => null,
                'checked_at' => $checkedAt,
                'source' => 'packagist',
            ];
        }

        $updateAvailable = version_compare($currentComparable, $latestComparable, '<');

        return [
            'current_version' => $currentVersion,
            'latest_version' => $latestVersion,
            'status' => $updateAvailable ? 'update_available' : 'up_to_date',
            'update_available' => $updateAvailable,
            'checked_at' => $checkedAt,
            'source' => 'packagist',
        ];
    }

    private function getLatestNoturVersion(): ?string
    {
        try {
            return Cache::remember(
                'notur:diagnostics:latest-version',
                now()->addMinutes(30),
                fn (): ?string => $this->fetchLatestNoturVersion(),
            );
        } catch (\Throwable) {
            return $this->fetchLatestNoturVersion();
        }
    }

    private function fetchLatestNoturVersion(): ?string
    {
        try {
            $response = Http::acceptJson()
                ->timeout(3)
                ->get('https://repo.packagist.org/p2/notur/notur.json');
        } catch (\Throwable) {
            return null;
        }

        if (!$response->ok()) {
            return null;
        }

        $packages = $response->json('packages.notur/notur');
        if (!is_array($packages)) {
            return null;
        }

        $stableVersions = [];

        foreach ($packages as $package) {
            if (!is_array($package)) {
                continue;
            }

            $version = $package['version'] ?? $package['version_normalized'] ?? null;
            if (!is_string($version) || $version === '') {
                continue;
            }

            $normalized = ltrim($version, 'v');

            if (str_contains($normalized, 'dev') || str_contains($normalized, '-')) {
                continue;
            }

            $stableVersions[] = $normalized;
        }

        if ($stableVersions === []) {
            return null;
        }

        usort($stableVersions, static fn (string $a, string $b): int => version_compare($a, $b));

        return end($stableVersions) ?: null;
    }

    private function getComposerPackageVersion(string $packageName): ?string
    {
        $lockFile = base_path('composer.lock');
        if (!file_exists($lockFile)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($lockFile), true);
        if (!is_array($decoded)) {
            return null;
        }

        $packages = array_merge($decoded['packages'] ?? [], $decoded['packages-dev'] ?? []);
        foreach ($packages as $package) {
            if (!is_array($package) || ($package['name'] ?? null) !== $packageName) {
                continue;
            }

            $version = $package['pretty_version'] ?? $package['version'] ?? null;
            if (is_string($version) && $version !== '') {
                return $version;
            }
        }

        return null;
    }

    private function normalizeVersionForCompare(string $version): ?string
    {
        $normalized = ltrim(trim($version), 'v');
        if ($normalized === '' || str_starts_with($normalized, 'dev-')) {
            return null;
        }

        if (!preg_match('/^\d+\.\d+\.\d+(?:\.\d+)?(?:-[0-9A-Za-z.-]+)?$/', $normalized)) {
            return null;
        }

        return $normalized;
    }

    private function commandExists(string $command): bool
    {
        if (!function_exists('exec')) {
            return false;
        }

        $output = [];
        $result = 1;
        exec(sprintf('command -v %s >/dev/null 2>&1', escapeshellarg($command)), $output, $result);

        return $result === 0;
    }

    private function detectCpuCores(): ?int
    {
        $envCores = getenv('NUMBER_OF_PROCESSORS');
        if (is_string($envCores) && ctype_digit($envCores) && (int) $envCores > 0) {
            return (int) $envCores;
        }

        if (is_readable('/proc/cpuinfo')) {
            $contents = (string) file_get_contents('/proc/cpuinfo');
            preg_match_all('/^processor\s*:/m', $contents, $matches);
            $count = count($matches[0] ?? []);
            if ($count > 0) {
                return $count;
            }
        }

        if (function_exists('shell_exec') && $this->commandExists('nproc')) {
            $raw = trim((string) shell_exec('nproc 2>/dev/null'));
            if (ctype_digit($raw) && (int) $raw > 0) {
                return (int) $raw;
            }
        }

        if (function_exists('shell_exec') && $this->commandExists('sysctl')) {
            $raw = trim((string) shell_exec('sysctl -n hw.ncpu 2>/dev/null'));
            if (ctype_digit($raw) && (int) $raw > 0) {
                return (int) $raw;
            }
        }

        return null;
    }

    private function getDiskSpace(string $path, bool $free): ?float
    {
        $value = $free ? @disk_free_space($path) : @disk_total_space($path);

        return is_numeric($value) ? (float) $value : null;
    }

    private function formatBytes(?float $bytes): ?string
    {
        if ($bytes === null || $bytes <= 0) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return sprintf('%.1f %s', $value, $units[$power]);
    }
}
