<?php

declare(strict_types=1);

namespace Notur\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
                $server = $this->findServer((string) $serverIdentifier);
                if ($server === null) {
                    return $this->errorResponse($request, 404, "Server '{$serverIdentifier}' not found.");
                }
                $request->attributes->set('server', $server);
                return $next($request);
            }

            // Regular users: verify ownership or subuser access
            $server = $this->findAccessibleServer((string) $serverIdentifier, $user);

            if ($server === null) {
                return $this->errorResponse($request, 404, "Server '{$serverIdentifier}' not found or access denied.");
            }

            $request->attributes->set('server', $server);

            return $next($request);
        } catch (\Throwable $e) {
            Log::error("[Notur] VerifyServerAccess error: {$e->getMessage()}", [
                'server' => $serverIdentifier,
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
