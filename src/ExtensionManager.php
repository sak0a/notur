<?php

declare(strict_types=1);

namespace Notur;

use Composer\Autoload\ClassLoader;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Notur\Contracts\ExtensionInterface;
use Notur\Contracts\HasBladeViews;
use Notur\Contracts\HasCommands;
use Notur\Contracts\HasEventListeners;
use Notur\Contracts\HasFrontendSlots;
use Notur\Contracts\HasHealthChecks;
use Notur\Contracts\HasMiddleware;
use Notur\Contracts\HasMigrations;
use Notur\Features\ExtensionContext;
use Notur\Features\FeatureRegistry;
use Notur\Models\InstalledExtension;
use Notur\Support\HealthCheckNormalizer;
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

    /** @var array<string, HasHealthChecks> */
    private array $healthCheckProviders = [];

    private bool $booted = false;
    private FeatureRegistry $featureRegistry;

    public function __construct(
        private readonly Application $app,
        private readonly DependencyResolver $resolver,
        private readonly PermissionBroker $permissionBroker,
        private ?ThemeCompiler $themeCompiler = null,
        ?FeatureRegistry $featureRegistry = null,
    ) {
        $this->featureRegistry = $featureRegistry ?? FeatureRegistry::defaults();
    }

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

            $psr4 = $this->resolveAutoloadPsr4($extManifest, $extPath);
            $this->registerAutoloading($psr4, $extPath);
            $this->bootExtension($id, $extManifest, $extPath, $psr4);
        }

        $this->booted = true;
    }

    private function registerAutoloading(array $psr4, string $extPath): void
    {
        if ($psr4 === []) {
            return;
        }

        // Find Composer's ClassLoader
        $loaders = ClassLoader::getRegisteredLoaders();
        $loader = reset($loaders);

        if (!$loader) {
            return;
        }

        foreach ($psr4 as $namespace => $paths) {
            $resolved = [];
            foreach ((array) $paths as $path) {
                if (!is_string($path) || $path === '') {
                    continue;
                }
                $resolved[] = $this->resolvePath($extPath, $path);
            }

            if ($resolved === []) {
                continue;
            }

            $loader->addPsr4($namespace, count($resolved) === 1 ? $resolved[0] : $resolved);
        }
    }

    private function bootExtension(string $id, ExtensionManifest $manifest, string $extPath, array $psr4): void
    {
        $entrypoint = $this->resolveEntrypoint($manifest, $extPath, $psr4);
        if (!$entrypoint) {
            return;
        }

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

        $context = new ExtensionContext(
            id: $id,
            extension: $extension,
            manifest: $manifest,
            path: $extPath,
            app: $this->app,
            manager: $this,
        );

        // Register phase
        $extension->register();

        // Feature registration (post-register, pre-boot)
        $this->featureRegistry->register($context);

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
        $slots = [];
        if ($extension instanceof HasFrontendSlots) {
            $slots = $extension->getFrontendSlots();
        } else {
            $slots = $manifest->getFrontendSlots();
        }

        if (is_array($slots) && $slots !== []) {
            $this->frontendSlots[$id] = $slots;
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

        // Feature boot (post-extension boot)
        $this->featureRegistry->boot($context);

        $this->extensions[$id] = $extension;
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
     * Register a health check provider for an extension.
     */
    public function registerHealthCheckProvider(string $id, HasHealthChecks $provider): void
    {
        $this->healthCheckProviders[$id] = $provider;
    }

    /**
     * Get normalized health check results for an extension.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getHealthChecks(string $id): array
    {
        $provider = $this->healthCheckProviders[$id] ?? null;

        if (!$provider instanceof HasHealthChecks) {
            return [];
        }

        return HealthCheckNormalizer::normalize($provider->getHealthChecks());
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
     * Resolve PSR-4 autoload mappings from manifest, composer.json, or conventions.
     *
     * @return array<string, string|array<int, string>>
     */
    private function resolveAutoloadPsr4(ExtensionManifest $manifest, string $extPath): array
    {
        $autoload = $manifest->getAutoload();
        $psr4 = is_array($autoload) ? ($autoload['psr-4'] ?? []) : [];

        if (is_array($psr4) && $psr4 !== []) {
            return $psr4;
        }

        $composer = $this->readComposerJson($extPath);
        $composerPsr4 = [];
        if (isset($composer['autoload']) && is_array($composer['autoload'])) {
            $composerPsr4 = $composer['autoload']['psr-4'] ?? [];
        }
        if (is_array($composerPsr4) && $composerPsr4 !== []) {
            return $composerPsr4;
        }

        $namespace = $this->inferNamespaceFromId($manifest->getId());
        if ($namespace !== '') {
            return [$namespace . '\\' => 'src/'];
        }

        return [];
    }

    private function resolveEntrypoint(ExtensionManifest $manifest, string $extPath, array $psr4): ?string
    {
        $entrypoint = $manifest->getEntrypoint();
        if (is_string($entrypoint) && $entrypoint !== '') {
            return $entrypoint;
        }

        $composerEntrypoint = $this->readComposerEntrypoint($extPath);
        if ($composerEntrypoint !== null) {
            return $composerEntrypoint;
        }

        $defaultEntrypoint = $this->buildDefaultEntrypoint($manifest->getId());
        if ($defaultEntrypoint !== '' && class_exists($defaultEntrypoint) && is_subclass_of($defaultEntrypoint, ExtensionInterface::class)) {
            return $defaultEntrypoint;
        }

        $discovered = $this->discoverEntrypoint($extPath, $psr4, $defaultEntrypoint);
        if ($discovered !== null) {
            return $discovered;
        }

        return null;
    }

    private function readComposerEntrypoint(string $extPath): ?string
    {
        $composer = $this->readComposerJson($extPath);
        $extra = $composer['extra'] ?? null;
        if (!is_array($extra)) {
            return null;
        }

        $notur = $extra['notur'] ?? null;
        if (!is_array($notur)) {
            return null;
        }

        $entrypoint = $notur['entrypoint'] ?? null;
        if (!is_string($entrypoint) || $entrypoint === '') {
            return null;
        }

        return $entrypoint;
    }

    /**
     * @return array<string, mixed>
     */
    private function readComposerJson(string $extPath): array
    {
        $path = rtrim($extPath, '/') . '/composer.json';
        if (!file_exists($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function buildDefaultEntrypoint(string $id): string
    {
        $namespace = $this->inferNamespaceFromId($id);
        if ($namespace === '') {
            return '';
        }

        $className = $this->inferClassNameFromId($id);
        if ($className === '') {
            return '';
        }

        return $namespace . '\\' . $className;
    }

    private function inferNamespaceFromId(string $id): string
    {
        if (!str_contains($id, '/')) {
            return '';
        }

        [$vendor, $name] = explode('/', $id, 2);
        if ($vendor === '' || $name === '') {
            return '';
        }

        return $this->toStudly($vendor) . '\\' . $this->toStudly($name);
    }

    private function inferClassNameFromId(string $id): string
    {
        if (!str_contains($id, '/')) {
            return '';
        }

        [, $name] = explode('/', $id, 2);
        if ($name === '') {
            return '';
        }

        $classBase = $this->toStudly($name);
        return str_ends_with($classBase, 'Extension') ? $classBase : $classBase . 'Extension';
    }

    private function toStudly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $value)));
    }

    /**
     * @return array<int, string>
     */
    private function resolveAutoloadDirs(string $extPath, array $psr4): array
    {
        $dirs = [];

        foreach ($psr4 as $paths) {
            foreach ((array) $paths as $path) {
                if (!is_string($path) || $path === '') {
                    continue;
                }

                $dir = $this->resolvePath($extPath, $path);
                if (is_dir($dir)) {
                    $dirs[] = $dir;
                }
            }
        }

        return array_values(array_unique($dirs));
    }

    private function discoverEntrypoint(string $extPath, array $psr4, string $preferred): ?string
    {
        $dirs = $this->resolveAutoloadDirs($extPath, $psr4);
        if ($dirs === []) {
            $fallback = rtrim($extPath, '/') . '/src';
            if (is_dir($fallback)) {
                $dirs[] = $fallback;
            }
        }

        if ($dirs === []) {
            return null;
        }

        $candidates = $this->findExtensionClassCandidates($dirs);
        if ($candidates === []) {
            return null;
        }

        if ($preferred !== '' && isset($candidates[$preferred])) {
            $preferredFile = $candidates[$preferred];
            unset($candidates[$preferred]);
            $candidates = [$preferred => $preferredFile] + $candidates;
        }

        foreach ($candidates as $class => $file) {
            if (!class_exists($class)) {
                require_once $file;
            }

            if (is_subclass_of($class, ExtensionInterface::class)) {
                return $class;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $dirs
     * @return array<string, string> Map of class => file path.
     */
    private function findExtensionClassCandidates(array $dirs): array
    {
        $candidates = [];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $filename = $file->getFilename();
                if (!str_ends_with($filename, 'Extension.php')) {
                    continue;
                }

                $classes = $this->extractPhpClasses($file->getPathname());
                foreach ($classes as $class) {
                    if (!isset($candidates[$class])) {
                        $candidates[$class] = $file->getPathname();
                    }
                }
            }
        }

        return $candidates;
    }

    /**
     * @return array<int, string>
     */
    private function extractPhpClasses(string $file): array
    {
        $contents = file_get_contents($file);
        if ($contents === false) {
            return [];
        }

        $tokens = token_get_all($contents);
        $namespace = '';
        $classes = [];
        $previousToken = null;

        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token)) {
                if ($token[0] === T_NAMESPACE) {
                    $namespace = '';
                    for ($j = $i + 1; $j < $count; $j++) {
                        $next = $tokens[$j];
                        if (is_array($next) && in_array($next[0], [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                            $namespace .= $next[1];
                            continue;
                        }
                        if ($next === ';' || $next === '{') {
                            break;
                        }
                    }
                }

                if ($token[0] === T_CLASS) {
                    if ($previousToken === T_NEW) {
                        continue;
                    }

                    for ($j = $i + 1; $j < $count; $j++) {
                        $next = $tokens[$j];
                        if (is_array($next) && $next[0] === T_STRING) {
                            $className = $next[1];
                            $classes[] = $namespace !== '' ? $namespace . '\\' . $className : $className;
                            break;
                        }

                        if ($next === '{' || $next === ';') {
                            break;
                        }
                    }
                }

                if (!in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    $previousToken = $token[0];
                }
            } elseif (trim($token) !== '') {
                $previousToken = null;
            }
        }

        return $classes;
    }

    private function resolvePath(string $extPath, string $path): string
    {
        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return rtrim($extPath, '/') . '/' . ltrim($path, '/');
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
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
