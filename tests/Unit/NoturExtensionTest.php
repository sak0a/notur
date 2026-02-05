<?php

declare(strict_types=1);

namespace Notur\Tests\Unit;

use Notur\Support\NoturExtension;
use PHPUnit\Framework\TestCase;

class NoturExtensionTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/notur-test-' . uniqid();
        mkdir($this->tempDir);
        mkdir($this->tempDir . '/src');
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function test_reads_id_from_manifest(): void
    {
        $this->writeManifest([
            'id' => 'acme/test-ext',
            'name' => 'Test Extension',
            'version' => '1.2.3',
        ]);

        $extension = $this->createTestExtension();

        $this->assertEquals('acme/test-ext', $extension->getId());
    }

    public function test_reads_name_from_manifest(): void
    {
        $this->writeManifest([
            'id' => 'acme/test-ext',
            'name' => 'My Test Extension',
            'version' => '1.0.0',
        ]);

        $extension = $this->createTestExtension();

        $this->assertEquals('My Test Extension', $extension->getName());
    }

    public function test_reads_version_from_manifest(): void
    {
        $this->writeManifest([
            'id' => 'acme/test-ext',
            'name' => 'Test Extension',
            'version' => '2.5.0',
        ]);

        $extension = $this->createTestExtension();

        $this->assertEquals('2.5.0', $extension->getVersion());
    }

    public function test_getBasePath_returns_extension_root(): void
    {
        $this->writeManifest([
            'id' => 'acme/test-ext',
            'name' => 'Test Extension',
            'version' => '1.0.0',
        ]);

        $extension = $this->createTestExtension();

        $this->assertEquals($this->tempDir, $extension->getBasePath());
    }

    public function test_register_is_noop_by_default(): void
    {
        $this->writeManifest([
            'id' => 'acme/test-ext',
            'name' => 'Test Extension',
            'version' => '1.0.0',
        ]);

        $extension = $this->createTestExtension();

        // Should not throw
        $extension->register();
        $this->assertTrue(true);
    }

    public function test_boot_is_noop_by_default(): void
    {
        $this->writeManifest([
            'id' => 'acme/test-ext',
            'name' => 'Test Extension',
            'version' => '1.0.0',
        ]);

        $extension = $this->createTestExtension();

        // Should not throw
        $extension->boot();
        $this->assertTrue(true);
    }

    public function test_manifest_is_cached(): void
    {
        $this->writeManifest([
            'id' => 'acme/test-ext',
            'name' => 'Test Extension',
            'version' => '1.0.0',
        ]);

        $extension = $this->createTestExtension();

        // Call getId multiple times
        $id1 = $extension->getId();
        $id2 = $extension->getId();

        $this->assertEquals($id1, $id2);
        $this->assertEquals('acme/test-ext', $id1);
    }

    public function test_setBasePath_allows_custom_path(): void
    {
        // Create a second temp directory with different manifest
        $otherDir = sys_get_temp_dir() . '/notur-test-other-' . uniqid();
        mkdir($otherDir);

        $this->writeManifestTo($otherDir, [
            'id' => 'other/extension',
            'name' => 'Other Extension',
            'version' => '3.0.0',
        ]);

        $this->writeManifest([
            'id' => 'acme/test-ext',
            'name' => 'Test Extension',
            'version' => '1.0.0',
        ]);

        $extension = $this->createTestExtensionWithCustomPath($otherDir);

        $this->assertEquals('other/extension', $extension->getId());
        $this->assertEquals('Other Extension', $extension->getName());
        $this->assertEquals('3.0.0', $extension->getVersion());

        // Clean up
        $this->removeDirectory($otherDir);
    }

    private function writeManifest(array $data): void
    {
        $this->writeManifestTo($this->tempDir, $data);
    }

    private function writeManifestTo(string $dir, array $data): void
    {
        $yaml = '';
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $yaml .= "{$key}: \"{$value}\"\n";
            } else {
                $yaml .= "{$key}: {$value}\n";
            }
        }
        file_put_contents($dir . '/extension.yaml', $yaml);
    }

    private function createTestExtension(): TestableNoturExtension
    {
        $extension = new TestableNoturExtension();
        $extension->setTestBasePath($this->tempDir);
        return $extension;
    }

    private function createTestExtensionWithCustomPath(string $path): TestableNoturExtension
    {
        $extension = new TestableNoturExtension();
        $extension->setTestBasePath($path);
        return $extension;
    }
}

/**
 * Testable implementation of NoturExtension that allows setting base path.
 */
class TestableNoturExtension extends NoturExtension
{
    public function setTestBasePath(string $path): void
    {
        $this->setBasePath($path);
    }
}
