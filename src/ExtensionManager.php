<?php

declare(strict_types=1);

namespace Notur;

use Composer\Autoload\ClassLoader;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Notur\Contracts\ExtensionInterface;
use Notur\Contracts\HasBladeViews;
use Notur\Contracts\HasCommands;
use Notur\Contracts\HasEventListeners;
use Notur\Contracts\HasFrontendSlots;
use Notur\Contracts\HasMiddleware;
use Notur\Contracts\HasMigrations;
use Notur\Contracts\HasRoutes;
use Notur\Models\InstalledExtension;
use Notur\Support\ThemeCompiler;
use RuntimeException;

class ExtensionManager
{
    /** @var array<string, ExtensionInterface> */
    private array $extensions = [];

    /** @var array<string, ExtensionManifest> */
    private array $manifests = [];

    /** @var array<string, array<string, mixed>> */
    private array $frontendSlots = [];

    /** @var array<string, array<string, mixed>> */
    private array $frontendRoutes = [];

    private bool $booted = false;

    public function __construct(
        private readonly Application $app,
        private readonly DependencyResolver $resolver,
        private readonly PermissionBroker $permissionBroker,
        private ?ThemeCompiler $themeCompiler = null,
    ) {}

    /**
     * Boot all enabled extensions in dependency order.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $extensionsPath = $this->getExtensionsPath();
        $manifestFile = $this->getManifestPath();

        if (!file_exists($manifestFile)) {
            $this->booted = true;
            return;
        }

        $manifest = json_decode(file_get_contents($manifestFile), true);

        if (empty($manifest['extensions'])) {
            $this->booted = true;
            return;
        }

        // Build dependency graph for enabled extensions
        $graph = [];
        $enabledExtensions = [];

        foreach ($manifest['extensions'] as $id => $entry) {
            if (!($entry['enabled'] ?? false)) {
                continue;
            }

            $extPath = $extensionsPath . '/' . str_replace('/', DIRECTORY_SEPARATOR, $id);

            try {
                $extManifest = ExtensionManifest::load($extPath);
            } catch (\Throwable) {
                continue;
            }

            $this->manifests[$id] = $extManifest;
            $graph[$id] = array_keys($extManifest->getDependencies());
            $enabledExtensions[$id] = $extPath;
        }

        // Resolve load order
        $loadOrder = $this->resolver->resolve($graph);

        // Register autoloading and boot each extension
        foreach ($loadOrder as $id) {
            if (!isset($enabledExtensions[$id])) {
                continue;
            }

            $extPath = $enabledExtensions[$id];
            $extManifest = $this->manifests[$id];

            $this->registerAutoloading($extManifest, $extPath);
            $this->bootExtension($id, $extManifest, $extPath);
        }

        $this->booted = true;
    }

    private function registerAutoloading(ExtensionManifest $manifest, string $extPath): void
    {
        $autoload = $manifest->getAutoload();
        $psr4 = $autoload['psr-4'] ?? [];

        if (empty($psr4)) {
            return;
        }

        // Find Composer's ClassLoader
        $loaders = ClassLoader::getRegisteredLoaders();
        $loader = reset($loaders);

        if (!$loader) {
            return;
        }

        foreach ($psr4 as $namespace => $path) {
            $loader->addPsr4($namespace, $extPath . '/' . ltrim($path, '/'));
        }
    }

    private function bootExtension(string $id, ExtensionManifest $manifest, string $extPath): void
    {
        $entrypoint = $manifest->getEntrypoint();

        if (!class_exists($entrypoint)) {
            return;
        }

        /** @var ExtensionInterface $extension */
        $extension = $this->app->make($entrypoint);

        if (!$extension instanceof ExtensionInterface) {
            throw new RuntimeException(
                "Extension '{$id}' entrypoint must implement " . ExtensionInterface::class
            );
        }

        // Register phase
        $extension->register();

        // Register routes
        if ($extension instanceof HasRoutes) {
            $this->registerRoutes($id, $extension, $extPath);
        }

        // Register commands
        if ($extension instanceof HasCommands && $this->app->runningInConsole()) {
            $this->app->make('Illuminate\Contracts\Console\Kernel');
            \Illuminate\Support\Facades\Artisan::starting(function ($artisan) use ($extension) {
                foreach ($extension->getCommands() as $command) {
                    $artisan->resolve($command);
                }
            });
        }

        // Register middleware
        if ($extension instanceof HasMiddleware) {
            $router = $this->app->make('router');
            foreach ($extension->getMiddleware() as $group => $middlewareClasses) {
                foreach ($middlewareClasses as $middleware) {
                    $router->pushMiddlewareToGroup($group, $middleware);
                }
            }
        }

        // Register event listeners
        if ($extension instanceof HasEventListeners) {
            foreach ($extension->getEventListeners() as $event => $listeners) {
                foreach ($listeners as $listener) {
                    Event::listen($event, $listener);
                }
            }
        }

        // Register Blade views
        if ($extension instanceof HasBladeViews) {
            View::addNamespace($extension->getViewNamespace(), $extension->getViewsPath());
        }

        // Collect frontend slots
        if ($extension instanceof HasFrontendSlots) {
            $this->frontendSlots[$id] = $extension->getFrontendSlots();
        }

        // Register permissions
        $permissions = $manifest->getPermissions();
        if (!empty($permissions)) {
            $this->permissionBroker->register($id, $permissions);
        }

        // Register theme overrides
        $theme = $manifest->getTheme();
        if (!empty($theme)) {
            $this->registerThemeOverrides($id, $theme, $extPath);
        }

        // Boot phase
        $extension->boot();

        $this->extensions[$id] = $extension;
    }

    private function registerRoutes(string $id, HasRoutes $extension, string $extPath): void
    {
        $routeFiles = $extension->getRouteFiles();

        foreach ($routeFiles as $group => $file) {
            $filePath = $extPath . '/' . ltrim($file, '/');

            if (!file_exists($filePath)) {
                continue;
            }

            $prefix = match ($group) {
                'api-client' => "api/client/notur/{$id}",
                'admin' => "admin/notur/{$id}",
                'web' => "notur/{$id}",
                default => "notur/{$id}",
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

    /**
     * Get a loaded extension by ID.
     */
    public function get(string $id): ?ExtensionInterface
    {
        return $this->extensions[$id] ?? null;
    }

    /**
     * Get all loaded extensions.
     *
     * @return array<string, ExtensionInterface>
     */
    public function all(): array
    {
        return $this->extensions;
    }

    /**
     * Get the manifest for an extension.
     */
    public function getManifest(string $id): ?ExtensionManifest
    {
        return $this->manifests[$id] ?? null;
    }

    /**
     * Get all frontend slot registrations.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getFrontendSlots(): array
    {
        return $this->frontendSlots;
    }

    /**
     * Check if an extension is loaded and enabled.
     */
    public function isEnabled(string $id): bool
    {
        return isset($this->extensions[$id]);
    }

    /**
     * Enable an extension in the manifest.
     */
    public function enable(string $id): void
    {
        $this->setExtensionEnabled($id, true);
    }

    /**
     * Disable an extension in the manifest.
     */
    public function disable(string $id): void
    {
        $this->setExtensionEnabled($id, false);
    }

    private function setExtensionEnabled(string $id, bool $enabled): void
    {
        $manifestFile = $this->getManifestPath();
        $manifest = file_exists($manifestFile)
            ? json_decode(file_get_contents($manifestFile), true)
            : ['extensions' => []];

        if (!isset($manifest['extensions'][$id])) {
            throw new RuntimeException("Extension '{$id}' is not installed.");
        }

        $manifest['extensions'][$id]['enabled'] = $enabled;

        file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        InstalledExtension::where('extension_id', $id)->update(['enabled' => $enabled]);
    }

    /**
     * Register an extension in the master manifest.
     */
    public function registerExtension(string $id, string $version): void
    {
        $manifestFile = $this->getManifestPath();
        $manifest = file_exists($manifestFile)
            ? json_decode(file_get_contents($manifestFile), true)
            : ['extensions' => []];

        $manifest['extensions'][$id] = [
            'version' => $version,
            'enabled' => true,
            'installed_at' => now()->toIso8601String(),
        ];

        $dir = dirname($manifestFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Unregister an extension from the master manifest.
     */
    public function unregisterExtension(string $id): void
    {
        $manifestFile = $this->getManifestPath();

        if (!file_exists($manifestFile)) {
            return;
        }

        $manifest = json_decode(file_get_contents($manifestFile), true);
        unset($manifest['extensions'][$id]);

        file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Get the path where extensions are stored.
     */
    public function getExtensionsPath(): string
    {
        return base_path('notur/extensions');
    }

    /**
     * Get the path to the master manifest file.
     */
    public function getManifestPath(): string
    {
        return base_path('notur/extensions.json');
    }

    /**
     * Get the public path for extension frontend assets.
     */
    public function getPublicPath(): string
    {
        return public_path('notur/extensions');
    }

    /**
     * Get the theme compiler instance.
     */
    public function getThemeCompiler(): ThemeCompiler
    {
        if ($this->themeCompiler === null) {
            $this->themeCompiler = $this->app->make(ThemeCompiler::class);
        }

        return $this->themeCompiler;
    }

    /**
     * Register theme overrides from an extension's manifest.
     *
     * The manifest `theme` section supports:
     * - `views`: Map of view namespace => relative path to views directory
     * - `css_variables`: Nested map of CSS variable overrides
     */
    private function registerThemeOverrides(string $id, array $theme, string $extPath): void
    {
        $compiler = $this->getThemeCompiler();

        // Register Blade view overrides — these take priority over default panel views
        $viewOverrides = $theme['views'] ?? [];
        if (!empty($viewOverrides)) {
            $resolvedOverrides = [];
            foreach ($viewOverrides as $namespace => $relativePath) {
                $viewPath = $extPath . '/' . ltrim($relativePath, '/');
                if (is_dir($viewPath)) {
                    // Prepend to the namespace hints so theme views take priority
                    View::prependNamespace($namespace, $viewPath);
                    $resolvedOverrides[$namespace] = $viewPath;
                }
            }
            $compiler->registerViewOverrides($id, $resolvedOverrides);
        }

        // Register CSS variable overrides
        $cssVariables = $theme['css_variables'] ?? [];
        if (!empty($cssVariables)) {
            $compiler->registerCssOverrides($id, $cssVariables);
        }
    }
}
