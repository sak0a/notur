<?php

declare(strict_types=1);

namespace Notur\Console\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Notur\Support\ExtensionPath;

class DevPullCommand extends Command
{
    private const DEFAULT_REPO = 'sak0a/notur';
    private const GITHUB_API_BASE = 'https://api.github.com';

    protected $signature = 'notur:dev:pull
        {branch=master : The branch to pull from}
        {commit? : A specific commit SHA to pull (defaults to latest on branch)}
        {--no-rebuild : Skip frontend rebuild after pulling}
        {--dry-run : Show what would be done without making changes}';

    protected $description = 'Pull the latest Notur framework code from GitHub for development';

    public function handle(): int
    {
        $branch = $this->argument('branch');
        $commit = $this->argument('commit');
        $ref = $commit ?? $branch;
        $isDryRun = (bool) $this->option('dry-run');
        $noRebuild = (bool) $this->option('no-rebuild');

        $repo = config('notur.repository', self::DEFAULT_REPO);
        $noturRoot = base_path('vendor/notur/notur');

        $client = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'Notur-DevPull/1.0',
            ],
        ]);

        // Step 1: Fetch commit info
        $this->info("Fetching commit info for '{$ref}' from {$repo}...");

        try {
            $commitInfo = $this->fetchCommitInfo($client, $repo, $ref);
        } catch (\Throwable $e) {
            $this->error("Failed to fetch commit info: {$e->getMessage()}");
            return 1;
        }

        $sha = $commitInfo['sha'];
        $shortSha = substr($sha, 0, 8);
        $message = $commitInfo['commit']['message'] ?? 'No message';
        $author = $commitInfo['commit']['author']['name'] ?? 'Unknown';
        $date = $commitInfo['commit']['author']['date'] ?? 'Unknown';
        $firstLine = strtok($message, "\n");

        $this->newLine();
        $this->info("  Branch:  {$branch}");
        $this->info("  Commit:  {$shortSha}");
        $this->info("  Author:  {$author}");
        $this->info("  Date:    {$date}");
        $this->info("  Message: {$firstLine}");
        $this->newLine();

        // Step 2: Warn and confirm
        $this->warn('This will replace files in vendor/notur/notur/ which is normally managed by Composer.');
        $this->warn('Running "composer update notur/notur" later will overwrite these changes.');

        if ($isDryRun) {
            $this->newLine();
            $this->info("[DRY RUN] Would download and extract commit {$shortSha} to {$noturRoot}");
            if (!$noRebuild) {
                $this->info('[DRY RUN] Would rebuild frontend bridge');
                $this->info('[DRY RUN] Would copy bridge.js and tailwind.css to public/notur/');
            }
            return 0;
        }

        if (!$this->confirm("Pull commit {$shortSha} into vendor/notur/notur?")) {
            $this->info('Aborted.');
            return 0;
        }

        // Step 3: Download the zip archive
        $tmpZip = sys_get_temp_dir() . '/notur-dev-pull-' . uniqid() . '.zip';
        $this->info("Downloading commit {$shortSha}...");

        try {
            $this->downloadArchive($client, $repo, $sha, $tmpZip);
        } catch (\Throwable $e) {
            $this->error("Download failed: {$e->getMessage()}");
            @unlink($tmpZip);
            return 1;
        }

        $this->info('Download complete.');

        // Step 4: Extract to temp directory
        $tmpDir = sys_get_temp_dir() . '/notur-dev-pull-extract-' . uniqid();

        try {
            $innerDir = $this->extractArchive($tmpZip, $tmpDir);
        } catch (\Throwable $e) {
            $this->error("Extraction failed: {$e->getMessage()}");
            @unlink($tmpZip);
            return 1;
        }

        @unlink($tmpZip);

        // Step 5: Replace vendor files
        $this->info('Replacing vendor/notur/notur/...');

        try {
            $this->replaceVendorFiles($noturRoot, $innerDir);
        } catch (\Throwable $e) {
            $this->error("Failed to replace files: {$e->getMessage()}");
            $this->deleteDirectory($tmpDir);
            return 1;
        }

        $this->deleteDirectory($tmpDir);
        $this->info('Files replaced successfully.');

        // Step 6: Rebuild frontend
        if (!$noRebuild) {
            $this->newLine();
            $this->info('Rebuilding frontend...');

            $packageManager = $this->resolvePackageManager($noturRoot);

            $result = $this->runProcess("{$packageManager} install", $noturRoot);
            if ($result !== 0) {
                $this->warn('Dependency install failed. You may need to run it manually.');
            }

            $result = $this->runProcess("{$packageManager} run build:bridge", $noturRoot);
            if ($result !== 0) {
                $this->warn('Bridge build failed. You may need to run it manually.');
            }

            $result = $this->runProcess("{$packageManager} run build:tailwind", $noturRoot);
            if ($result !== 0) {
                $this->warn('Tailwind build failed. You may need to run it manually.');
            }

            // Step 7: Copy built assets to public
            $bridgeSource = $noturRoot . '/bridge/dist/bridge.js';
            $bridgeTarget = ExtensionPath::bridgeJs();

            if (file_exists($bridgeSource) && !is_link($bridgeTarget)) {
                $bridgeDir = dirname($bridgeTarget);
                if (!is_dir($bridgeDir)) {
                    mkdir($bridgeDir, 0755, true);
                }
                copy($bridgeSource, $bridgeTarget);
                $this->info('Copied bridge.js to public/notur/');
            } elseif (is_link($bridgeTarget)) {
                $this->info('bridge.js is symlinked (dev mode) — skipping copy.');
            }

            $tailwindSource = $noturRoot . '/bridge/dist/tailwind.css';
            $tailwindTarget = ExtensionPath::tailwindCss();

            if (file_exists($tailwindSource) && !is_link($tailwindTarget)) {
                $tailwindDir = dirname($tailwindTarget);
                if (!is_dir($tailwindDir)) {
                    mkdir($tailwindDir, 0755, true);
                }
                copy($tailwindSource, $tailwindTarget);
                $this->info('Copied tailwind.css to public/notur/');
            } elseif (is_link($tailwindTarget)) {
                $this->info('tailwind.css is symlinked (dev mode) — skipping copy.');
            }
        }

        // Step 8: Clear caches
        $this->call('cache:clear');
        $this->call('view:clear');

        $this->newLine();
        $this->info("Notur framework updated to commit {$shortSha} ({$firstLine})");
        $this->info("Run 'composer update notur/notur' to revert to the published release.");

        return 0;
    }

    private function fetchCommitInfo(Client $client, string $repo, string $ref): array
    {
        $url = self::GITHUB_API_BASE . "/repos/{$repo}/commits/{$ref}";

        try {
            $response = $client->get($url);
        } catch (GuzzleException $e) {
            throw new \RuntimeException(
                "GitHub API request failed: {$e->getMessage()}",
                (int) $e->getCode(),
                $e,
            );
        }

        $body = json_decode($response->getBody()->getContents(), true);
        if (!is_array($body) || !isset($body['sha'])) {
            throw new \RuntimeException('Unexpected response from GitHub API');
        }

        return $body;
    }

    private function downloadArchive(Client $client, string $repo, string $sha, string $targetPath): void
    {
        $url = self::GITHUB_API_BASE . "/repos/{$repo}/zipball/{$sha}";

        try {
            $client->get($url, [
                'sink' => $targetPath,
                'timeout' => 120,
                'connect_timeout' => 10,
            ]);
        } catch (GuzzleException $e) {
            throw new \RuntimeException(
                "Failed to download archive: {$e->getMessage()}",
                (int) $e->getCode(),
                $e,
            );
        }
    }

    private function extractArchive(string $zipPath, string $targetDir): string
    {
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $zip = new \ZipArchive();
        $result = $zip->open($zipPath);

        if ($result !== true) {
            throw new \RuntimeException("Failed to open zip archive (error code: {$result})");
        }

        // Manually extract each entry with path validation to prevent path traversal attacks
        $targetDir = realpath($targetDir);
        if ($targetDir === false) {
            $zip->close();
            throw new \RuntimeException('Failed to resolve target directory path');
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if ($entry === false) {
                continue;
            }

            // Validate entry path: no absolute paths, no .. segments
            if ($this->isUnsafePath($entry)) {
                $zip->close();
                throw new \RuntimeException("Archive contains unsafe path: {$entry}");
            }

            // Validate that the normalized relative path doesn't escape
            try {
                $normalizedEntry = $this->normalizePath($entry);
            } catch (\RuntimeException $e) {
                $zip->close();
                throw new \RuntimeException("Archive contains path that attempts to escape: {$entry}");
            }

            $destination = $targetDir . DIRECTORY_SEPARATOR . $normalizedEntry;

            // Double-check the resolved path is within target directory
            $realDestination = realpath(dirname($destination));
            if ($realDestination === false) {
                // Directory doesn't exist yet, which is OK - verify the path would be safe
                $parentPath = dirname($destination);
                if (!str_starts_with($parentPath, $targetDir)) {
                    $zip->close();
                    throw new \RuntimeException("Archive attempts to extract outside target directory: {$entry}");
                }
            } elseif (!str_starts_with($realDestination, $targetDir)) {
                $zip->close();
                throw new \RuntimeException("Archive attempts to extract outside target directory: {$entry}");
            }

            // Extract the entry
            if (str_ends_with($entry, '/')) {
                // Directory entry
                if (!is_dir($destination)) {
                    mkdir($destination, 0750, true);
                }
            } else {
                // File entry
                $dir = dirname($destination);
                if (!is_dir($dir)) {
                    mkdir($dir, 0750, true);
                }

                $contents = $zip->getFromIndex($i);
                if ($contents === false) {
                    $zip->close();
                    throw new \RuntimeException("Failed to read entry from archive: {$entry}");
                }

                file_put_contents($destination, $contents);
            }
        }

        $zip->close();

        // GitHub zipball contains a single top-level directory: {owner}-{repo}-{shortsha}/
        $entries = array_values(array_filter(
            scandir($targetDir),
            fn ($e) => $e !== '.' && $e !== '..' && is_dir($targetDir . '/' . $e),
        ));

        if (count($entries) !== 1) {
            throw new \RuntimeException('Unexpected archive structure: expected exactly one top-level directory');
        }

        return $targetDir . '/' . $entries[0];
    }

    /**
     * Check if a path contains unsafe components that could lead to path traversal.
     */
    private function isUnsafePath(string $path): bool
    {
        // Check for absolute paths (Unix or Windows style)
        if (str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:/', $path)) {
            return true;
        }

        // Check for .. segments or null bytes
        $parts = explode('/', str_replace('\\', '/', $path));
        foreach ($parts as $part) {
            if ($part === '..' || str_contains($part, "\0")) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize a path by resolving . and .. segments without requiring the path to exist.
     */
    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $parts = explode('/', $path);
        $normalized = [];

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                // If we can't pop (empty array), the path tries to escape - reject it
                if (empty($normalized)) {
                    throw new \RuntimeException('Invalid path: attempts to traverse above root');
                }
                array_pop($normalized);
            } else {
                $normalized[] = $part;
            }
        }

        $result = implode(DIRECTORY_SEPARATOR, $normalized);
        if (str_starts_with($path, '/')) {
            $result = DIRECTORY_SEPARATOR . $result;
        }

        return $result;
    }

    private function replaceVendorFiles(string $noturRoot, string $sourcePath): void
    {
        // Preserve vendor/ and node_modules/ if they exist (heavy install artifacts)
        $preserveDirs = ['vendor', 'node_modules'];
        $tmpPreserve = sys_get_temp_dir() . '/notur-preserve-' . uniqid();

        foreach ($preserveDirs as $dir) {
            $dirPath = $noturRoot . '/' . $dir;
            if (is_dir($dirPath)) {
                if (!is_dir($tmpPreserve)) {
                    mkdir($tmpPreserve, 0755, true);
                }
                rename($dirPath, $tmpPreserve . '/' . $dir);
            }
        }

        // Delete old vendor/notur/notur/ contents
        if (is_dir($noturRoot)) {
            $this->deleteDirectory($noturRoot);
        }

        // Copy new files
        $this->copyDirectory($sourcePath, $noturRoot);

        // Restore preserved directories
        foreach ($preserveDirs as $dir) {
            $tmpSource = $tmpPreserve . '/' . $dir;
            if (is_dir($tmpSource)) {
                $destPath = $noturRoot . '/' . $dir;
                if (is_dir($destPath)) {
                    $this->deleteDirectory($destPath);
                }
                rename($tmpSource, $destPath);
            }
        }

        // Clean up preserve temp
        if (is_dir($tmpPreserve)) {
            $this->deleteDirectory($tmpPreserve);
        }
    }

    private function resolvePackageManager(string $cwd): string
    {
        if (file_exists($cwd . '/bun.lockb') || file_exists($cwd . '/bun.lock')) {
            return 'bun';
        }
        if (file_exists($cwd . '/pnpm-lock.yaml')) {
            return 'pnpm';
        }
        if (file_exists($cwd . '/yarn.lock')) {
            return 'yarn';
        }
        if (file_exists($cwd . '/package-lock.json')) {
            return 'npm';
        }

        return 'npm';
    }

    private function runProcess(string $command, string $cwd): int
    {
        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $cwd,
        );

        if (!is_resource($process)) {
            return 1;
        }

        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($output) {
            $this->line($output);
        }
        if ($errors && $exitCode !== 0) {
            $this->error($errors);
        }

        return $exitCode;
    }

    private function copyDirectory(string $source, string $dest): void
    {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $target = $dest . '/' . $iterator->getSubPathname();
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                copy($item->getPathname(), $target);
            }
        }
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
