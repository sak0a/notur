<?php

declare(strict_types=1);

namespace Notur\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Notur\Models\RemotePushApiKey;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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

        if ($provided === '') {
            abort(403, 'Invalid Notur remote push key.');
        }

        $dbKey = $this->lookupDbKey($provided);
        if ($dbKey !== null) {
            $dbKey->forceFill([
                'last_used_at' => now(),
                'last_used_ip' => $request->ip(),
            ])->save();

            $request->attributes->set('notur_remote_push_key', $dbKey);
            return $next($request);
        }

        $envKeys = config('notur.remote_push.keys', []);
        if (is_array($envKeys)) {
            foreach ($envKeys as $key) {
                if (is_string($key) && $key !== '' && hash_equals($key, $provided)) {
                    return $next($request);
                }
            }
        }

        abort(403, 'Invalid Notur remote push key.');
    }

    private function lookupDbKey(string $provided): ?RemotePushApiKey
    {
        try {
            $candidates = RemotePushApiKey::query()
                ->where('prefix', RemotePushApiKey::prefixOf($provided))
                ->whereNull('revoked_at')
                ->get();
        } catch (Throwable) {
            return null;
        }

        $providedHash = RemotePushApiKey::hashToken($provided);
        foreach ($candidates as $candidate) {
            if (hash_equals((string) $candidate->token_hash, $providedHash)) {
                return $candidate;
            }
        }

        return null;
    }
}
