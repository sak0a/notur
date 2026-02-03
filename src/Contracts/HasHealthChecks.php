<?php

declare(strict_types=1);

namespace Notur\Contracts;

interface HasHealthChecks
{
    /**
     * Return health check results for this extension.
     *
     * Each entry should include:
     * - id (string)
     * - status: ok|warning|error|unknown
     * - message (optional)
     * - details (optional)
     *
     * @return array<int|string, array<string, mixed>>
     */
    public function getHealthChecks(): array;
}
