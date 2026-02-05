<?php

declare(strict_types=1);

namespace Notur\Tests\Unit\Support;

use Notur\Support\HealthCheckNormalizer;
use PHPUnit\Framework\TestCase;

class HealthCheckNormalizerTest extends TestCase
{
    public function test_normalizes_ok_status(): void
    {
        $results = [
            ['id' => 'check1', 'status' => 'ok'],
            ['id' => 'check2', 'status' => 'pass'],
            ['id' => 'check3', 'status' => 'healthy'],
        ];

        $normalized = HealthCheckNormalizer::normalize($results);

        $this->assertCount(3, $normalized);
        $this->assertSame('ok', $normalized[0]['status']);
        $this->assertSame('ok', $normalized[1]['status']);
        $this->assertSame('ok', $normalized[2]['status']);
    }

    public function test_normalizes_warning_status(): void
    {
        $results = [
            ['id' => 'check1', 'status' => 'warn'],
            ['id' => 'check2', 'status' => 'warning'],
        ];

        $normalized = HealthCheckNormalizer::normalize($results);

        $this->assertCount(2, $normalized);
        $this->assertSame('warning', $normalized[0]['status']);
        $this->assertSame('warning', $normalized[1]['status']);
    }

    public function test_normalizes_error_status(): void
    {
        $results = [
            ['id' => 'check1', 'status' => 'error'],
            ['id' => 'check2', 'status' => 'fail'],
            ['id' => 'check3', 'status' => 'critical'],
        ];

        $normalized = HealthCheckNormalizer::normalize($results);

        $this->assertCount(3, $normalized);
        $this->assertSame('error', $normalized[0]['status']);
        $this->assertSame('error', $normalized[1]['status']);
        $this->assertSame('error', $normalized[2]['status']);
    }

    public function test_defaults_unknown_status(): void
    {
        $results = [
            ['id' => 'check1', 'status' => 'unknown'],
            ['id' => 'check2', 'status' => 'invalid'],
            ['id' => 'check3', 'status' => 'random'],
        ];

        $normalized = HealthCheckNormalizer::normalize($results);

        $this->assertCount(3, $normalized);
        $this->assertSame('unknown', $normalized[0]['status']);
        $this->assertSame('unknown', $normalized[1]['status']);
        $this->assertSame('unknown', $normalized[2]['status']);
    }

    public function test_preserves_message(): void
    {
        $results = [
            ['id' => 'check1', 'status' => 'ok', 'message' => 'All systems operational'],
        ];

        $normalized = HealthCheckNormalizer::normalize($results);

        $this->assertSame('All systems operational', $normalized[0]['message']);
    }

    public function test_preserves_details(): void
    {
        $results = [
            [
                'id' => 'check1',
                'status' => 'ok',
                'details' => ['cpu' => '50%', 'memory' => '2GB'],
            ],
        ];

        $normalized = HealthCheckNormalizer::normalize($results);

        $this->assertSame(['cpu' => '50%', 'memory' => '2GB'], $normalized[0]['details']);
    }

    public function test_preserves_checked_at(): void
    {
        $timestamp = '2024-01-15T10:00:00Z';
        $results = [
            ['id' => 'check1', 'status' => 'ok', 'checked_at' => $timestamp],
        ];

        $normalized = HealthCheckNormalizer::normalize($results);

        $this->assertSame($timestamp, $normalized[0]['checked_at']);
    }

    public function test_uses_key_as_id_fallback(): void
    {
        $results = [
            'database_check' => ['status' => 'ok'],
            'cache_check' => ['status' => 'warning'],
        ];

        $normalized = HealthCheckNormalizer::normalize($results);

        $this->assertCount(2, $normalized);
        $ids = array_column($normalized, 'id');
        $this->assertContains('database_check', $ids);
        $this->assertContains('cache_check', $ids);
    }

    public function test_skips_non_array_entries(): void
    {
        $results = [
            ['id' => 'valid', 'status' => 'ok'],
            'invalid_string',
            123,
            null,
        ];

        $normalized = HealthCheckNormalizer::normalize($results);

        $this->assertCount(1, $normalized);
        $this->assertSame('valid', $normalized[0]['id']);
    }

    public function test_skips_entries_without_id(): void
    {
        $results = [
            ['status' => 'ok'],
            ['id' => '', 'status' => 'ok'],
            ['id' => 'valid', 'status' => 'ok'],
        ];

        $normalized = HealthCheckNormalizer::normalize($results);

        $this->assertCount(1, $normalized);
        $this->assertSame('valid', $normalized[0]['id']);
    }

    public function test_handles_empty_array(): void
    {
        $normalized = HealthCheckNormalizer::normalize([]);

        $this->assertSame([], $normalized);
    }
}
