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

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException("Cannot read file: {$filePath}");
        }

        $sigBin = sodium_hex2bin($signature);
        $keyBin = sodium_hex2bin($publicKey);

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

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException("Cannot read file: {$filePath}");
        }

        $keyBin = sodium_hex2bin($secretKey);
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
        $hash = hash_file($algo, $filePath);
        return hash_equals($expectedHash, $hash);
    }
}
