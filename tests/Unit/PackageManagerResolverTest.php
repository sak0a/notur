<?php

declare(strict_types=1);

namespace Notur\Tests\Unit;

use Notur\Support\PackageManagerResolver;
use PHPUnit\Framework\TestCase;

class PackageManagerResolverTest extends TestCase
{
    private string $tempDir;
    private string $originalPath;
    private string|false $originalPkgManager;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/notur-pm-resolver-' . uniqid('', true);
        mkdir($this->tempDir, 0755, true);

        $this->originalPath = (string) getenv('PATH');
        $this->originalPkgManager = getenv('PKG_MANAGER');
        putenv('PKG_MANAGER');
    }

    protected function tearDown(): void
    {
        putenv("PATH={$this->originalPath}");
        if ($this->originalPkgManager !== false) {
            putenv('PKG_MANAGER=' . $this->originalPkgManager);
        } else {
            putenv('PKG_MANAGER');
        }

        $this->deleteDir($this->tempDir);
    }

    public function test_detect_prefers_env_variable_when_binary_exists(): void
    {
        $this->createBinary('pnpm');
        putenv("PATH={$this->tempDir}");
        putenv('PKG_MANAGER=pnpm');

        $resolver = new PackageManagerResolver();

        $this->assertSame('pnpm', $resolver->detect($this->tempDir));
    }

    public function test_detect_uses_lockfile_when_matching_binary_exists(): void
    {
        $workDir = $this->tempDir . '/project';
        mkdir($workDir, 0755, true);
        file_put_contents($workDir . '/yarn.lock', '');

        $this->createBinary('yarn');
        putenv("PATH={$this->tempDir}");

        $resolver = new PackageManagerResolver();

        $this->assertSame('yarn', $resolver->detect($workDir));
    }

    public function test_detect_returns_null_when_no_supported_package_manager_exists(): void
    {
        putenv("PATH={$this->tempDir}");

        $resolver = new PackageManagerResolver();

        $this->assertNull($resolver->detect($this->tempDir));
    }

    private function createBinary(string $name): void
    {
        $path = $this->tempDir . '/' . $name;
        file_put_contents($path, "#!/usr/bin/env sh\nexit 0\n");
        chmod($path, 0755);
    }

    private function deleteDir(string $dir): void
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
