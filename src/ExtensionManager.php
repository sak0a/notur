<?php

declare(strict_types=1);

namespace Notur;

use Composer\Autoload\ClassLoader;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
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
use Notur\Support\EntrypointResolver;
use Notur\Support\ExtensionPath;
use Notur\Support\ExtensionStateStore;
use Notur\Support\HealthCheckNormalizer;
use Notur\Support\ManifestOnlyExtension;
use Notur\Support\ThemeCompiler;
use Notur\Exceptions\ExtensionBootException;
use Notur\Exceptions\DependencyResolutionException;
use Notur\Exceptions\ExtensionNotFoundException;
use Notur\Exceptions\ManifestException;

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

    /** @var array<string, array{status: string, stage: string, message: string, exception: ?string, dependency: ?string}> */
    private array $bootFailures = [];

    private bool $booted = false;
    private FeatureRegistry $featureRegistry;

    public function __construct(
        private readonly Application $app,
        private readonly DependencyResolver $resolver,
        private readonly PermissionBroker $permissionBroker,
        private ?ThemeCompiler $themeCompiler = null,
        ?FeatureRegistry $featureRegistry = null,
        private readonly EntrypointResolver $entrypointResolver = new EntrypointResolver(),
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

        // The process environment still overrides cached Laravel configuration.
        if ($this->isSafeMode()) {
            $this->booted = true;
            Log::warning('[Notur] Safe mode active; extension discovery and boot skipped.');
            return;
        }

        try {
            $extensionsPath = $this->getExtensionsPath();
            $manifest = $this->stateStore()->reconcile(function (array $state): void {
                try {
                    $this->projectState($state);
                } catch (\Throwable $e) {
                    // Boot can still use authoritative JSON during a database outage.
                    Log::warning('[Notur] Extension database reconciliation failed: ' . $e->getMessage());
                }
            });
        } catch (\Throwable $e) {
            $this->recordBootFailure('@manifest', 'discovery', $e);
            $this->booted = true;
            return;
        }

        if (empty($manifest['extensions'])) {
            $this->booted = true;
            return;
        }

        // Read manifests before registering anything so dependency failures cannot
        // leave a partially booted extension set.
        $graph = [];
        $enabledExtensions = [];

        foreach ($manifest['extensions'] as $id => $entry) {
            if (!is_string($id)) {
                $this->recordBootFailure((string) $id, 'manifest', new \UnexpectedValueException('Master manifest extension ID must be a string.'));
                continue;
            }
            if (!is_array($entry)) {
                $this->recordBootFailure($id, 'manifest', new \UnexpectedValueException('Master manifest extension entry must be an object.'));
                continue;
            }
            if (!($entry['enabled'] ?? false)) {
                continue;
            }

            $extPath = $extensionsPath . '/' . str_replace('/', DIRECTORY_SEPARATOR, $id);

            try {
                $extManifest = ExtensionManifest::load($extPath);
            } catch (\Throwable $e) {
                $this->recordBootFailure($id, 'manifest', $e);
                continue;
            }

            try {
                $dependencies = $extManifest->getDependencies();
                if (!is_array($dependencies)) {
                    throw new \UnexpectedValueException('Manifest dependencies must be a map.');
                }
                $graph[$id] = array_keys($dependencies);
            } catch (\Throwable $e) {
                $this->recordBootFailure($id, 'dependency discovery', $e);
                continue;
            }

            $this->manifests[$id] = $extManifest;
            $enabledExtensions[$id] = $extPath;
        }

        // Resolve load order
        $loadOrder = $this->resolveLoadOrder($graph);

        // Register autoloading and boot each extension
        foreach ($loadOrder as $id) {
            if (!isset($enabledExtensions[$id])) {
                continue;
            }

            $extPath = $enabledExtensions[$id];
            $extManifest = $this->manifests[$id];

            // A dependent must not run against a dependency that failed in this boot.
            $failedDependency = null;
            foreach ($graph[$id] as $dependency) {
                if (isset($this->bootFailures[$dependency])) {
                    $failedDependency = $dependency;
                    break;
                }
            }
            if ($failedDependency !== null) {
                $this->recordSkippedDependency($id, $failedDependency);
                continue;
            }

            try {
                $this->dependencyValidator()->validateRequirements($id, $manifest['extensions'], $this->manifests);
            } catch (\Throwable $e) {
                $this->recordBootFailure($id, 'dependency validation', $e);
                continue;
            }

            $stage = 'autoload';
            try {
                $psr4 = $this->resolveAutoloadPsr4($extManifest, $extPath);
                $this->registerAutoloading($psr4, $extPath);
                $this->bootExtension($id, $extManifest, $extPath, $psr4, $stage);
            } catch (\Throwable $e) {
                // These are local registries only. Laravel/container callbacks, routes,
                // listeners and arbitrary extension side effects cannot be rolled back.
                unset($this->frontendSlots[$id], $this->healthCheckProviders[$id]);
                $this->permissionBroker->unregister($id);
                $this->recordBootFailure($id, $stage, $e);
            }
        }

        $this->booted = true;

        if ($this->extensions !== []) {
            Log::info('[Notur] Booted ' . count($this->extensions) . ' extension(s)');
        }
    }

    /**
     * @return array<string, array{status: string, stage: string, message: string, exception: ?string, dependency: ?string}>
     */
    public function getBootFailures(): array
    {
        return $this->bootFailures;
    }

    public function isSafeMode(): bool
    {
        $override = getenv('NOTUR_SAFE_MODE');
        return filter_var($override !== false ? $override : config('notur.safe_mode', false), FILTER_VALIDATE_BOOLEAN);
    }

    /** @param array<string, array<string>> $graph
     *  @return array<string>
     */
    private function resolveLoadOrder(array $graph): array
    {
        // Missing or unreadable dependencies are checked when each extension is
        // reached. They must not make sorting fail for an unrelated extension.
        $availableGraph = [];
        foreach ($graph as $id => $dependencies) {
            $availableGraph[$id] = array_values(array_filter(
                $dependencies,
                static fn (string $dependency): bool => isset($graph[$dependency]),
            ));
        }

        $cycleNodes = [];
        foreach ($availableGraph as $id => $dependencies) {
            foreach ($dependencies as $dependency) {
                if ($this->canReachDependency($dependency, $id, $availableGraph)) {
                    $cycleNodes[$id] = true;
                    break;
                }
            }
        }

        foreach (array_keys($cycleNodes) as $id) {
            $this->recordBootFailure(
                $id,
                'dependency resolution',
                new DependencyResolutionException(
                    "Circular dependency detected involving extension '{$id}'. Remove one dependency in the cycle."
                ),
            );
        }

        $sortableGraph = [];
        foreach ($availableGraph as $id => $dependencies) {
            if (isset($cycleNodes[$id])) {
                continue;
            }
            $sortableGraph[$id] = array_values(array_filter(
                $dependencies,
                static fn (string $dependency): bool => !isset($cycleNodes[$dependency]),
            ));
        }

        return $this->resolver->resolve($sortableGraph);
    }

    /** @param array<string, array<string>> $graph */
    private function canReachDependency(string $start, string $target, array $graph): bool
    {
        $pending = [$start];
        $visited = [];
        while ($pending !== []) {
            $node = array_pop($pending);
            if ($node === $target) {
                return true;
            }
            if (isset($visited[$node])) {
                continue;
            }
            $visited[$node] = true;
            foreach ($graph[$node] ?? [] as $dependency) {
                $pending[] = $dependency;
            }
        }

        return false;
    }

    private function recordBootFailure(string $id, string $stage, \Throwable $e): void
    {
        $this->bootFailures[$id] = [
            'status' => 'failed',
            'stage' => $stage,
            'message' => $e->getMessage(),
            'exception' => $e::class,
            'dependency' => null,
        ];
        Log::error("[Notur] Extension '{$id}' failed during {$stage}: {$e->getMessage()}", ['exception' => $e]);
    }

    private function recordSkippedDependency(string $id, string $dependency): void
    {
        $message = "Required extension '{$dependency}' failed to boot.";
        $this->bootFailures[$id] = [
            'status' => 'skipped',
            'stage' => 'dependency',
            'message' => $message,
            'exception' => null,
            'dependency' => $dependency,
        ];
        Log::warning("[Notur] Skipping extension '{$id}': {$message}");
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

    private function bootExtension(string $id, ExtensionManifest $manifest, string $extPath, array $psr4, string &$stage): void
    {
        $stage = 'entrypoint';
        $entrypoint = $this->entrypointResolver->resolve($manifest, $extPath, $psr4);
        if (!$entrypoint) {
            $extension = new ManifestOnlyExtension($manifest, $extPath);
        } else {
            if (!class_exists($entrypoint)) {
                throw new ExtensionBootException("Extension '{$id}' entrypoint '{$entrypoint}' was not found.");
            }

            /** @var ExtensionInterface $extension */
            $extension = $this->app->make($entrypoint);
        }

        if (!$extension instanceof ExtensionInterface) {
            throw new ExtensionBootException(
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
        $stage = 'register';
        $extension->register();

        // Feature registration (post-register, pre-boot)
        $stage = 'feature registration';
        $this->featureRegistry->register($context);

        // Register commands
        $stage = 'service registration';
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

        // Collect frontend slots (deprecated - use frontend createExtension() instead)
        $slots = [];
        if ($extension instanceof HasFrontendSlots) {
            @trigger_error(
                "Extension '{$id}' uses deprecated HasFrontendSlots interface. Define slots in frontend code via createExtension({ slots: [...] }) instead.",
                E_USER_DEPRECATED
            );
            $slots = $extension->getFrontendSlots();
        } else {
            // getFrontendSlots() already triggers deprecation if slots exist
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
        $stage = 'boot';
        $extension->boot();

        // Feature boot (post-extension boot)
        $stage = 'feature boot';
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
        $this->stateStore()->update(function (array $state) use ($id, $enabled): array {
            if (!isset($state['extensions'][$id])) {
                throw new ExtensionNotFoundException($id, "Extension '{$id}' is not installed.");
            }
            $state['extensions'][$id]['enabled'] = $enabled;
            if ($enabled) {
                $this->validateProposedState($state['extensions']);
            } else {
                $this->assertNoActiveDependents($id, $state['extensions']);
            }
            return $state;
        }, fn (array $state) => $this->projectState($state));

        Log::info("[Notur] Extension '{$id}' " . ($enabled ? 'enabled' : 'disabled'));
    }

    /**
     * Register an extension in the master manifest.
     */
    public function registerExtension(string $id, string $version, ?ExtensionManifest $extensionManifest = null, bool $enabled = true): void
    {
        $this->stateStore()->update(function (array $state) use ($id, $version, $extensionManifest, $enabled): array {
            if ($extensionManifest !== null && ($extensionManifest->getId() !== $id || $extensionManifest->getVersion() !== $version)) {
                throw new DependencyResolutionException(
                    "Extension '{$id}' manifest does not match the ID and version being registered."
                );
            }

            $existing = $state['extensions'][$id] ?? null;
            $state['extensions'][$id] = [
                'version' => $version,
                'enabled' => $enabled,
                'installed_at' => $existing['installed_at'] ?? now()->toIso8601String(),
            ];
            if ($extensionManifest !== null) {
                $this->validateProposedState($state['extensions'], [$id => $extensionManifest]);
            }
            return $state;
        }, fn (array $state) => $this->projectState($state, $id, $extensionManifest));
    }

    /**
     * Unregister an extension from the master manifest.
     */
    public function unregisterExtension(string $id): void
    {
        $this->stateStore()->update(function (array $state) use ($id): array {
            $this->assertNoActiveDependents($id, $state['extensions']);
            unset($state['extensions'][$id]);
            return $state;
        }, fn (array $state) => $this->projectState($state));
    }

    /**
     * Retry the database projection after a process stops between JSON and DB commits.
     * The boot manifest is the source of truth for membership, version and enabled state.
     *
     * @return array<string, mixed>|null
     */
    public function reconcileState(): ?array
    {
        return $this->stateStore()->reconcile(fn (array $state) => $this->projectState($state));
    }

    /**
     * Read the authoritative installed entry without reconciling the database.
     *
     * @return array<string, mixed>|null
     */
    public function getInstalledState(string $id): ?array
    {
        return $this->stateStore()->getEntry($id);
    }

    private function stateStore(): ExtensionStateStore
    {
        return new ExtensionStateStore($this->getManifestPath());
    }

    /** @param array<string, mixed> $state */
    private function projectState(array $state, ?string $registeredId = null, ?ExtensionManifest $registration = null): void
    {
        if (!Schema::hasTable('notur_extensions')) {
            return;
        }

        DB::transaction(function () use ($state, $registeredId, $registration): void {
            foreach ($state['extensions'] as $id => $entry) {
                $record = InstalledExtension::where('extension_id', $id)->first();
                $manifest = $id === $registeredId ? $registration : null;
                if ($manifest === null) {
                    try {
                        $path = $this->getExtensionsPath() . '/' . str_replace('/', DIRECTORY_SEPARATOR, $id);
                        $manifest = ExtensionManifest::load($path);
                    } catch (\Throwable) {
                        // Broken or missing YAML must not block disabling an extension.
                        // Preserve existing metadata until its files can be repaired.
                    }
                }

                if ($manifest !== null && ($manifest->getId() !== $id || $manifest->getVersion() !== $entry['version'])) {
                    // An install can stage live files before committing its state.
                    // Do not project that uncommitted version's metadata during boot.
                    Log::warning("[Notur] Skipping metadata projection for '{$id}': files do not match committed extension state.");
                    $manifest = null;
                }

                if ($record === null) {
                    InstalledExtension::create([
                        'extension_id' => $id,
                        'name' => $manifest?->getName() ?? $id,
                        'version' => $entry['version'],
                        'enabled' => $entry['enabled'],
                        'manifest' => $manifest?->getRaw(),
                    ]);
                } else {
                    $values = ['version' => $entry['version'], 'enabled' => $entry['enabled']];
                    if ($manifest !== null) {
                        $values['name'] = $manifest->getName();
                        $values['manifest'] = $manifest->getRaw();
                    }
                    $record->fill($values);
                    if ($record->isDirty()) {
                        $record->save();
                    }
                }
            }

            $ids = array_keys($state['extensions']);
            $stale = InstalledExtension::query();
            if ($ids !== []) {
                $stale->whereNotIn('extension_id', $ids);
            }
            if ($stale->exists()) {
                $stale->delete();
            }
        });
    }

    /** Check a candidate before replacing files or applying migrations. */
    public function assertCanInstall(ExtensionManifest $candidate): void
    {
        $id = $candidate->getId();
        $entries = $this->readMasterManifest()['extensions'];
        $entries[$id] = [
            'version' => $candidate->getVersion(),
            'enabled' => $entries[$id]['enabled'] ?? true,
        ];
        $this->validateProposedState($entries, [$id => $candidate]);
    }

    /** Check removal before any files, migrations, or records are changed. */
    public function assertCanRemove(string $id): void
    {
        $entries = $this->readMasterManifest()['extensions'];
        $this->assertNoActiveDependents($id, $entries);
    }

    /** The master manifest is authoritative for the state used at boot. */
    public function isConfiguredEnabled(string $id): bool
    {
        return (bool) ($this->readMasterManifest()['extensions'][$id]['enabled'] ?? false);
    }

    /**
     * Only a dependent of the target can be broken by disabling or removing it.
     * Ignore other invalid entries so administrators can repair them one at a time.
     *
     * @param array<string, array<string, mixed>> $entries
     */
    private function assertNoActiveDependents(string $id, array $entries): void
    {
        foreach ($entries as $dependentId => $entry) {
            if ($dependentId === $id || !($entry['enabled'] ?? false)) {
                continue;
            }

            try {
                $manifest = ExtensionManifest::load(
                    $this->getExtensionsPath() . '/' . str_replace('/', DIRECTORY_SEPARATOR, $dependentId)
                );
            } catch (ManifestException | \Symfony\Component\Yaml\Exception\ParseException) {
                // An unreadable extension cannot declare a known reverse dependency.
                continue;
            }

            if ($manifest->getId() !== $dependentId) {
                continue;
            }

            if (array_key_exists($id, $manifest->getDependencies())) {
                throw new DependencyResolutionException(
                    "Extension '{$dependentId}' requires '{$id}'. Disable or remove '{$dependentId}' first."
                );
            }
        }
    }

    /** @return array{extensions: array<string, array<string, mixed>>} */
    private function readMasterManifest(): array
    {
        $path = $this->getManifestPath();
        $manifest = file_exists($path) ? json_decode((string) file_get_contents($path), true) : null;
        return is_array($manifest) && is_array($manifest['extensions'] ?? null)
            ? $manifest
            : ['extensions' => []];
    }

    /**
     * @param array<string, array<string, mixed>> $entries
     * @param array<string, ExtensionManifest> $overrides
     */
    private function validateProposedState(array $entries, array $overrides = []): void
    {
        $manifests = $overrides;
        foreach ($entries as $id => $entry) {
            if (!($entry['enabled'] ?? false) || isset($manifests[$id])) {
                continue;
            }

            try {
                $manifests[$id] = ExtensionManifest::load(
                    $this->getExtensionsPath() . '/' . str_replace('/', DIRECTORY_SEPARATOR, $id)
                );
            } catch (ManifestException $e) {
                throw new DependencyResolutionException(
                    "Enabled extension '{$id}' has no readable manifest. Restore its files or disable it. {$e->getMessage()}"
                );
            }
        }

        $this->dependencyValidator()->validate($entries, $manifests);
    }

    private function dependencyValidator(): ExtensionDependencyValidator
    {
        return new ExtensionDependencyValidator($this->resolver);
    }

    /**
     * Get the path where extensions are stored.
     */
    public function getExtensionsPath(): string
    {
        return ExtensionPath::extensionsDir();
    }

    /**
     * Get the path to the master manifest file.
     */
    public function getManifestPath(): string
    {
        return ExtensionPath::manifest();
    }

    /**
     * Get the public path for extension frontend assets.
     */
    public function getPublicPath(): string
    {
        return ExtensionPath::publicExtensionsDir();
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

    private function toStudly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $value)));
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
