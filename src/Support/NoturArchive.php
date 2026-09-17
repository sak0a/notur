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
        $originalChecksums = $checksumsExisted ? file_get_contents($checksumsFile) : null;
        file_put_contents(
            $checksumsFile,
            json_encode($checksums, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );

        // Use a unique, explicit tar suffix: Phar otherwise rewrites unknown
        // extensions and can collide with an earlier pack or unpack operation.
        $tarPath = dirname($outputPath) . '/.notur-pack-' . bin2hex(random_bytes(12)) . '.tar';
        try {
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

            unset($phar);
            if (!rename($tarPath . '.gz', $outputPath)) {
                throw new RuntimeException("Cannot write archive at: {$outputPath}");
            }
        } finally {
            foreach ([$tarPath, $tarPath . '.gz'] as $temporary) {
                if (file_exists($temporary)) {
                    unlink($temporary);
                }
            }
            if ($checksumsExisted) {
                file_put_contents($checksumsFile, $originalChecksums);
            } elseif (file_exists($checksumsFile)) {
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
     * @param bool   $requireChecksums Whether checksums.json must exist and be valid.
     * @return array<string, string> The checksums from the archive.
     * @throws RuntimeException If extraction or verification fails.
     */
    public static function unpack(
        string $archivePath,
        string $targetPath,
        bool $verifyChecksums = true,
        bool $requireChecksums = true,
        int $maxExtractedBytes = 536870912,
        int $maxEntries = 10000,
    ): array {
        if (!file_exists($archivePath)) {
            throw new RuntimeException("Archive not found: {$archivePath}");
        }

        if (!is_dir($targetPath)) {
            mkdir($targetPath, 0755, true);
        }

        // Bound gzip expansion before PharData decompresses the archive internally.
        $probe = fopen($archivePath, 'rb');
        if ($probe === false) {
            throw new RuntimeException("Cannot read archive: {$archivePath}");
        }
        $magic = fread($probe, 2);
        fclose($probe);
        if ($magic === "\x1f\x8b") {
            $stream = gzopen($archivePath, 'rb');
            if ($stream === false) {
                throw new RuntimeException('Cannot open compressed archive.');
            }
            try {
                $expanded = 0;
                // Include tar headers and padding in addition to file payloads.
                $limit = $maxExtractedBytes + ($maxEntries * 1024) + 10240;
                while (!gzeof($stream)) {
                    $chunk = gzread($stream, 65536);
                    if ($chunk === false || ($chunk === '' && !gzeof($stream))) {
                        throw new RuntimeException('Cannot read compressed archive.');
                    }
                    $expanded += strlen($chunk);
                    if ($expanded > $limit) {
                        throw new RuntimeException('Archive exceeds the decompression size limit.');
                    }
                }
            } finally {
                gzclose($stream);
            }
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
            $bytes = 0;
            $count = 0;
            $prefix = 'phar://' . $phar->getPath() . '/';
            foreach (new \RecursiveIteratorIterator($phar, \RecursiveIteratorIterator::SELF_FIRST) as $entry) {
                $relative = substr($entry->getPathname(), strlen($prefix));
                self::validateArchivePath($relative);
                if ($entry->isLink() || (!$entry->isFile() && !$entry->isDir())) {
                    throw new RuntimeException("Archive contains an unsupported entry: {$relative}");
                }
                $bytes += $entry->isFile() ? $entry->getSize() : 0;
                if (++$count > $maxEntries || $bytes > $maxExtractedBytes) {
                    throw new RuntimeException('Archive exceeds the unpacked size or file-count limit.');
                }
                // Never extract through a pre-existing link in the destination.
                $destination = rtrim($targetPath, '/');
                if (is_link($destination)) {
                    throw new RuntimeException('Extraction target must not be a symbolic link.');
                }
                foreach (explode('/', $relative) as $component) {
                    $destination .= '/' . $component;
                    if (is_link($destination)) {
                        throw new RuntimeException("Extraction path contains a symbolic link: {$relative}");
                    }
                }
            }
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

        if (!file_exists($checksumsFile)) {
            if ($requireChecksums) {
                throw new RuntimeException('Archive is missing required checksums.json');
            }
        } else {
            $raw = file_get_contents($checksumsFile);
            $decoded = json_decode($raw !== false ? $raw : '', true);

            if (!is_array($decoded) || $decoded === []) {
                if ($requireChecksums) {
                    throw new RuntimeException('checksums.json is missing or invalid');
                }
            } else {
                $checksums = $decoded;
            }
        }

        // Verify checksums if requested
        if ($verifyChecksums && $checksums !== []) {
            self::verifyChecksums($targetPath, $checksums);
        }

        return $checksums;
    }

    private static function validateArchivePath(string $path): void
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, "\\")
            || str_contains($path, "\0") || preg_match('#(^|/)\.\.?(/|$)#', $path)
            || preg_match('/^[a-z]:/i', $path)) {
            throw new RuntimeException("Invalid archive path: {$path}");
        }
    }

    /**
     * Verify that extracted files match their recorded checksums.
     *
     * @throws RuntimeException If any checksum does not match.
     */
    public static function verifyChecksums(string $basePath, array $checksums): void
    {
        $failed = [];
        $expectedPaths = [];

        foreach ($checksums as $relativePath => $expectedHash) {
            if (!is_string($relativePath) || $relativePath === '') {
                $failed[] = '<invalid path key>';
                continue;
            }

            if (!is_string($expectedHash) || !preg_match('/^[a-f0-9]{64}$/i', $expectedHash)) {
                $failed[] = "{$relativePath} (invalid checksum format)";
                continue;
            }

            self::validateArchivePath($relativePath);
            $expectedPaths[] = $relativePath;
            $fullPath = $basePath . '/' . $relativePath;

            if (!file_exists($fullPath)) {
                $failed[] = "{$relativePath} (missing)";
                continue;
            }

            $actualHash = hash_file(self::HASH_ALGO, $fullPath);
            if (!is_string($actualHash) || !hash_equals($expectedHash, $actualHash)) {
                $failed[] = "{$relativePath} (hash mismatch)";
            }
        }

        $actualFiles = self::collectExtractedFiles($basePath);
        sort($actualFiles);
        sort($expectedPaths);

        $extraFiles = array_values(array_diff($actualFiles, $expectedPaths));
        if ($extraFiles !== []) {
            $failed[] = 'unexpected files: ' . implode(', ', $extraFiles);
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

        // Exclude .notur archive files, their checksums, and related artifacts
        if (
            str_ends_with($relativePath, '.notur')
            || str_ends_with($relativePath, '.notur.sha256')
            || str_ends_with($relativePath, '.notur.tar.gz')
            || str_ends_with($relativePath, '.notur.sig')
            || (str_contains($relativePath, '.tar.gz') && !str_contains($relativePath, '/'))
        ) {
            return true;
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

    /**
     * @return array<int, string>
     */
    private static function collectExtractedFiles(string $basePath): array
    {
        if (!is_dir($basePath)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relativePath = self::relativePath($basePath, $file->getPathname());
            if ($relativePath === 'checksums.json') {
                continue;
            }

            $files[] = $relativePath;
        }

        return $files;
    }
}
