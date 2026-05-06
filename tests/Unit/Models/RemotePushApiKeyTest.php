<?php

declare(strict_types=1);

namespace Notur\Tests\Unit\Models;

use Notur\Models\RemotePushApiKey;
use PHPUnit\Framework\TestCase;

class RemotePushApiKeyTest extends TestCase
{
    public function test_generate_plaintext_has_notur_prefix_and_sufficient_length(): void
    {
        $plaintext = RemotePushApiKey::generatePlaintext();

        $this->assertStringStartsWith('notur_', $plaintext);
        $this->assertGreaterThanOrEqual(40, strlen($plaintext));
    }

    public function test_generate_plaintext_is_unique_across_calls(): void
    {
        $a = RemotePushApiKey::generatePlaintext();
        $b = RemotePushApiKey::generatePlaintext();

        $this->assertNotSame($a, $b);
    }

    public function test_hash_token_returns_sha256_hex(): void
    {
        $hash = RemotePushApiKey::hashToken('notur_example');

        $this->assertSame(64, strlen($hash));
        $this->assertSame(hash('sha256', 'notur_example'), $hash);
    }

    public function test_prefix_of_returns_first_12_chars(): void
    {
        $this->assertSame('notur_abcdef', RemotePushApiKey::prefixOf('notur_abcdef0123456789'));
    }

    public function test_prefix_of_short_input_returns_input(): void
    {
        $this->assertSame('notur_a', RemotePushApiKey::prefixOf('notur_a'));
    }
}
