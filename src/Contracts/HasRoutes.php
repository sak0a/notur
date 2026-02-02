<?php

declare(strict_types=1);

namespace Notur\Contracts;

interface HasRoutes
{
    /**
     * Return an array of route file paths keyed by group name.
     *
     * Supported groups: "api-client", "admin", "web"
     *
     * @return array<string, string>
     */
    public function getRouteFiles(): array;
}
