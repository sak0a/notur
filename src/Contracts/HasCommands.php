<?php

declare(strict_types=1);

namespace Notur\Contracts;

interface HasCommands
{
    /**
     * Return an array of artisan command class names.
     *
     * @return array<class-string>
     */
    public function getCommands(): array;
}
