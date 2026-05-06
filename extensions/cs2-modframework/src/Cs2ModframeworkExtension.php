<?php

declare(strict_types=1);

namespace Notur\Cs2Modframework;

use Illuminate\Support\Facades\Cache;
use Notur\Contracts\HasHealthChecks;
use Notur\Contracts\HasRoutes;
use Notur\Support\NoturExtension;
use Pterodactyl\Models\Server;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;

class Cs2ModframeworkExtension extends NoturExtension implements HasRoutes, HasHealthChecks
{
    public function getRouteFiles(): array
    {
        return [
            'api-client' => 'src/routes/api-client.php',
        ];
    }

    public function getHealthChecks(): array
    {
        $manifest = $this->getManifest();
        $basePath = $this->getBasePath();
        $routeFile = $manifest->getRoutes()['api-client'] ?? 'src/routes/api-client.php';
        $bundle = $manifest->getFrontendBundle();
        $permissions = $manifest->getPermissions();

        return [
            $this->checkFile(
                'frontend_bundle',
                $basePath . '/' . ltrim($bundle, '/'),
                'Frontend bundle is present.',
                'Frontend bundle is missing. Rebuild the extension frontend before packaging.',
            ),
            $this->checkFile(
                'api_routes',
                $basePath . '/' . ltrim((string) $routeFile, '/'),
                'API route file is present.',
                'API route file is missing, so the extension API cannot be registered.',
            ),
            $this->checkPterodactylRuntime(),
            $this->checkCacheStore(),
            [
                'id' => 'manifest_contract',
                'status' => in_array('cs2-modframework.manage', $permissions, true) && $bundle !== '' && $routeFile !== ''
                    ? 'ok'
                    : 'warning',
                'message' => in_array('cs2-modframework.manage', $permissions, true) && $bundle !== '' && $routeFile !== ''
                    ? 'Required route, permission, and frontend declarations are present.'
                    : 'Manifest is missing a required route, permission, or frontend declaration.',
            ],
        ];
    }

    private function checkFile(string $id, string $path, string $okMessage, string $errorMessage): array
    {
        return [
            'id' => $id,
            'status' => is_file($path) && is_readable($path) && filesize($path) > 0 ? 'ok' : 'error',
            'message' => is_file($path) && is_readable($path) && filesize($path) > 0 ? $okMessage : $errorMessage,
            'details' => $path,
        ];
    }

    private function checkPterodactylRuntime(): array
    {
        $missing = [];
        foreach ([Server::class, DaemonFileRepository::class] as $className) {
            if (!class_exists($className)) {
                $missing[] = $className;
            }
        }

        return [
            'id' => 'pterodactyl_runtime',
            'status' => $missing === [] ? 'ok' : 'error',
            'message' => $missing === []
                ? 'Required Pterodactyl classes are available.'
                : 'Missing required Pterodactyl classes: ' . implode(', ', $missing),
        ];
    }

    private function checkCacheStore(): array
    {
        try {
            Cache::getStore();

            return [
                'id' => 'cache_store',
                'status' => 'ok',
                'message' => 'Laravel cache store is available for version lookup caching.',
            ];
        } catch (\Throwable $e) {
            return [
                'id' => 'cache_store',
                'status' => 'warning',
                'message' => 'Laravel cache store is not available: ' . $e->getMessage(),
            ];
        }
    }
}
