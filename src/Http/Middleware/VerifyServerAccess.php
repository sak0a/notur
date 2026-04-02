<?php

declare(strict_types=1);

namespace Notur\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verify that the authenticated user has access to the server
 * identified by a route parameter.
 *
 * Usage in extension route files:
 *   ->middleware('notur.server-access')        // reads {server} parameter
 *   ->middleware('notur.server-access:serverId') // reads {serverId} parameter
 *
 * Handles both full UUID and short UUID lookups.
 * Admin users bypass the access check.
 * Sets the resolved server on $request->attributes->set('server', $server).
 */
class VerifyServerAccess
{
    public function handle(Request $request, Closure $next, string $parameterName = 'server'): Response
    {
        $serverIdentifier = $request->route($parameterName);

        if ($serverIdentifier === null || $serverIdentifier === '') {
            abort(404, 'Server identifier required.');
        }

        $user = $request->user();

        if ($user === null) {
            abort(403, 'Authentication required.');
        }

        // Admin users bypass access check
        if ($this->isAdmin($user)) {
            $server = $this->findServer((string) $serverIdentifier);
            if ($server === null) {
                abort(404, 'Server not found.');
            }
            $request->attributes->set('server', $server);
            return $next($request);
        }

        // Regular users: verify ownership or subuser access
        $server = $this->findAccessibleServer((string) $serverIdentifier, $user);

        if ($server === null) {
            abort(404, 'Server not found.');
        }

        $request->attributes->set('server', $server);

        return $next($request);
    }

    /**
     * Find a server by UUID or short UUID (admin lookup, no access check).
     */
    private function findServer(string $identifier): ?object
    {
        if (!class_exists('\Pterodactyl\Models\Server')) {
            return null;
        }

        return \Pterodactyl\Models\Server::query()
            ->where(fn ($q) => $q->where('uuid', $identifier)->orWhere('uuidShort', $identifier))
            ->where('suspended', false)
            ->first();
    }

    /**
     * Find a server the user has access to (owner or subuser).
     * Uses grouped orWhere for UUID/short UUID to prevent query logic bugs.
     */
    private function findAccessibleServer(string $identifier, mixed $user): ?object
    {
        if (!class_exists('\Pterodactyl\Models\Server')) {
            return null;
        }

        return \Pterodactyl\Models\Server::query()
            ->where(fn ($q) => $q->where('uuid', $identifier)->orWhere('uuidShort', $identifier))
            ->where('suspended', false)
            ->where(fn ($q) => $q
                ->where('owner_id', $user->id)
                ->orWhereHas('subusers', fn ($sq) => $sq->where('user_id', $user->id))
            )
            ->first();
    }

    /**
     * Check if the user is a Pterodactyl admin.
     */
    private function isAdmin(mixed $user): bool
    {
        if (property_exists($user, 'root_admin')) {
            return (bool) $user->root_admin;
        }

        if (method_exists($user, 'isRootAdmin')) {
            return $user->isRootAdmin();
        }

        if ($user instanceof \Illuminate\Database\Eloquent\Model) {
            return (bool) $user->getAttribute('root_admin');
        }

        return false;
    }
}
