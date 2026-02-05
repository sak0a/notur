<?php

declare(strict_types=1);

namespace Notur\Tests\Unit\Support;

use Notur\Support\CapabilityMatcher;
use PHPUnit\Framework\TestCase;

class CapabilityMatcherTest extends TestCase
{
    public function test_matches_exact_version(): void
    {
        $this->assertTrue(CapabilityMatcher::matches('1', 1));
        $this->assertTrue(CapabilityMatcher::matches('2', 2));
        $this->assertFalse(CapabilityMatcher::matches('1', 2));
    }

    public function test_matches_version_with_minor(): void
    {
        $this->assertTrue(CapabilityMatcher::matches('1.0', 1));
        $this->assertTrue(CapabilityMatcher::matches('1.5', 1));
        $this->assertFalse(CapabilityMatcher::matches('2.0', 1));
    }

    public function test_matches_caret_range(): void
    {
        $this->assertTrue(CapabilityMatcher::matches('^1', 1));
        $this->assertTrue(CapabilityMatcher::matches('^1.0', 1));
        $this->assertFalse(CapabilityMatcher::matches('^2', 1));
    }

    public function test_matches_tilde_range(): void
    {
        $this->assertTrue(CapabilityMatcher::matches('~1', 1));
        $this->assertTrue(CapabilityMatcher::matches('~1.0', 1));
        $this->assertFalse(CapabilityMatcher::matches('~2', 1));
    }

    public function test_matches_greater_than(): void
    {
        // Note: The current implementation only checks major version equality
        $this->assertTrue(CapabilityMatcher::matches('>=1', 1));
        $this->assertFalse(CapabilityMatcher::matches('>=2', 1));
    }

    public function test_rejects_non_matching_version(): void
    {
        $this->assertFalse(CapabilityMatcher::matches('1', 2));
        $this->assertFalse(CapabilityMatcher::matches('2', 1));
        $this->assertFalse(CapabilityMatcher::matches('3', 1));
    }

    public function test_rejects_empty_constraint(): void
    {
        $this->assertFalse(CapabilityMatcher::matches('', 1));
    }

    public function test_rejects_invalid_constraint(): void
    {
        $this->assertFalse(CapabilityMatcher::matches('invalid', 1));
        $this->assertFalse(CapabilityMatcher::matches('abc', 1));
    }

    public function test_rejects_zero_major_version(): void
    {
        $this->assertFalse(CapabilityMatcher::matches('0', 0));
        $this->assertFalse(CapabilityMatcher::matches('0.1', 0));
    }
}
