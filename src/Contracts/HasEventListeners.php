<?php

declare(strict_types=1);

namespace Notur\Contracts;

interface HasEventListeners
{
    /**
     * Return event-to-listener mappings.
     *
     * @return array<class-string, array<class-string>>
     */
    public function getEventListeners(): array;
}
