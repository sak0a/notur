<?php

declare(strict_types=1);

namespace Notur\Console\Commands;

use Illuminate\Console\Command;
use Notur\Support\PackageManagerResolver;

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

        $namespace = $this->toNamespace($vendor) . '\\' . $this->toClassName($name);
        $classBase = $this->toClassName($name);
        $className = str_ends_with($classBase, 'Extension') ? $classBase : $classBase . 'Extension';
        $viewNamespace = $this->toViewNamespace($vendor, $name);

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

        $this->createDirectories($basePath, $context);

        $this->writeManifest($basePath, $context);
        $this->writePhpClass($basePath, $context);

        if ($context['includeApiRoutes']) {
            $this->writeApiRoute($basePath, $context);
            $this->writeApiController($basePath, $context);
        }

        if ($context['includeAdminRoutes']) {
            $this->writeAdminRoute($basePath, $context);
        }

        if ($context['includeAdminRoutes'] || $context['includeAdmin']) {
            $this->writeAdminController($basePath, $context);
        }

        if ($context['includeFrontend']) {
            $this->writeFrontendIndex($basePath, $context);
            $this->writeFrontendPackageJson($basePath, $context);
            $this->writeFrontendTsConfig($basePath);
            $this->writeFrontendWebpackConfig($basePath, $context);
        }

        if ($context['includeMigrations']) {
            $this->writeMigration($basePath, $context);
        }

        if ($context['includeAdmin']) {
            $this->writeAdminView($basePath, $context);
        }

        if ($context['includeTests']) {
            $this->writeComposerJson($basePath, $context);
            $this->writePhpunitXml($basePath, $context);
            $this->writePhpunitTest($basePath, $context);
        }

        $packageManager = $this->detectPackageManager($basePath);

        $this->writeReadme($basePath, $context, $packageManager);
        $this->writeGitignore($basePath);

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
        $displayName = $this->toDisplayName($name);
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

    private function createDirectories(string $basePath, array $context): void
    {
        $directories = [
            $basePath,
            $basePath . '/src',
        ];

        if ($context['includeApiRoutes'] || $context['includeAdminRoutes']) {
            $directories[] = $basePath . '/src/routes';
        }

        if ($context['includeApiRoutes'] || $context['includeAdminRoutes'] || $context['includeAdmin']) {
            $directories[] = $basePath . '/src/Http/Controllers';
        }

        if ($context['includeMigrations']) {
            $directories[] = $basePath . '/database/migrations';
        }

        if ($context['includeFrontend']) {
            $directories[] = $basePath . '/resources/frontend/src';
            $directories[] = $basePath . '/resources/frontend/dist';
        }

        if ($context['includeAdmin']) {
            $directories[] = $basePath . '/resources/views/admin';
        }

        if ($context['includeTests']) {
            $directories[] = $basePath . '/tests/Unit';
        }

        foreach ($directories as $dir) {
            mkdir($dir, 0755, true);
        }
    }

    private function writeManifest(string $basePath, array $context): void
    {
        $lines = [];
        $lines[] = 'notur: "1.0"';
        $lines[] = 'id: ' . $this->yamlString($context['id']);
        $lines[] = 'name: ' . $this->yamlString($context['displayName']);
        $lines[] = 'version: "1.0.0"';
        $lines[] = 'description: ' . $this->yamlString($context['description']);

        if ($context['authorName'] !== '') {
            $lines[] = 'authors:';
            $lines[] = '  - name: ' . $this->yamlString($context['authorName']);
            if ($context['authorEmail'] !== '') {
                $lines[] = '    email: ' . $this->yamlString($context['authorEmail']);
            }
        }

        $lines[] = 'license: ' . $this->yamlString($context['license']);
        $lines[] = '';
        $lines[] = 'requires:';
        $lines[] = '  notur: "^1.0"';
        $lines[] = '  pterodactyl: "^1.11"';
        $lines[] = '  php: "^8.2"';

        file_put_contents($basePath . '/extension.yaml', implode("\n", $lines) . "\n");
    }

    private function writePhpClass(string $basePath, array $context): void
    {
        // Build the list of interfaces to implement (beyond NoturExtension base class)
        $interfaces = [];
        $uses = [
            'Notur\\Support\\NoturExtension',
        ];

        if ($context['includeApiRoutes'] || $context['includeAdminRoutes']) {
            $interfaces[] = 'HasRoutes';
            $uses[] = 'Notur\\Contracts\\HasRoutes';
        }

        if ($context['includeMigrations']) {
            $interfaces[] = 'HasMigrations';
            $uses[] = 'Notur\\Contracts\\HasMigrations';
        }

        if ($context['includeAdmin']) {
            $interfaces[] = 'HasBladeViews';
            $uses[] = 'Notur\\Contracts\\HasBladeViews';
        }

        // Note: HasFrontendSlots is deprecated - slots should be registered in frontend code

        $content = "<?php\n\n";
        $content .= "declare(strict_types=1);\n\n";
        $content .= "namespace {$context['namespace']};\n\n";

        foreach ($uses as $use) {
            $content .= "use {$use};\n";
        }

        $content .= "\n";

        // Build class declaration
        $classDeclaration = 'class ' . $context['className'] . ' extends NoturExtension';
        if (!empty($interfaces)) {
            $classDeclaration .= ' implements ' . implode(', ', $interfaces);
        }

        $content .= $classDeclaration . "\n";
        $content .= "{\n";

        // Only include methods that are needed - getId/getName/getVersion/getBasePath are inherited from NoturExtension

        $hasCustomMethods = false;

        if ($context['includeApiRoutes'] || $context['includeAdminRoutes']) {
            $hasCustomMethods = true;
            $content .= "    public function getRouteFiles(): array\n";
            $content .= "    {\n";
            $content .= "        return [\n";
            if ($context['includeApiRoutes']) {
                $content .= "            'api-client' => 'src/routes/api-client.php',\n";
            }
            if ($context['includeAdminRoutes']) {
                $content .= "            'admin' => 'src/routes/admin.php',\n";
            }
            $content .= "        ];\n";
            $content .= "    }\n";
        }

        if ($context['includeMigrations']) {
            if ($hasCustomMethods) {
                $content .= "\n";
            }
            $hasCustomMethods = true;
            $content .= "    public function getMigrationsPath(): string\n";
            $content .= "    {\n";
            $content .= "        return \$this->getBasePath() . '/database/migrations';\n";
            $content .= "    }\n";
        }

        if ($context['includeAdmin']) {
            if ($hasCustomMethods) {
                $content .= "\n";
            }
            $hasCustomMethods = true;
            $content .= "    public function getViewsPath(): string\n";
            $content .= "    {\n";
            $content .= "        return \$this->getBasePath() . '/resources/views';\n";
            $content .= "    }\n\n";
            $content .= "    public function getViewNamespace(): string\n";
            $content .= "    {\n";
            $content .= "        return '{$context['viewNamespace']}';\n";
            $content .= "    }\n";
        }

        // If no custom methods, add a comment explaining the class
        if (!$hasCustomMethods) {
            $content .= "    // Metadata (id, name, version) is read from extension.yaml automatically.\n";
            $content .= "    // Override register() or boot() to add custom initialization logic.\n";
        }

        $content .= "}\n";

        file_put_contents($basePath . '/src/' . $context['className'] . '.php', $content);
    }

    private function writeApiRoute(string $basePath, array $context): void
    {
        $content = <<<PHP
<?php

use Illuminate\Support\Facades\Route;
use {$context['namespace']}\Http\Controllers\ApiController;

Route::get('/ping', [ApiController::class, 'ping']);
PHP;

        file_put_contents($basePath . '/src/routes/api-client.php', $content . "\n");
    }

    private function writeAdminRoute(string $basePath, array $context): void
    {
        $content = <<<PHP
<?php

use Illuminate\Support\Facades\Route;
use {$context['namespace']}\Http\Controllers\AdminController;

Route::get('/', [AdminController::class, 'index']);
PHP;

        file_put_contents($basePath . '/src/routes/admin.php', $content . "\n");
    }

    private function writeApiController(string $basePath, array $context): void
    {
        $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$context['namespace']}\Http\Controllers;

use Illuminate\Http\JsonResponse;

class ApiController
{
    public function ping(): JsonResponse
    {
        return response()->json([
            'message' => '{$context['displayName']} is alive.',
        ]);
    }
}
PHP;

        file_put_contents($basePath . '/src/Http/Controllers/ApiController.php', $content . "\n");
    }

    private function writeAdminController(string $basePath, array $context): void
    {
        if ($context['includeAdmin']) {
            $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$context['namespace']}\Http\Controllers;

use Illuminate\View\View;

class AdminController
{
    public function index(): View
    {
        return view('{$context['viewNamespace']}::admin.index', [
            'extensionId' => '{$context['id']}',
            'extensionName' => '{$context['displayName']}',
        ]);
    }
}
PHP;
        } else {
            $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$context['namespace']}\Http\Controllers;

use Illuminate\Http\JsonResponse;

class AdminController
{
    public function index(): JsonResponse
    {
        return response()->json([
            'message' => '{$context['displayName']} admin endpoint is ready.',
        ]);
    }
}
PHP;
        }

        file_put_contents($basePath . '/src/Http/Controllers/AdminController.php', $content . "\n");
    }

    private function writeFrontendIndex(string $basePath, array $context): void
    {
        $stubContent = $this->renderStub('frontend-index.tsx.stub', [
            'id' => $context['id'],
            'displayName' => $context['displayName'],
        ]);
        if ($stubContent !== null) {
            file_put_contents($basePath . '/resources/frontend/src/index.tsx', $stubContent . "\n");
            return;
        }

        $content = <<<TSX
import * as React from 'react';
import { createExtension } from '@notur/sdk';

const ExampleWidget: React.FC<{ extensionId: string }> = () => {
    return (
        <div
            style={{
                padding: '1rem',
                background: 'var(--notur-bg-secondary)',
                border: '1px solid var(--notur-border)',
                borderRadius: 'var(--notur-radius-md)',
            }}
        >
            <h3 style={{ color: 'var(--notur-text-primary)' }}>
                {$context['displayName']}
            </h3>
            <p style={{ color: 'var(--notur-text-secondary)' }}>
                Hello from {$context['id']}!
            </p>
        </div>
    );
};

// Register extension with slots - name/version are auto-resolved from extension.yaml
createExtension({
    id: '{$context['id']}',
    slots: [
        {
            slot: 'dashboard.widgets',
            component: ExampleWidget,
            order: 100,
        },
    ],
});
TSX;

        file_put_contents($basePath . '/resources/frontend/src/index.tsx', $content . "\n");
    }

    private function writeFrontendPackageJson(string $basePath, array $context): void
    {
        $stubContent = $this->renderStub('frontend-package.json.stub', [
            'id' => $context['id'],
        ]);
        if ($stubContent !== null) {
            file_put_contents($basePath . '/package.json', $stubContent . "\n");
            return;
        }

        $content = json_encode([
            'name' => $context['id'],
            'version' => '1.0.0',
            'private' => true,
            'scripts' => [
                'build' => 'webpack-cli --mode production --config webpack.config.js',
                'dev' => 'webpack-cli --mode development --watch --config webpack.config.js',
            ],
            'peerDependencies' => [
                'react' => '^16.14.0',
                'react-dom' => '^16.14.0',
            ],
            'devDependencies' => [
                '@notur/sdk' => '^1.2.0',
                '@types/react' => '^16.14.0',
                '@types/react-dom' => '^16.9.0',
                'css-loader' => '^7.1.2',
                'react' => '^16.14.0',
                'react-dom' => '^16.14.0',
                'style-loader' => '^4.0.0',
                'ts-loader' => '^9.5.0',
                'typescript' => '^5.3.0',
                'webpack' => '^5.90.0',
                'webpack-cli' => '^6.0.0',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        file_put_contents($basePath . '/package.json', $content . "\n");
    }

    private function writeFrontendTsConfig(string $basePath): void
    {
        $content = json_encode([
            'compilerOptions' => [
                'target' => 'ES2018',
                'module' => 'ESNext',
                'lib' => ['DOM', 'ES2018'],
                'jsx' => 'react',
                'strict' => true,
                'esModuleInterop' => true,
                'moduleResolution' => 'node',
                'outDir' => './resources/frontend/dist',
                'rootDir' => './resources/frontend/src',
                'sourceMap' => true,
                'skipLibCheck' => true,
            ],
            'include' => ['resources/frontend/src/**/*'],
            'exclude' => ['node_modules', 'resources/frontend/dist'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        file_put_contents($basePath . '/tsconfig.json', $content . "\n");
    }

    private function writeFrontendWebpackConfig(string $basePath, array $context): void
    {
        $libraryName = $this->toClassName($context['name']);

        $stubContent = $this->renderStub('webpack.config.js.stub', [
            'libraryName' => $libraryName,
        ]);
        if ($stubContent !== null) {
            file_put_contents($basePath . '/webpack.config.js', $stubContent . "\n");
            return;
        }

        $content = <<<JS
const path = require('path');
const base = require('@notur/sdk/webpack.extension.config');

module.exports = {
    ...base,
    entry: './resources/frontend/src/index.tsx',
    output: {
        ...base.output,
        filename: 'extension.js',
        path: path.resolve(__dirname, 'resources/frontend/dist'),
        library: {
            ...base.output.library,
            name: '__NOTUR_EXT_{$libraryName}__',
            type: 'umd',
        },
    },
};
JS;

        file_put_contents($basePath . '/webpack.config.js', $content . "\n");
    }

    private function writeMigration(string $basePath, array $context): void
    {
        $table = str_replace('-', '_', $context['vendor'] . '_' . $context['name']);
        $timestamp = date('Y_m_d_His');
        $filename = $timestamp . '_create_' . $table . '_table.php';

        $content = <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$table}', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$table}');
    }
};
PHP;

        file_put_contents($basePath . '/database/migrations/' . $filename, $content . "\n");
    }

    private function writeAdminView(string $basePath, array $context): void
    {
        $content = <<<BLADE
@extends('layouts.admin')

@section('title')
    {$context['displayName']}
@endsection

@section('content-header')
    <h1>{$context['displayName']}<small>Admin</small></h1>
@endsection

@section('content')
    <div class="box box-primary">
        <div class="box-body">
            <p>Welcome to {$context['displayName']}.</p>
        </div>
    </div>
@endsection
BLADE;

        file_put_contents($basePath . '/resources/views/admin/index.blade.php', $content . "\n");
    }

    private function writeComposerJson(string $basePath, array $context): void
    {
        $content = json_encode([
            'name' => $context['id'],
            'description' => $context['description'],
            'type' => 'library',
            'license' => $context['license'],
            'require' => [
                'php' => '^8.2',
            ],
            'require-dev' => [
                'notur/notur' => '^1.0',
                'phpunit/phpunit' => '^11.0',
            ],
            'autoload' => [
                'psr-4' => [
                    $context['namespace'] . '\\' => 'src/',
                ],
            ],
            'autoload-dev' => [
                'psr-4' => [
                    $context['namespace'] . '\\Tests\\' => 'tests/',
                ],
            ],
            'minimum-stability' => 'stable',
            'prefer-stable' => true,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        file_put_contents($basePath . '/composer.json', $content . "\n");
    }

    private function writePhpunitXml(string $basePath, array $context): void
    {
        $content = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php" colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
    </testsuites>
</phpunit>
XML;

        file_put_contents($basePath . '/phpunit.xml', $content . "\n");
    }

    private function writePhpunitTest(string $basePath, array $context): void
    {
        $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$context['namespace']}\Tests\Unit;

use {$context['namespace']}\\{$context['className']};
use PHPUnit\Framework\TestCase;

class ExtensionTest extends TestCase
{
    public function test_extension_metadata(): void
    {
        $extension = new {$context['className']}();

        $this->assertSame('{$context['id']}', $extension->getId());
        $this->assertSame('{$context['displayName']}', $extension->getName());
        $this->assertSame('1.0.0', $extension->getVersion());
    }
}
PHP;

        file_put_contents($basePath . '/tests/Unit/ExtensionTest.php', $content . "\n");
    }

    private function writeReadme(string $basePath, array $context, string $packageManager): void
    {
        $lines = [];
        $lines[] = '# ' . $context['displayName'];
        $lines[] = '';
        $lines[] = $context['description'];
        $lines[] = '';
        $lines[] = '## Local Development';
        $lines[] = '';
        $lines[] = "```bash";
        $lines[] = 'php artisan notur:dev /path/to/extension';
        $lines[] = "```";

        $lines[] = '';
        $lines[] = '## Export (Optional)';
        $lines[] = '';
        $lines[] = "```bash";
        $lines[] = 'php artisan notur:export /path/to/extension';
        $lines[] = "```";

        if ($context['includeFrontend']) {
            $lines[] = '';
            $lines[] = '## Frontend Development';
            $lines[] = '';
            $lines[] = "```bash";
            $lines[] = $this->frontendInstallCommand($packageManager);
            $lines[] = $this->frontendRunScriptCommand($packageManager, 'dev');
            $lines[] = "```";
        }

        if ($context['includeTests']) {
            $lines[] = '';
            $lines[] = '## Tests';
            $lines[] = '';
            $lines[] = "```bash";
            $lines[] = 'composer install';
            $lines[] = './vendor/bin/phpunit';
            $lines[] = "```";
        }

        $lines[] = '';
        $lines[] = '## Structure';
        $lines[] = '';
        $lines[] = '- `extension.yaml` - Extension manifest';
        $lines[] = '- `src/` - PHP backend code';

        if ($context['includeFrontend']) {
            $lines[] = '- `resources/frontend/` - Frontend source and bundle';
        }

        if ($context['includeMigrations']) {
            $lines[] = '- `database/migrations/` - Database migrations';
        }

        if ($context['includeAdmin']) {
            $lines[] = '- `resources/views/` - Blade views for admin UI';
        }

        if ($context['includeTests']) {
            $lines[] = '- `tests/` - PHPUnit tests';
        }

        file_put_contents($basePath . '/README.md', implode("\n", $lines) . "\n");
    }

    private function writeGitignore(string $basePath): void
    {
        $content = <<<TXT
/node_modules
/vendor
.phpunit.result.cache
.DS_Store
TXT;

        file_put_contents($basePath . '/.gitignore', $content . "\n");
    }

    private function yamlString(string $value): string
    {
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
        return '"' . $escaped . '"';
    }

    private function toClassName(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $name)));
    }

    private function toNamespace(string $vendor): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $vendor)));
    }

    private function toDisplayName(string $name): string
    {
        return ucwords(str_replace('-', ' ', $name));
    }

    private function toViewNamespace(string $vendor, string $name): string
    {
        return $vendor . '-' . $name;
    }

    private function renderStub(string $filename, array $variables): ?string
    {
        $stubPath = dirname(__DIR__, 3) . '/resources/stubs/new-extension/' . $filename;
        if (!is_file($stubPath)) {
            return null;
        }

        $content = file_get_contents($stubPath);
        if (!is_string($content)) {
            return null;
        }

        $replacements = [];
        foreach ($variables as $key => $value) {
            $replacements['{{' . $key . '}}'] = (string) $value;
        }

        return strtr($content, $replacements);
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
