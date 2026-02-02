<?php

declare(strict_types=1);

namespace Notur\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Notur\ExtensionManager;
use Symfony\Component\HttpFoundation\Response;

class ExtensionNamespace
{
    public function __construct(
        private readonly ExtensionManager $manager,
    ) {}

    /**
     * Scope requests to the appropriate extension namespace.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Extract extension ID from the route prefix
        $path = $request->path();

        if (preg_match('#notur/([a-z0-9\-]+/[a-z0-9\-]+)#', $path, $matches)) {
            $extensionId = $matches[1];

            if (!$this->manager->isEnabled($extensionId)) {
                abort(404, "Extension '{$extensionId}' is not enabled.");
            }

            $request->attributes->set('notur.extension_id', $extensionId);
        }

        return $next($request);
    }
}
