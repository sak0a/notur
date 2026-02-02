<?php

declare(strict_types=1);

namespace Notur\Contracts;

interface HasMiddleware
{
    /**
     * Return middleware class names keyed by middleware group.
     *
     * @return array<string, array<class-string>>
     */
    public function getMiddleware(): array;
}
