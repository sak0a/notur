<?php

declare(strict_types=1);

namespace Notur\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyRemotePushKey
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('notur.remote_push.enabled', false)) {
            abort(404);
        }

        $provided = $request->bearerToken();
        if (!is_string($provided) || $provided === '') {
            $provided = (string) $request->header('X-Notur-Token', '');
        }

        $keys = config('notur.remote_push.keys', []);
        if (!is_array($keys) || $keys === []) {
            abort(403, 'Remote push is enabled but no keys are configured.');
        }

        foreach ($keys as $key) {
            if (is_string($key) && $key !== '' && hash_equals($key, $provided)) {
                return $next($request);
            }
        }

        abort(403, 'Invalid Notur remote push key.');
    }
}
