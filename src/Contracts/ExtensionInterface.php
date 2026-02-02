<?php

declare(strict_types=1);

namespace Notur\Contracts;

interface ExtensionInterface
{
    /**
     * Get the unique identifier of the extension (e.g., "acme/server-analytics").
     */
    public function getId(): string;

    /**
     * Get the human-readable name of the extension.
     */
    public function getName(): string;

    /**
     * Get the extension version string.
     */
    public function getVersion(): string;

    /**
     * Register bindings, services, or configuration.
     * Called during the "register" phase of Laravel's boot cycle.
     */
    public function register(): void;

    /**
     * Boot the extension after all extensions have been registered.
     * Called during the "boot" phase.
     */
    public function boot(): void;

    /**
     * Return the absolute path to the extension's root directory.
     */
    public function getBasePath(): string;
}
