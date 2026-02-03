<?php

declare(strict_types=1);

namespace Notur\Support;

final class HealthCheckNormalizer
{
    /**
     * Normalize health check results into a consistent array format.
     *
     * @param array<int|string, array<string, mixed>> $results
     * @return array<int, array<string, mixed>>
     */
    public static function normalize(array $results): array
    {
        $normalized = [];

        foreach ($results as $key => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $id = $entry['id'] ?? (is_string($key) ? $key : null);
            if (!is_string($id) || $id === '') {
                continue;
            }

            $statusRaw = strtolower((string) ($entry['status'] ?? 'unknown'));
            $status = match ($statusRaw) {
                'ok', 'pass', 'healthy' => 'ok',
                'warn', 'warning' => 'warning',
                'error', 'fail', 'critical' => 'error',
                default => 'unknown',
            };

            $normalized[] = [
                'id' => $id,
                'status' => $status,
                'message' => isset($entry['message']) ? (string) $entry['message'] : null,
                'details' => $entry['details'] ?? null,
                'checked_at' => $entry['checked_at'] ?? null,
            ];
        }

        return $normalized;
    }
}
