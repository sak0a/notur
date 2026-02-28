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
        if ($context->extension instanceof HasRoutes) {
            return true;
        }

        $manifestRoutes = $context->manifest->getRoutes();
        if (is_array($manifestRoutes) && $manifestRoutes !== []) {
            return true;
        }

        return $this->hasDefaultRouteFiles($context->path);
    }

    public function register(ExtensionContext $context): void
    {
        $routeFiles = $this->resolveRouteFiles($context);
        if ($routeFiles === []) {
            return;
        }

        foreach ($routeFiles as $group => $file) {
            if (!is_string($file) || $file === '') {
                continue;
            }

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
                'admin' => [
                    'web',
                    class_exists(\Pterodactyl\Http\Middleware\AdminAuthenticate::class)
                        ? \Pterodactyl\Http\Middleware\AdminAuthenticate::class
                        : 'admin',
                ],
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

    /**
     * Resolve route files from the extension class, manifest, or convention defaults.
     *
     * @return array<string, string>
     */
    private function resolveRouteFiles(ExtensionContext $context): array
    {
        if ($context->extension instanceof HasRoutes) {
            $routeFiles = $context->extension->getRouteFiles();
            return is_array($routeFiles) ? $routeFiles : [];
        }

        $manifestRoutes = $context->manifest->getRoutes();
        if (is_array($manifestRoutes) && $manifestRoutes !== []) {
            return $manifestRoutes;
        }

        return $this->defaultRouteFiles($context->path);
    }

    /**
     * @return array<string, string>
     */
    private function defaultRouteFiles(string $extensionPath): array
    {
        $defaults = [
            'api-client' => 'src/routes/api-client.php',
            'admin' => 'src/routes/admin.php',
            'web' => 'src/routes/web.php',
        ];

        $resolved = [];
        foreach ($defaults as $group => $relativePath) {
            $fullPath = rtrim($extensionPath, '/') . '/' . $relativePath;
            if (file_exists($fullPath)) {
                $resolved[$group] = $relativePath;
            }
        }

        return $resolved;
    }

    private function hasDefaultRouteFiles(string $extensionPath): bool
    {
        return $this->defaultRouteFiles($extensionPath) !== [];
    }
}
