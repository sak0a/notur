<?php

declare(strict_types=1);

namespace Notur\Contracts;

/**
 * Interface for extensions that define frontend slot registrations.
 *
 * @deprecated Define slots in frontend code via createExtension({ slots: [...] }) instead.
 *             This interface will be removed in a future major version.
 *             Frontend slot registration should be done in your extension's JavaScript/TypeScript
 *             bundle using the Notur SDK's createExtension() function.
 */
interface HasFrontendSlots
{
    /**
     * Return frontend slot registrations.
     *
     * @deprecated Use frontend createExtension({ slots: [...] }) instead.
     * @return array<string, array<string, mixed>>
     */
    public function getFrontendSlots(): array;
}
