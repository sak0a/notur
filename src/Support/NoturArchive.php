<?php

declare(strict_types=1);

namespace Notur\Support;

use RuntimeException;

/**
 * Handles packing and unpacking of .notur archives.
 *
 * A .notur file is a tar.gz archive containing:
 * - The extension files (PHP source, frontend bundle, manifest)
 * - A checksums.json with SHA-256 hashes of all included files
 * - An optional signature file (Ed25519, for Phase 5)
 */
class NoturArchive
{
    /** Directories to exclude when packing an extension. */
    private const EXCLUDE_PATTERNS = [
        'node_modules',
        '.git',
        'vendor',
        '.idea',
        '.vscode',
    ];

    /** The checksum algorithm used for file integrity. */
    private const HASH_ALGO = 'sha256';

    /**
     * Pack an extension directory into a .notur archive.
     *
     * @param string $sourcePath  Path to the extension directory.
     * @param string $outputPath  Path for the output .notur file.
     * @return array{archive: string, checksums: array<string, string>} Archive path and checksums map.
     * @throws RuntimeException If packing fails.
     */
    public static function pack(string $sourcePath, string $outputPath): array
    {
        if (!is_dir($sourcePath)) {
            throw new RuntimeException("Source directory does not exist: {$sourcePath}");
        }

        $sourcePath = realpath($sourcePath);
        if ($sourcePath === false) {
            throw new RuntimeException("Cannot resolve source path");
        }

        // Collect files and compute checksums
        $files = self::collectFiles($sourcePath);
        $checksums = self::computeChecksums($sourcePath, $files);

        // Write checksums.json into the source temporarily
        $checksumsFile = $sourcePath . '/checksums.json';
        $checksumsExisted = file_exists($checksumsFile);
        file_put_contents(
            $checksumsFile,
            json_encode($checksums, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );

        try {
            // Remove .gz extension for PharData — it adds it on compress
            $tarPath = preg_replace('/\.gz$/', '', $outputPath);
            if ($tarPath === null) {
                $tarPath = $outputPath;
            }

            // Remove stale files if they exist
            foreach ([$tarPath, $tarPath . '.gz', $outputPath] as $stale) {
                if (file_exists($stale)) {
                    unlink($stale);
                }
            }

            $phar = new \PharData($tarPath);

            // Add checksums.json
            $phar->addFile($checksumsFile, 'checksums.json');

            // Add all extension files
            foreach ($files as $relativePath) {
                $fullPath = $sourcePath . '/' . $relativePath;
                $phar->addFile($fullPath, $relativePath);
            }

            // Compress to .tar.gz
            $phar->compress(\Phar::GZ);

            // Clean up the uncompressed tar
            if (file_exists($tarPath) && $tarPath !== $outputPath) {
                unlink($tarPath);
            }

            // Rename .tar.gz to .notur if needed
            $gzPath = $tarPath . '.gz';
            if ($gzPath !== $outputPath && file_exists($gzPath)) {
                rename($gzPath, $outputPath);
            }
        } finally {
            // Clean up temporary checksums.json only if we created it
            if (!$checksumsExisted && file_exists($checksumsFile)) {
                unlink($checksumsFile);
            }
        }

        if (!file_exists($outputPath)) {
            throw new RuntimeException("Failed to create archive at: {$outputPath}");
        }

        return [
            'archive' => $outputPath,
            'checksums' => $checksums,
        ];
    }

    /**
     * Unpack a .notur archive into a target directory.
     *
     * @param string $archivePath  Path to the .notur archive.
     * @param string $targetPath   Directory to extract into.
     * @param bool   $verifyChecksums  Whether to verify file checksums after extraction.
     * @return array<string, string> The checksums from the archive.
     * @throws RuntimeException If extraction or verification fails.
     */
    public static function unpack(string $archivePath, string $targetPath, bool $verifyChecksums = true): array
    {
        if (!file_exists($archivePath)) {
            throw new RuntimeException("Archive not found: {$archivePath}");
        }

        if (!is_dir($targetPath)) {
            mkdir($targetPath, 0755, true);
        }

        // PharData infers archive format from file extension. Since .notur is
        // actually a .tar.gz, we symlink to a recognisable name so PharData
        // can open it correctly.
        $aliasPath = null;
        if (!preg_match('/\.tar(\.gz|\.bz2)?$/i', $archivePath)) {
            $aliasPath = $archivePath . '.tar.gz';
            symlink($archivePath, $aliasPath);
        }

        try {
            $phar = new \PharData($aliasPath ?? $archivePath);
            $phar->extractTo($targetPath, null, true);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                "Failed to extract archive {$archivePath}: {$e->getMessage()}",
                0,
                $e,
            );
        } finally {
            if ($aliasPath !== null && file_exists($aliasPath)) {
                unlink($aliasPath);
            }
        }

        // Read checksums
        $checksumsFile = $targetPath . '/checksums.json';
        $checksums = [];

        if (file_exists($checksumsFile)) {
            $raw = file_get_contents($checksumsFile);
            $decoded = json_decode($raw !== false ? $raw : '', true);
            $checksums = is_array($decoded) ? $decoded : [];
        }

        // Verify checksums if requested
        if ($verifyChecksums && !empty($checksums)) {
            self::verifyChecksums($targetPath, $checksums);
        }

        return $checksums;
    }

    /**
     * Verify that extracted files match their recorded checksums.
     *
     * @throws RuntimeException If any checksum does not match.
     */
    public static function verifyChecksums(string $basePath, array $checksums): void
    {
        $failed = [];

        foreach ($checksums as $relativePath => $expectedHash) {
            $fullPath = $basePath . '/' . $relativePath;

            if (!file_exists($fullPath)) {
                $failed[] = "{$relativePath} (missing)";
                continue;
            }

            $actualHash = hash_file(self::HASH_ALGO, $fullPath);
            if (!hash_equals($expectedHash, $actualHash)) {
                $failed[] = "{$relativePath} (hash mismatch)";
            }
        }

        if (!empty($failed)) {
            throw new RuntimeException(
                'Checksum verification failed for: ' . implode(', ', $failed)
            );
        }
    }

    /**
     * Read checksums.json from an archive without extracting all files.
     *
     * @return array<string, string>|null Checksums map or null if not present.
     */
    public static function readChecksums(string $archivePath): ?array
    {
        $aliasPath = null;
        if (!preg_match('/\.tar(\.gz|\.bz2)?$/i', $archivePath)) {
            $aliasPath = $archivePath . '.tar.gz';
            symlink($archivePath, $aliasPath);
        }

        try {
            $phar = new \PharData($aliasPath ?? $archivePath);

            if (!isset($phar['checksums.json'])) {
                return null;
            }

            $content = $phar['checksums.json']->getContent();
            $decoded = json_decode($content, true);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable) {
            return null;
        } finally {
            if ($aliasPath !== null && file_exists($aliasPath)) {
                unlink($aliasPath);
            }
        }
    }

    /**
     * Collect all files in a directory, excluding patterns.
     *
     * @return array<int, string> Relative file paths.
     */
    private static function collectFiles(string $sourcePath): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourcePath, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }

            $relativePath = self::relativePath($sourcePath, $file->getPathname());

            if (self::isExcluded($relativePath)) {
                continue;
            }

            // Skip checksums.json — it will be generated fresh
            if ($relativePath === 'checksums.json') {
                continue;
            }

            $files[] = $relativePath;
        }

        sort($files);

        return $files;
    }

    /**
     * Compute SHA-256 checksums for a list of files.
     *
     * @return array<string, string> Map of relative path to hex-encoded hash.
     */
    private static function computeChecksums(string $basePath, array $relativePaths): array
    {
        $checksums = [];

        foreach ($relativePaths as $relativePath) {
            $fullPath = $basePath . '/' . $relativePath;
            $checksums[$relativePath] = hash_file(self::HASH_ALGO, $fullPath);
        }

        return $checksums;
    }

    /**
     * Check if a relative path matches any exclusion pattern.
     */
    private static function isExcluded(string $relativePath): bool
    {
        foreach (self::EXCLUDE_PATTERNS as $pattern) {
            if (
                str_starts_with($relativePath, $pattern . '/')
                || $relativePath === $pattern
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the relative path of a file within a base directory.
     */
    private static function relativePath(string $basePath, string $fullPath): string
    {
        return ltrim(str_replace($basePath, '', $fullPath), '/\\');
    }
}
