<?php

declare(strict_types=1);

namespace Notur;

use Illuminate\Support\ServiceProvider;
use Notur\Features\FeatureRegistry;
use Notur\Console\Commands\BuildCommand;
use Notur\Console\Commands\DevCommand;
use Notur\Console\Commands\DisableCommand;
use Notur\Console\Commands\EnableCommand;
use Notur\Console\Commands\ExportCommand;
use Notur\Console\Commands\InstallCommand;
use Notur\Console\Commands\KeygenCommand;
use Notur\Console\Commands\ListCommand;
use Notur\Console\Commands\NewCommand;
use Notur\Console\Commands\RegistrySyncCommand;
use Notur\Console\Commands\RemoveCommand;
use Notur\Console\Commands\UninstallCommand;
use Notur\Console\Commands\UpdateCommand;
use Notur\Console\Commands\ValidateCommand;
use Notur\Support\ActivityLogger;

class NoturServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DependencyResolver::class);

        $this->app->singleton(PermissionBroker::class);

        $this->app->singleton(MigrationManager::class);

        $this->app->singleton(FeatureRegistry::class, function () {
            return FeatureRegistry::defaults();
        });

        $this->app->singleton(ExtensionManager::class, function ($app) {
            return new ExtensionManager(
                $app,
                $app->make(DependencyResolver::class),
                $app->make(PermissionBroker::class),
                $app->make(Support\ThemeCompiler::class),
                $app->make(FeatureRegistry::class),
            );
        });

        $this->app->singleton(Support\RegistryClient::class, function ($app) {
            return new Support\RegistryClient(
                client: new \GuzzleHttp\Client(),
                registryUrl: config('notur.registry_url', 'https://raw.githubusercontent.com/notur/registry/main'),
                cachePath: config('notur.registry_cache_path'),
            );
        });

        $this->app->singleton(Support\SignatureVerifier::class);

        $this->app->singleton(Support\ThemeCompiler::class);

        $this->app->singleton(ActivityLogger::class);
    }

    public function boot(): void
    {
        // Load Notur migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Load Notur views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'notur');

        // Publish config
        $this->publishes([
            __DIR__ . '/../config/notur.php' => config_path('notur.php'),
        ], 'notur-config');

        $this->mergeConfigFrom(__DIR__ . '/../config/notur.php', 'notur');

        // Register artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                RemoveCommand::class,
                EnableCommand::class,
                DisableCommand::class,
                ListCommand::class,
                UpdateCommand::class,
                DevCommand::class,
                BuildCommand::class,
                ExportCommand::class,
                KeygenCommand::class,
                RegistrySyncCommand::class,
                NewCommand::class,
                ValidateCommand::class,
                UninstallCommand::class,
            ]);
        }

        // Register routes for extension API
        $this->registerRoutes();

        // Boot extension manager
        $this->app->make(ExtensionManager::class)->boot();

        // Register activity log listeners
        $this->app->make(ActivityLogger::class)->registerListeners();

        // Share frontend data with views
        $this->shareFrontendData();
    }

    private function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        $this->loadRoutesFrom(__DIR__ . '/../routes/admin.php');
    }

    private function shareFrontendData(): void
    {
        $this->app['view']->composer('notur::scripts', function ($view) {
            $manager = $this->app->make(ExtensionManager::class);

            $extensionAssets = [];
            foreach ($manager->all() as $id => $extension) {
                $manifest = $manager->getManifest($id);
                if (!$manifest) {
                    continue;
                }

                $asset = ['id' => $id];

                if ($bundle = $manifest->getFrontendBundle()) {
                    $asset['bundle'] = "/notur/extensions/{$id}/{$bundle}";
                }

                if ($styles = $manifest->getFrontendStyles()) {
                    $asset['styles'] = "/notur/extensions/{$id}/{$styles}";
                }

                $cssIsolation = $manifest->getFrontendCssIsolation();
                if (!empty($cssIsolation)) {
                    $allowCssIsolation = $manifest->hasCapabilitiesDeclared()
                        ? $manifest->isCapabilityEnabled('css_isolation', 1)
                        : true;

                    if ($allowCssIsolation) {
                        if (isset($cssIsolation['class']) && !isset($cssIsolation['className'])) {
                            $cssIsolation['className'] = $cssIsolation['class'];
                        }
                        $asset['cssIsolation'] = $cssIsolation;
                    }
                }

                $extensionAssets[] = $asset;
            }

            $view->with('noturConfig', [
                'version' => '1.0.0',
                'slots' => $manager->getFrontendSlots(),
                'extensions' => $extensionAssets,
            ]);
        });
    }
}
