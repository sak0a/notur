<?php

declare(strict_types=1);

namespace Notur\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Notur\PermissionBroker;
use Symfony\Component\HttpFoundation\Response;

class ExtensionPermission
{
    public function __construct(
        private readonly PermissionBroker $broker,
    ) {}

    /**
     * Enforce an extension permission on the current request.
     *
     * Usage in route definitions:
     *   ->middleware('notur.permission:view-stats')
     *
     * The middleware resolves the extension ID from the request attributes
     * (set by ExtensionNamespace) and checks:
     *   1. The extension has declared the permission in its manifest.
     *   2. The authenticated user holds the scoped permission.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $extensionId = $request->attributes->get('notur.extension_id');

        if ($extensionId === null) {
            abort(403, 'Extension context not available.');
        }

        // Verify the extension actually declares this permission
        if (!$this->broker->extensionDeclares($extensionId, $permission)) {
            abort(403, "Permission '{$permission}' is not declared by extension '{$extensionId}'.");
        }

        // Build the scoped permission key: notur.<vendor/name>.<permission>
        $scopedPermission = $this->broker->scopePermission($extensionId, $permission);

        // Check the authenticated user
        $user = $request->user();

        if ($user === null) {
            abort(403, 'Authentication required.');
        }

        // Pterodactyl admin users bypass permission checks
        if ($this->isAdmin($user)) {
            return $next($request);
        }

        // For server-scoped routes, check sub-user permissions via Pterodactyl's system
        $server = $request->attributes->get('server');

        if ($server !== null && method_exists($user, 'permissions')) {
            $permissions = $user->permissions($server);

            if (!in_array($scopedPermission, $permissions, true) && !in_array('*', $permissions, true)) {
                abort(403, 'You do not have permission to perform this action.');
            }

            return $next($request);
        }

        // For non-server routes, check if user has the permission via a generic check.
        // Pterodactyl stores permissions on the server-user pivot; for account-level
        // extension routes we fall back to checking admin status (handled above) or
        // a simple can() gate if available.
        if (method_exists($user, 'can') && !$user->can($scopedPermission)) {
            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }

    /**
     * Check if the user is a Pterodactyl admin.
     *
     * Pterodactyl's User model uses a `root_admin` boolean column.
     */
    private function isAdmin(mixed $user): bool
    {
        if (property_exists($user, 'root_admin')) {
            return (bool) $user->root_admin;
        }

        if (method_exists($user, 'isRootAdmin')) {
            return $user->isRootAdmin();
        }

        // Fallback: check the attribute directly
        if ($user instanceof \Illuminate\Database\Eloquent\Model) {
            return (bool) $user->getAttribute('root_admin');
        }

        return false;
    }
}
