<?php

declare(strict_types=1);

namespace Notur\Support;

final class CapabilityMatcher
{
    /**
     * Evaluate a capability version constraint against a supported major version.
     *
     * Supported formats (major-only):
     * - "1"
     * - "1.0"
     * - "^1"
     * - "~1"
     * - ">=1"
     *
     * Any unrecognized constraint returns false.
     */
    public static function matches(string $constraint, int $supportedMajor): bool
    {
        if ($constraint === '') {
            return false;
        }

        if (!preg_match('/(\d+)/', $constraint, $matches)) {
            return false;
        }

        $requestedMajor = (int) $matches[1];
        if ($requestedMajor <= 0) {
            return false;
        }

        return $requestedMajor === $supportedMajor;
    }
}
