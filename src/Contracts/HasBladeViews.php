<?php

declare(strict_types=1);

namespace Notur\Contracts;

interface HasBladeViews
{
    /**
     * Return the path to the extension's views directory.
     */
    public function getViewsPath(): string;

    /**
     * Return the view namespace (e.g., "acme-analytics").
     */
    public function getViewNamespace(): string;
}
