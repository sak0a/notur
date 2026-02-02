<?php

declare(strict_types=1);

namespace Notur\Tests\Unit;

use Notur\Support\NoturArchive;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class NoturArchiveTest extends TestCase
{
    private string $tempDir;
    private string $sourceDir;
    private string $extractDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/notur-archive-test-' . uniqid();
        $this->sourceDir = $this->tempDir . '/source';
        $this->extractDir = $this->tempDir . '/extract';

        mkdir($this->sourceDir . '/src', 0755, true);
        mkdir($this->extractDir, 0755, true);

        // Create a minimal extension structure
        file_put_contents($this->sourceDir . '/extension.yaml', implode("\n", [
            'id: "acme/test"',
            'name: "Test Extension"',
            'version: "1.0.0"',
            'entrypoint: "Acme\\Test\\TestExtension"',
        ]));

        file_put_contents($this->sourceDir . '/src/TestExtension.php', '<?php // test');
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->tempDir);
    }

    public function test_pack_creates_archive(): void
    {
        $outputPath = $this->tempDir . '/test.notur';

        $result = NoturArchive::pack($this->sourceDir, $outputPath);

        $this->assertFileExists($outputPath);
        $this->assertArrayHasKey('archive', $result);
        $this->assertArrayHasKey('checksums', $result);
        $this->assertSame($outputPath, $result['archive']);
    }

    public function test_pack_generates_checksums(): void
    {
        $outputPath = $this->tempDir . '/test.notur';

        $result = NoturArchive::pack($this->sourceDir, $outputPath);
        $checksums = $result['checksums'];

        $this->assertArrayHasKey('extension.yaml', $checksums);
        $this->assertArrayHasKey('src/TestExtension.php', $checksums);

        // Verify checksums are valid SHA-256 hex strings (64 chars)
        foreach ($checksums as $hash) {
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
        }
    }

    public function test_pack_excludes_node_modules_and_git(): void
    {
        mkdir($this->sourceDir . '/node_modules', 0755);
        file_put_contents($this->sourceDir . '/node_modules/package.json', '{}');

        mkdir($this->sourceDir . '/.git', 0755);
        file_put_contents($this->sourceDir . '/.git/HEAD', 'ref: refs/heads/main');

        $outputPath = $this->tempDir . '/test.notur';
        $result = NoturArchive::pack($this->sourceDir, $outputPath);

        $this->assertArrayNotHasKey('node_modules/package.json', $result['checksums']);
        $this->assertArrayNotHasKey('.git/HEAD', $result['checksums']);
    }

    public function test_unpack_extracts_files(): void
    {
        $outputPath = $this->tempDir . '/test.notur';
        NoturArchive::pack($this->sourceDir, $outputPath);

        $checksums = NoturArchive::unpack($outputPath, $this->extractDir);

        $this->assertFileExists($this->extractDir . '/extension.yaml');
        $this->assertFileExists($this->extractDir . '/src/TestExtension.php');
        $this->assertFileExists($this->extractDir . '/checksums.json');
        $this->assertNotEmpty($checksums);
    }

    public function test_unpack_verifies_checksums(): void
    {
        $outputPath = $this->tempDir . '/test.notur';
        NoturArchive::pack($this->sourceDir, $outputPath);

        // This should not throw
        $checksums = NoturArchive::unpack($outputPath, $this->extractDir, true);
        $this->assertNotEmpty($checksums);
    }

    public function test_verify_checksums_detects_tampered_files(): void
    {
        $outputPath = $this->tempDir . '/test.notur';
        NoturArchive::pack($this->sourceDir, $outputPath);
        NoturArchive::unpack($outputPath, $this->extractDir, false);

        // Tamper with a file
        file_put_contents($this->extractDir . '/src/TestExtension.php', '<?php // TAMPERED');

        // Read the checksums
        $checksums = json_decode(
            file_get_contents($this->extractDir . '/checksums.json'),
            true,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Checksum verification failed');

        NoturArchive::verifyChecksums($this->extractDir, $checksums);
    }

    public function test_read_checksums_from_archive(): void
    {
        $outputPath = $this->tempDir . '/test.notur';
        NoturArchive::pack($this->sourceDir, $outputPath);

        $checksums = NoturArchive::readChecksums($outputPath);

        $this->assertNotNull($checksums);
        $this->assertArrayHasKey('extension.yaml', $checksums);
    }

    public function test_pack_throws_for_nonexistent_directory(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Source directory does not exist');

        NoturArchive::pack('/nonexistent/path', $this->tempDir . '/out.notur');
    }

    public function test_unpack_throws_for_nonexistent_archive(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Archive not found');

        NoturArchive::unpack('/nonexistent/archive.notur', $this->extractDir);
    }

    public function test_roundtrip_preserves_file_content(): void
    {
        $outputPath = $this->tempDir . '/test.notur';
        NoturArchive::pack($this->sourceDir, $outputPath);
        NoturArchive::unpack($outputPath, $this->extractDir);

        $originalYaml = file_get_contents($this->sourceDir . '/extension.yaml');
        $extractedYaml = file_get_contents($this->extractDir . '/extension.yaml');

        $this->assertSame($originalYaml, $extractedYaml);

        $originalPhp = file_get_contents($this->sourceDir . '/src/TestExtension.php');
        $extractedPhp = file_get_contents($this->extractDir . '/src/TestExtension.php');

        $this->assertSame($originalPhp, $extractedPhp);
    }

    private function deleteDir(string $dir): void
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
}
