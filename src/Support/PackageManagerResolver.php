<?php

declare(strict_types=1);

namespace Notur\Support;

final class PackageManagerResolver
{
    private const LOCKFILE_MAP = [
        'bun.lockb' => 'bun',
        'bun.lock' => 'bun',
        'pnpm-lock.yaml' => 'pnpm',
        'yarn.lock' => 'yarn',
        'package-lock.json' => 'npm',
    ];

    /**
     * Detect package manager based on explicit env, lockfiles, and available binaries.
     */
    public function detect(?string $workingDir = null): ?string
    {
        $env = getenv('PKG_MANAGER');
        if (is_string($env) && in_array($env, ['bun', 'pnpm', 'yarn', 'npm'], true) && $this->commandExists($env)) {
            return $env;
        }

        if (is_string($workingDir) && $workingDir !== '') {
            foreach (self::LOCKFILE_MAP as $lockfile => $manager) {
                if (file_exists(rtrim($workingDir, '/') . '/' . $lockfile) && $this->commandExists($manager)) {
                    return $manager;
                }
            }
        }

        foreach (['bun', 'pnpm', 'yarn', 'npm'] as $manager) {
            if ($this->commandExists($manager)) {
                return $manager;
            }
        }

        return null;
    }

    public function installCommand(string $manager): string
    {
        return match ($manager) {
            'bun' => 'bun install',
            'pnpm' => 'pnpm install',
            'yarn' => 'yarn install',
            default => 'npm install',
        };
    }

    public function runScriptCommand(string $manager, string $script): string
    {
        $escapedScript = escapeshellarg($script);

        return match ($manager) {
            'bun' => "bun run {$escapedScript}",
            'pnpm' => "pnpm run {$escapedScript}",
            'yarn' => "yarn run {$escapedScript}",
            default => "npm run {$escapedScript}",
        };
    }

    /**
     * Build a package-manager specific "exec" command (bunx / pnpm dlx / yarn dlx / npx).
     *
     * @param array<int, string> $args
     */
    public function execCommand(string $manager, array $args): string
    {
        $prefix = match ($manager) {
            'bun' => ['bunx'],
            'pnpm' => ['pnpm', 'dlx'],
            'yarn' => ['yarn', 'dlx'],
            default => ['npx'],
        };

        $parts = array_map(static fn (string $arg): string => escapeshellarg($arg), array_merge($prefix, $args));

        return implode(' ', $parts);
    }

    private function commandExists(string $command): bool
    {
        if ($command === '') {
            return false;
        }

        if (str_contains($command, '/') || str_contains($command, '\\')) {
            return is_file($command) && is_executable($command);
        }

        $path = getenv('PATH');
        if (!is_string($path) || $path === '') {
            return false;
        }

        $directories = array_filter(explode(PATH_SEPARATOR, $path));
        $isWindows = PHP_OS_FAMILY === 'Windows';

        $extensions = [''];
        if ($isWindows) {
            $pathext = getenv('PATHEXT');
            $extensions = ['.exe', '.cmd', '.bat', '.com'];

            if (is_string($pathext) && $pathext !== '') {
                $parsed = array_map(
                    static fn (string $ext): string => strtolower(trim($ext)),
                    explode(';', $pathext),
                );
                $parsed = array_values(array_filter($parsed, static fn (string $ext): bool => $ext !== ''));
                if ($parsed !== []) {
                    $extensions = $parsed;
                }
            }

            if (pathinfo($command, PATHINFO_EXTENSION) !== '') {
                $extensions = [''];
            }
        }

        foreach ($directories as $directory) {
            $directory = rtrim($directory, DIRECTORY_SEPARATOR);
            if ($directory === '') {
                continue;
            }

            foreach ($extensions as $extension) {
                $candidate = $directory . DIRECTORY_SEPARATOR . $command;
                if ($extension !== '' && !str_ends_with(strtolower($candidate), $extension)) {
                    $candidate .= $extension;
                }

                if (is_file($candidate) && is_executable($candidate)) {
                    return true;
                }
            }
        }

        return false;
    }
}

