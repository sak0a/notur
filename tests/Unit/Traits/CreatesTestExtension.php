<?php

declare(strict_types=1);

namespace Notur\Tests\Unit\Traits;

trait CreatesTestExtension
{
    protected string $tempDir;

    protected function createTempDir(): string
    {
        $this->tempDir = sys_get_temp_dir() . '/notur-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
        return $this->tempDir;
    }

    protected function createTempExtension(array $manifestOverrides = []): string
    {
        $path = $this->createTempDir();

        $manifest = array_merge([
            'id' => 'acme/test',
            'name' => 'Test Extension',
            'version' => '1.0.0',
            'entrypoint' => 'Acme\\Test\\TestExtension',
        ], $manifestOverrides);

        file_put_contents(
            $path . '/extension.yaml',
            $this->toYaml($manifest)
        );

        return $path;
    }

    protected function cleanupTempDir(): void
    {
        if (isset($this->tempDir) && is_dir($this->tempDir)) {
            $this->deleteDir($this->tempDir);
        }
    }

    protected function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }

    protected function toYaml(array $data): string
    {
        $lines = [];

        foreach ($data as $key => $value) {
            $lines = array_merge($lines, $this->yamlLine($key, $value, 0));
        }

        return implode("\n", $lines) . "\n";
    }

    private function yamlLine(string $key, mixed $value, int $indent): array
    {
        $prefix = str_repeat('  ', $indent);

        if (is_array($value)) {
            $lines = [$prefix . $key . ':'];
            if (array_is_list($value)) {
                foreach ($value as $item) {
                    if (is_array($item)) {
                        $lines[] = $prefix . '  -';
                        foreach ($item as $itemKey => $itemValue) {
                            $lines = array_merge($lines, $this->yamlLine((string) $itemKey, $itemValue, $indent + 2));
                        }
                    } else {
                        $lines[] = $prefix . '  - ' . $this->yamlScalar($item);
                    }
                }
            } else {
                foreach ($value as $childKey => $childValue) {
                    $lines = array_merge($lines, $this->yamlLine((string) $childKey, $childValue, $indent + 1));
                }
            }
            return $lines;
        }

        return [$prefix . $key . ': ' . $this->yamlScalar($value)];
    }

    private function yamlScalar(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $value);
        return '"' . $escaped . '"';
    }
}
