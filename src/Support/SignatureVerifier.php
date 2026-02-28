<?php

declare(strict_types=1);

namespace Notur\Support;

use RuntimeException;

class SignatureVerifier
{
    /**
     * Verify an Ed25519 signature for an extension archive.
     */
    public function verify(string $filePath, string $signature, string $publicKey): bool
    {
        if (!extension_loaded('sodium')) {
            throw new RuntimeException('The sodium extension is required for signature verification');
        }

        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new RuntimeException("Cannot read file: {$filePath}");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException("Cannot read file: {$filePath}");
        }

        $sigBin = @sodium_hex2bin(trim($signature));
        if ($sigBin === false) {
            throw new RuntimeException('Invalid signature format (expected hex-encoded Ed25519 signature)');
        }

        $keyBin = @sodium_hex2bin(trim($publicKey));
        if ($keyBin === false) {
            throw new RuntimeException('Invalid public key format (expected hex-encoded Ed25519 key)');
        }

        return sodium_crypto_sign_verify_detached($sigBin, $content, $keyBin);
    }

    /**
     * Generate a signature for an extension archive.
     */
    public function sign(string $filePath, string $secretKey): string
    {
        if (!extension_loaded('sodium')) {
            throw new RuntimeException('The sodium extension is required for signature generation');
        }

        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new RuntimeException("Cannot read file: {$filePath}");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException("Cannot read file: {$filePath}");
        }

        $keyBin = @sodium_hex2bin(trim($secretKey));
        if ($keyBin === false) {
            throw new RuntimeException('Invalid secret key format (expected hex-encoded Ed25519 key)');
        }
        $signature = sodium_crypto_sign_detached($content, $keyBin);

        return sodium_bin2hex($signature);
    }

    /**
     * Generate a new Ed25519 keypair.
     *
     * @return array{public: string, secret: string}
     */
    public function generateKeypair(): array
    {
        if (!extension_loaded('sodium')) {
            throw new RuntimeException('The sodium extension is required for key generation');
        }

        $keypair = sodium_crypto_sign_keypair();

        return [
            'public' => sodium_bin2hex(sodium_crypto_sign_publickey($keypair)),
            'secret' => sodium_bin2hex(sodium_crypto_sign_secretkey($keypair)),
        ];
    }

    /**
     * Verify checksum of a file.
     */
    public function verifyChecksum(string $filePath, string $expectedHash, string $algo = 'sha256'): bool
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("Cannot read file: {$filePath}");
        }

        $hash = hash_file($algo, $filePath);
        if (!is_string($hash)) {
            return false;
        }

        return hash_equals($expectedHash, $hash);
    }
}
