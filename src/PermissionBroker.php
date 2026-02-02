<?php

declare(strict_types=1);

namespace Notur;

use Notur\Models\InstalledExtension;

class PermissionBroker
{
    /**
     * All registered extension permissions keyed by extension ID.
     *
     * @var array<string, array<string>>
     */
    private array $permissions = [];

    /**
     * Register permissions declared by an extension.
     */
    public function register(string $extensionId, array $permissions): void
    {
        $this->permissions[$extensionId] = $permissions;
    }

    /**
     * Check if an extension has declared a specific permission.
     */
    public function extensionDeclares(string $extensionId, string $permission): bool
    {
        return in_array($permission, $this->permissions[$extensionId] ?? [], true);
    }

    /**
     * Get all permissions declared by an extension.
     */
    public function getExtensionPermissions(string $extensionId): array
    {
        return $this->permissions[$extensionId] ?? [];
    }

    /**
     * Get all registered permissions across all extensions.
     *
     * @return array<string, array<string>>
     */
    public function getAllPermissions(): array
    {
        return $this->permissions;
    }

    /**
     * Scope a permission key to an extension namespace.
     */
    public function scopePermission(string $extensionId, string $permission): string
    {
        return "notur.{$extensionId}.{$permission}";
    }

    /**
     * Check if the given permission belongs to the given extension.
     */
    public function isOwnedBy(string $extensionId, string $scopedPermission): bool
    {
        return str_starts_with($scopedPermission, "notur.{$extensionId}.");
    }
}
