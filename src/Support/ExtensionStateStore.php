<?php

declare(strict_types=1);

namespace Notur\Support;

use JsonException;
use RuntimeException;
use stdClass;

/**
 * Serializes changes to the boot manifest. The JSON file is authoritative;
 * callers project its committed contents into the database while holding the lock.
 */
class ExtensionStateStore
{
    public function __construct(private readonly string $path)
    {
    }

    /** @return array<string, mixed>|null */
    public function reconcile(callable $project): ?array
    {
        // A fresh installation has no state to project. Avoid creating its
        // parent directory or lock file during an ordinary read-only boot.
        if (!file_exists($this->path) && !file_exists($this->path . '.lock')) {
            return null;
        }

        return $this->locked(function () use ($project): ?array {
            $state = $this->read();
            if ($state !== null) {
                $project($state);
            }

            return $state;
        });
    }

    /** @return array<string, mixed> */
    public function update(callable $change, callable $project): array
    {
        return $this->locked(function () use ($change, $project): array {
            $state = $this->read() ?? ['extensions' => []];
            $next = $change($state);
            $this->validateState($next);

            if ($next !== $state || !is_file($this->path)) {
                $this->replace($next);
            }

            // A failure here leaves a committed JSON state. The next mutation,
            // explicit reconciliation, or boot retries the full projection.
            $project($next);

            return $next;
        });
    }

    /** @return array<string, mixed>|null */
    private function read(): ?array
    {
        if (!is_file($this->path)) {
            if (file_exists($this->path)) {
                throw new RuntimeException("Extension state path is not a file: {$this->path}");
            }
            return null;
        }

        $raw = file_get_contents($this->path);
        if ($raw === false) {
            throw new RuntimeException("Could not read extension state: {$this->path}");
        }

        try {
            $object = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
            $state = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("Invalid extension state JSON: {$this->path}", 0, $e);
        }

        if (!$object instanceof stdClass || !isset($object->extensions)
            || (!$object->extensions instanceof stdClass && $object->extensions !== [])) {
            throw new RuntimeException("Invalid extension state structure: {$this->path}");
        }

        $this->validateState($state);
        return $state;
    }

    private function validateState(mixed $state): void
    {
        if (!is_array($state) || !isset($state['extensions']) || !is_array($state['extensions'])) {
            throw new RuntimeException("Invalid extension state structure: {$this->path}");
        }

        foreach ($state['extensions'] as $id => $entry) {
            if (!is_string($id) || preg_match('#^[a-z0-9-]+/[a-z0-9-]+$#', $id) !== 1 || !is_array($entry)
                || !isset($entry['version']) || !is_string($entry['version']) || $entry['version'] === ''
                || !isset($entry['enabled']) || !is_bool($entry['enabled'])) {
                throw new RuntimeException("Invalid extension entry in state: {$this->path}");
            }
        }
    }

    private function replace(array $state): void
    {
        try {
            $encoded = $state;
            if ($encoded['extensions'] === []) {
                $encoded['extensions'] = new stdClass();
            }
            $json = json_encode($encoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        } catch (JsonException $e) {
            throw new RuntimeException('Could not encode extension state.', 0, $e);
        }

        $temp = tempnam(dirname($this->path), '.extensions-');
        if ($temp === false) {
            throw new RuntimeException("Could not create temporary extension state beside {$this->path}");
        }

        try {
            $handle = fopen($temp, 'wb');
            if ($handle === false) {
                throw new RuntimeException("Could not open temporary extension state: {$temp}");
            }

            try {
                $remaining = $json;
                while ($remaining !== '') {
                    $written = fwrite($handle, $remaining);
                    if ($written === false || $written === 0) {
                        throw new RuntimeException("Could not write temporary extension state: {$temp}");
                    }
                    $remaining = substr($remaining, $written);
                }
                if (!fflush($handle) || (function_exists('fsync') && !fsync($handle))) {
                    throw new RuntimeException("Could not flush temporary extension state: {$temp}");
                }
            } finally {
                fclose($handle);
            }

            $mode = is_file($this->path) ? (fileperms($this->path) & 0777) : 0644;
            if (!chmod($temp, $mode) || !rename($temp, $this->path)) {
                throw new RuntimeException("Could not replace extension state: {$this->path}");
            }
        } finally {
            if (is_file($temp)) {
                unlink($temp);
            }
        }
    }

    private function locked(callable $operation): mixed
    {
        $dir = dirname($this->path);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("Could not create extension state directory: {$dir}");
        }

        // The lock file is stable across atomic replacements of the JSON file.
        $lock = fopen($this->path . '.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException("Could not open extension state lock: {$this->path}.lock");
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException("Could not lock extension state: {$this->path}");
            }
            return $operation();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
