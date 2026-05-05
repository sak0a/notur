<?php

declare(strict_types=1);

namespace Notur\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
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
        $serverLabel = $this->serverLabel($serverIdentifier);

        if ($serverIdentifier === null || $serverIdentifier === '') {
            return $this->errorResponse($request, 404, 'Server identifier required.');
        }

        $user = $request->user();

        if ($user === null) {
            return $this->errorResponse($request, 403, 'Authentication required.');
        }

        if (!class_exists('\Pterodactyl\Models\Server')) {
            Log::error('[Notur] VerifyServerAccess: Pterodactyl\Models\Server class not found');
            return $this->errorResponse($request, 500, 'Server model not available. Is this a Pterodactyl Panel installation?');
        }

        try {
            // Admin users bypass access check
            if ($this->isAdmin($user)) {
                $server = $this->resolveServer($serverIdentifier);
                if ($server === null) {
                    return $this->errorResponse($request, 404, "Server '{$serverLabel}' not found.");
                }
                $request->attributes->set('server', $server);
                return $next($request);
            }

            // Regular users: verify ownership or subuser access
            $server = $this->resolveAccessibleServer($serverIdentifier, $user);

            if ($server === null) {
                return $this->errorResponse($request, 404, "Server '{$serverLabel}' not found or access denied.");
            }

            $request->attributes->set('server', $server);

            return $next($request);
        } catch (\Throwable $e) {
            Log::error("[Notur] VerifyServerAccess error: {$e->getMessage()}", [
                'server' => $serverLabel,
                'user' => $user->id ?? null,
                'exception' => $e,
            ]);

            return $this->errorResponse($request, 500, 'Failed to verify server access: ' . $e->getMessage());
        }
    }

    /**
     * Return an appropriate error response (JSON for API, abort for web).
     */
    private function errorResponse(Request $request, int $status, string $message): Response
    {
        if ($request->expectsJson() || str_starts_with($request->path(), 'api/')) {
            return response()->json(['message' => $message], $status);
        }

        abort($status, $message);
    }

    /**
     * Find a server by UUID or short UUID (admin lookup, no access check).
     */
    private function findServer(string $identifier): ?object
    {
        $query = \Pterodactyl\Models\Server::query()
            ->where(fn ($q) => $q->where('uuid', $identifier)->orWhere('uuidShort', $identifier));

        $this->applyNotSuspendedFilter($query);

        return $query->first();
    }

    /**
     * Find a server the user has access to (owner or subuser).
     * Uses grouped orWhere for UUID/short UUID to prevent query logic bugs.
     */
    private function findAccessibleServer(string $identifier, mixed $user): ?object
    {
        $query = \Pterodactyl\Models\Server::query()
            ->where(fn ($q) => $q->where('uuid', $identifier)->orWhere('uuidShort', $identifier))
            ->where(fn ($q) => $q
                ->where('owner_id', $user->id)
                ->orWhereHas('subusers', fn ($sq) => $sq->where('user_id', $user->id))
            );

        $this->applyNotSuspendedFilter($query);

        return $query->first();
    }

    private function resolveServer(mixed $serverIdentifier): ?object
    {
        if ($this->isServerModel($serverIdentifier)) {
            return $this->isSuspended($serverIdentifier) ? null : $serverIdentifier;
        }

        return $this->findServer((string) $serverIdentifier);
    }

    private function resolveAccessibleServer(mixed $serverIdentifier, mixed $user): ?object
    {
        if ($this->isServerModel($serverIdentifier)) {
            if ($this->isSuspended($serverIdentifier)) {
                return null;
            }

            return $this->userCanAccessServer($serverIdentifier, $user) ? $serverIdentifier : null;
        }

        return $this->findAccessibleServer((string) $serverIdentifier, $user);
    }

    private function isServerModel(mixed $value): bool
    {
        return is_object($value) && is_a($value, \Pterodactyl\Models\Server::class);
    }

    private function userCanAccessServer(object $server, mixed $user): bool
    {
        if ((int) ($server->owner_id ?? 0) === (int) ($user->id ?? 0)) {
            return true;
        }

        if (method_exists($server, 'subusers')) {
            return $server->subusers()
                ->where('user_id', $user->id)
                ->exists();
        }

        return false;
    }

    private function applyNotSuspendedFilter(mixed $query): void
    {
        try {
            if (Schema::hasColumn('servers', 'suspended')) {
                $query->where('suspended', false);
            }
        } catch (\Throwable) {
            // Some panel/test schemas do not expose the suspended column.
        }
    }

    private function isSuspended(object $server): bool
    {
        if ($server instanceof \Illuminate\Database\Eloquent\Model && $server->getAttribute('suspended') !== null) {
            return (bool) $server->getAttribute('suspended');
        }

        if (property_exists($server, 'suspended')) {
            return (bool) $server->suspended;
        }

        return false;
    }

    private function serverLabel(mixed $serverIdentifier): string
    {
        if (is_object($serverIdentifier)) {
            foreach (['uuid', 'uuidShort', 'id'] as $attribute) {
                $value = $serverIdentifier->{$attribute} ?? null;
                if (is_scalar($value) && (string) $value !== '') {
                    return (string) $value;
                }
            }

            return $serverIdentifier::class;
        }

        return (string) $serverIdentifier;
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
