<?php

declare(strict_types=1);

namespace Notur\Features;

use Illuminate\Support\Facades\Route;
use Notur\Contracts\HasRoutes;

final class RoutesFeature implements ExtensionFeature
{
    public function getCapabilityId(): ?string
    {
        return 'routes';
    }

    public function getCapabilityVersion(): int
    {
        return 1;
    }

    public function isEnabledByDefault(): bool
    {
        return true;
    }

    public function supports(ExtensionContext $context): bool
    {
        return $context->extension instanceof HasRoutes;
    }

    public function register(ExtensionContext $context): void
    {
        if (!$context->extension instanceof HasRoutes) {
            return;
        }

        $routeFiles = $context->extension->getRouteFiles();

        foreach ($routeFiles as $group => $file) {
            $filePath = $context->path . '/' . ltrim($file, '/');

            if (!file_exists($filePath)) {
                continue;
            }

            $prefix = match ($group) {
                'api-client' => "api/client/notur/{$context->id}",
                'admin' => "admin/notur/{$context->id}",
                'web' => "notur/{$context->id}",
                default => "notur/{$context->id}",
            };

            $middleware = match ($group) {
                'api-client' => ['api', 'client-api', 'throttle:api.client'],
                'admin' => ['web', 'admin'],
                'web' => ['web'],
                default => ['web'],
            };

            Route::prefix($prefix)
                ->middleware($middleware)
                ->group($filePath);
        }
    }

    public function boot(ExtensionContext $context): void
    {
        // No-op for now (routes are registered in the register phase).
    }
}
