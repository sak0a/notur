<?php

declare(strict_types=1);

namespace Notur\Console\Commands;

use Illuminate\Console\Command;
use Notur\Support\PackageManagerResolver;
use Notur\Support\ScaffoldGenerator;

class NewCommand extends Command
{
    protected $signature = 'notur:new
        {id : The extension ID in vendor/name format (e.g. acme/hello-world)}
        {--path= : Directory to create the extension in (defaults to current directory)}
        {--preset= : Preset: minimal, backend, standard, or full (default: standard)}
        {--with-api-routes : Include API routes (api-client)}
        {--no-api-routes : Skip API routes (api-client)}
        {--with-admin-routes : Include admin routes (admin)}
        {--no-admin-routes : Skip admin routes (admin)}
        {--with-frontend : Include a frontend scaffold (React + Notur SDK)}
        {--no-frontend : Skip frontend scaffold}
        {--with-admin : Include an admin UI scaffold (Blade view)}
        {--no-admin : Skip admin UI scaffold}
        {--with-migrations : Include database migrations}
        {--no-migrations : Skip migrations}
        {--with-tests : Include PHPUnit tests + composer.json}
        {--no-tests : Skip tests}';

    protected $description = 'Scaffold a new Notur extension with optional frontend, admin UI, migrations, and tests';

    public function handle(): int
    {
        $id = $this->argument('id');

        if (!preg_match('/^[a-z0-9-]+\/[a-z0-9-]+$/', $id)) {
            $this->error('Extension ID must be in vendor/name format using lowercase alphanumeric characters and hyphens.');
            return 1;
        }

        [$vendor, $name] = explode('/', $id);
        $basePath = rtrim($this->option('path') ?: getcwd(), '/') . '/' . $name;

        if (is_dir($basePath)) {
            $this->error("Directory already exists: {$basePath}");
            return 1;
        }

        $features = $this->resolveFeatures();
        if ($features === null) {
            return 1;
        }

        $metadata = $this->resolveMetadata($name);

        $namespace = ScaffoldGenerator::toNamespace($vendor) . '\\' . ScaffoldGenerator::toClassName($name);
        $classBase = ScaffoldGenerator::toClassName($name);
        $className = str_ends_with($classBase, 'Extension') ? $classBase : $classBase . 'Extension';
        $viewNamespace = ScaffoldGenerator::toViewNamespace($vendor, $name);

        $includeApiRoutes = $features['apiRoutes'];
        $includeAdminRoutes = $features['adminRoutes'];

        $context = [
            'id' => $id,
            'vendor' => $vendor,
            'name' => $name,
            'displayName' => $metadata['displayName'],
            'description' => $metadata['description'],
            'authorName' => $metadata['authorName'],
            'authorEmail' => $metadata['authorEmail'],
            'license' => $metadata['license'],
            'namespace' => $namespace,
            'className' => $className,
            'viewNamespace' => $viewNamespace,
            'includeFrontend' => $features['frontend'],
            'includeAdmin' => $features['admin'],
            'includeMigrations' => $features['migrations'],
            'includeTests' => $features['tests'],
            'includeApiRoutes' => $includeApiRoutes,
            'includeAdminRoutes' => $includeAdminRoutes,
        ];

        $this->info("Scaffolding extension: {$id}");

        $generator = new ScaffoldGenerator($basePath, $context);
        $generator->generate();

        $packageManager = $this->detectPackageManager($basePath);

        $generator->writeReadme($packageManager);
        $generator->writeGitignore();

        $this->newLine();
        $this->info('Extension scaffolded successfully!');
        $this->newLine();
        $this->line("  <comment>Directory:</comment>  {$basePath}");
        $this->line("  <comment>Extension:</comment>  {$id}");
        $this->newLine();
        $this->line('  <info>Next steps:</info>');
        $this->line("    1. cd {$basePath}");

        $step = 2;
        if ($context['includeFrontend']) {
            $this->line("    {$step}. {$this->frontendInstallCommand($packageManager)}");
            $step++;
            $this->line("    {$step}. {$this->frontendRunScriptCommand($packageManager, 'build')}");
            $step++;
        }

        if ($context['includeTests']) {
            $this->line("    {$step}. composer install");
            $step++;
            $this->line("    {$step}. ./vendor/bin/phpunit");
            $step++;
        }

        $this->line("    {$step}. Run: php artisan notur:dev {$basePath}");
        $this->newLine();

        return 0;
    }

    private function resolveMetadata(string $name): array
    {
        $displayName = ScaffoldGenerator::toDisplayName($name);
        $description = 'A Notur extension';
        $authorName = '';
        $authorEmail = '';
        $license = 'MIT';

        if ($this->input->isInteractive()) {
            $displayName = $this->ask('Display name', $displayName);
            $description = $this->ask('Description', $description);
            $license = $this->ask('License', $license);
            $authorName = $this->ask('Author name (optional)', $authorName);
            if ($authorName !== '') {
                $authorEmail = $this->ask('Author email (optional)', $authorEmail);
            }
        }

        return [
            'displayName' => $displayName,
            'description' => $description,
            'authorName' => $authorName,
            'authorEmail' => $authorEmail,
            'license' => $license,
        ];
    }

    private function resolveFeatures(): ?array
    {
        $preset = $this->option('preset');
        $preset = $preset ? strtolower((string) $preset) : null;

        if ($preset && !in_array($preset, ['minimal', 'backend', 'standard', 'full'], true)) {
            $this->error('Invalid preset. Use: minimal, backend, standard, or full.');
            return null;
        }

        $defaultPreset = 'standard';
        $features = $this->featuresForPreset($preset ?? $defaultPreset);

        if ($this->input->isInteractive() && !$preset && !$this->hasFeatureOverrides()) {
            $presetChoice = $this->choice(
                'Scaffold preset (full includes admin UI, migrations, tests)',
                ['standard', 'backend', 'full', 'minimal'],
                0
            );

            $features = $this->featuresForPreset($presetChoice);

            $features['apiRoutes'] = $this->confirm(
                'Include API routes (api-client)?',
                $features['apiRoutes']
            );
            $features['frontend'] = $this->confirm(
                'Include frontend (React + Notur SDK)?',
                $features['frontend']
            );
            $features['adminRoutes'] = $this->confirm(
                'Include admin routes (admin)?',
                $features['adminRoutes']
            );
            $features['admin'] = $this->confirm(
                'Include admin UI (Blade view + admin route)?',
                $features['admin']
            );
            $features['migrations'] = $this->confirm(
                'Include migrations?',
                $features['migrations']
            );
            $features['tests'] = $this->confirm(
                'Include PHPUnit tests?',
                $features['tests']
            );
        }

        $features = $this->applyFeatureOverrides($features);

        if ($features['admin'] && !$features['adminRoutes']) {
            $this->warn('Admin UI scaffolded without admin routes. Add routes manually or enable admin routes.');
        }

        return $features;
    }

    private function featuresForPreset(string $preset): array
    {
        return match ($preset) {
            'minimal' => [
                'apiRoutes' => false,
                'adminRoutes' => false,
                'frontend' => false,
                'admin' => false,
                'migrations' => false,
                'tests' => false,
            ],
            'backend' => [
                'apiRoutes' => true,
                'adminRoutes' => false,
                'frontend' => false,
                'admin' => false,
                'migrations' => false,
                'tests' => false,
            ],
            'standard' => [
                'apiRoutes' => true,
                'adminRoutes' => false,
                'frontend' => true,
                'admin' => false,
                'migrations' => false,
                'tests' => false,
            ],
            default => [
                'apiRoutes' => true,
                'adminRoutes' => true,
                'frontend' => true,
                'admin' => true,
                'migrations' => true,
                'tests' => true,
            ],
        };
    }

    private function hasFeatureOverrides(): bool
    {
        return (bool) (
            $this->option('with-frontend') ||
            $this->option('no-frontend') ||
            $this->option('with-api-routes') ||
            $this->option('no-api-routes') ||
            $this->option('with-admin-routes') ||
            $this->option('no-admin-routes') ||
            $this->option('with-admin') ||
            $this->option('no-admin') ||
            $this->option('with-migrations') ||
            $this->option('no-migrations') ||
            $this->option('with-tests') ||
            $this->option('no-tests')
        );
    }

    private function applyFeatureOverrides(array $features): array
    {
        if ($this->option('with-api-routes')) {
            $features['apiRoutes'] = true;
        }
        if ($this->option('no-api-routes')) {
            $features['apiRoutes'] = false;
        }
        if ($this->option('with-admin-routes')) {
            $features['adminRoutes'] = true;
        }
        if ($this->option('no-admin-routes')) {
            $features['adminRoutes'] = false;
        }
        if ($this->option('with-frontend')) {
            $features['frontend'] = true;
        }
        if ($this->option('no-frontend')) {
            $features['frontend'] = false;
        }
        if ($this->option('with-admin')) {
            $features['admin'] = true;
        }
        if ($this->option('no-admin')) {
            $features['admin'] = false;
        }
        if ($this->option('with-migrations')) {
            $features['migrations'] = true;
        }
        if ($this->option('no-migrations')) {
            $features['migrations'] = false;
        }
        if ($this->option('with-tests')) {
            $features['tests'] = true;
        }
        if ($this->option('no-tests')) {
            $features['tests'] = false;
        }

        return $features;
    }

    private function detectPackageManager(string $basePath): string
    {
        $resolver = new PackageManagerResolver();

        return $resolver->detect($basePath) ?? 'npm';
    }

    private function frontendInstallCommand(string $manager): string
    {
        $resolver = new PackageManagerResolver();

        return $resolver->installCommand($manager);
    }

    private function frontendRunScriptCommand(string $manager, string $script): string
    {
        $resolver = new PackageManagerResolver();

        return $resolver->runScriptCommand($manager, $script);
    }
}
