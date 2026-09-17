<?php

declare(strict_types=1);

namespace Notur\Support;

use RuntimeException;

/** File snapshots only. Database recovery requires an independent database backup. */
final class LifecycleBackup
{
    public function extension(string $id): string
    {
        return $this->create([
            'extension' => ExtensionPath::base($id),
            'public' => ExtensionPath::public($id),
            'extensions.json' => ExtensionPath::manifest(),
        ], 'extension');
    }

    public function create(array $paths, string $operation): string
    {
        $root = config('notur.backups_path', storage_path('notur/backups'));
        $destination = $root . '/' . $operation . '-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(8));
        $this->directory($destination);
        foreach ($paths as $name => $source) {
            if (file_exists($source) || is_link($source)) {
                $this->copy($source, $destination . '/' . $name);
            }
        }
        if (file_put_contents($destination . '/README.txt', "File snapshot only. No database data is included. Restore with the matching database backup, then reinstall Composer dependencies and clear caches.\n") === false) {
            throw new RuntimeException('Could not write backup metadata.');
        }
        return $destination;
    }

    private function directory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
            throw new RuntimeException("Could not create backup directory: {$path}");
        }
    }

    private function copy(string $source, string $target): void
    {
        // Never follow symlinks into unrelated files or recursive trees.
        if (is_link($source)) {
            throw new RuntimeException("Cannot snapshot symbolic link: {$source}");
        }
        if (is_dir($source)) {
            $this->directory($target);
            foreach (new \FilesystemIterator($source, \FilesystemIterator::SKIP_DOTS) as $item) {
                $this->copy($item->getPathname(), $target . '/' . $item->getFilename());
            }
        } elseif (!is_file($source) || !copy($source, $target)) {
            throw new RuntimeException("Could not back up: {$source}");
        } elseif (!chmod($target, 0600)) {
            throw new RuntimeException("Could not protect backup: {$target}");
        }
    }
}
