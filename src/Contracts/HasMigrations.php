<?php

declare(strict_types=1);

namespace Notur\Contracts;

interface HasMigrations
{
    /**
     * Return the path to the extension's migrations directory.
     */
    public function getMigrationsPath(): string;
}
