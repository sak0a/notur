<?php

declare(strict_types=1);

namespace Notur\Tests\Unit;

use Notur\Support\SignatureVerifier;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SignatureVerifierTest extends TestCase
{
    private SignatureVerifier $verifier;
    private string $tempDir;

    protected function setUp(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('The sodium extension is required for these tests.');
        }

        $this->verifier = new SignatureVerifier();
        $this->tempDir = sys_get_temp_dir() . '/notur-sig-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->tempDir);
    }

    public function test_generate_keypair_returns_public_and_secret(): void
    {
        $keypair = $this->verifier->generateKeypair();

        $this->assertArrayHasKey('public', $keypair);
        $this->assertArrayHasKey('secret', $keypair);

        // Ed25519 public key is 32 bytes = 64 hex chars
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $keypair['public']);

        // Ed25519 secret key is 64 bytes = 128 hex chars
        $this->assertMatchesRegularExpression('/^[a-f0-9]{128}$/', $keypair['secret']);
    }

    public function test_generate_keypair_returns_unique_keys(): void
    {
        $first = $this->verifier->generateKeypair();
        $second = $this->verifier->generateKeypair();

        $this->assertNotSame($first['public'], $second['public']);
        $this->assertNotSame($first['secret'], $second['secret']);
    }

    public function test_sign_produces_valid_signature(): void
    {
        $keypair = $this->verifier->generateKeypair();
        $filePath = $this->tempDir . '/test-archive.notur';
        file_put_contents($filePath, 'extension archive content');

        $signature = $this->verifier->sign($filePath, $keypair['secret']);

        // Ed25519 signature is 64 bytes = 128 hex chars
        $this->assertMatchesRegularExpression('/^[a-f0-9]{128}$/', $signature);
    }

    public function test_verify_returns_true_for_valid_signature(): void
    {
        $keypair = $this->verifier->generateKeypair();
        $filePath = $this->tempDir . '/test-archive.notur';
        file_put_contents($filePath, 'extension archive content');

        $signature = $this->verifier->sign($filePath, $keypair['secret']);
        $result = $this->verifier->verify($filePath, $signature, $keypair['public']);

        $this->assertTrue($result);
    }

    public function test_verify_returns_false_for_wrong_public_key(): void
    {
        $keypair = $this->verifier->generateKeypair();
        $otherKeypair = $this->verifier->generateKeypair();

        $filePath = $this->tempDir . '/test-archive.notur';
        file_put_contents($filePath, 'extension archive content');

        $signature = $this->verifier->sign($filePath, $keypair['secret']);
        $result = $this->verifier->verify($filePath, $signature, $otherKeypair['public']);

        $this->assertFalse($result);
    }

    public function test_verify_returns_false_for_tampered_file(): void
    {
        $keypair = $this->verifier->generateKeypair();
        $filePath = $this->tempDir . '/test-archive.notur';
        file_put_contents($filePath, 'original content');

        $signature = $this->verifier->sign($filePath, $keypair['secret']);

        // Tamper with the file
        file_put_contents($filePath, 'tampered content');

        $result = $this->verifier->verify($filePath, $signature, $keypair['public']);

        $this->assertFalse($result);
    }

    public function test_verify_returns_false_for_invalid_signature(): void
    {
        $keypair = $this->verifier->generateKeypair();
        $filePath = $this->tempDir . '/test-archive.notur';
        file_put_contents($filePath, 'extension archive content');

        // Create a signature for different content
        $otherFile = $this->tempDir . '/other.notur';
        file_put_contents($otherFile, 'different content');
        $wrongSignature = $this->verifier->sign($otherFile, $keypair['secret']);

        $result = $this->verifier->verify($filePath, $wrongSignature, $keypair['public']);

        $this->assertFalse($result);
    }

    public function test_sign_throws_for_nonexistent_file(): void
    {
        $keypair = $this->verifier->generateKeypair();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot read file');

        $this->verifier->sign('/nonexistent/file.notur', $keypair['secret']);
    }

    public function test_verify_throws_for_nonexistent_file(): void
    {
        $keypair = $this->verifier->generateKeypair();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot read file');

        $this->verifier->verify(
            '/nonexistent/file.notur',
            str_repeat('a', 128),
            $keypair['public'],
        );
    }

    public function test_verify_checksum_returns_true_for_matching_hash(): void
    {
        $filePath = $this->tempDir . '/test-file.txt';
        file_put_contents($filePath, 'checksum test content');

        $expectedHash = hash_file('sha256', $filePath);
        $result = $this->verifier->verifyChecksum($filePath, $expectedHash);

        $this->assertTrue($result);
    }

    public function test_verify_checksum_returns_false_for_wrong_hash(): void
    {
        $filePath = $this->tempDir . '/test-file.txt';
        file_put_contents($filePath, 'checksum test content');

        $result = $this->verifier->verifyChecksum($filePath, str_repeat('0', 64));

        $this->assertFalse($result);
    }

    public function test_verify_checksum_supports_custom_algorithm(): void
    {
        $filePath = $this->tempDir . '/test-file.txt';
        file_put_contents($filePath, 'checksum test content');

        $expectedHash = hash_file('md5', $filePath);
        $result = $this->verifier->verifyChecksum($filePath, $expectedHash, 'md5');

        $this->assertTrue($result);
    }

    public function test_sign_and_verify_roundtrip_with_binary_content(): void
    {
        $keypair = $this->verifier->generateKeypair();
        $filePath = $this->tempDir . '/binary-archive.notur';

        // Write random binary content
        file_put_contents($filePath, random_bytes(1024));

        $signature = $this->verifier->sign($filePath, $keypair['secret']);
        $result = $this->verifier->verify($filePath, $signature, $keypair['public']);

        $this->assertTrue($result);
    }

    public function test_sign_and_verify_roundtrip_with_empty_file(): void
    {
        $keypair = $this->verifier->generateKeypair();
        $filePath = $this->tempDir . '/empty-archive.notur';

        file_put_contents($filePath, '');

        $signature = $this->verifier->sign($filePath, $keypair['secret']);
        $result = $this->verifier->verify($filePath, $signature, $keypair['public']);

        $this->assertTrue($result);
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
