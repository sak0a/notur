<?php

declare(strict_types=1);

namespace Notur\Contracts;

interface HasFrontendSlots
{
    /**
     * Return frontend slot registrations as defined in extension.yaml.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getFrontendSlots(): array;
}
