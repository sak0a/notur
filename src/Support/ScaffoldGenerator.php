<?php

declare(strict_types=1);

namespace Notur\Support;

class ScaffoldGenerator
{
    public function __construct(
        private readonly string $basePath,
        private readonly array $context,
    ) {
    }

    public function generate(): void
    {
        $this->createDirectories();

        $this->writeManifest();
        $this->writePhpClass();

        if ($this->context['includeApiRoutes']) {
            $this->writeApiRoute();
            $this->writeApiController();
        }

        if ($this->context['includeAdminRoutes']) {
            $this->writeAdminRoute();
        }

        if ($this->context['includeAdminRoutes'] || $this->context['includeAdmin']) {
            $this->writeAdminController();
        }

        if ($this->context['includeFrontend']) {
            $this->writeFrontendIndex();
            $this->writeFrontendPackageJson();
            $this->writeFrontendTsConfig();
            $this->writeFrontendWebpackConfig();
        }

        if ($this->context['includeMigrations']) {
            $this->writeMigration();
        }

        if ($this->context['includeAdmin']) {
            $this->writeAdminView();
        }

        if ($this->context['includeTests']) {
            $this->writeComposerJson();
            $this->writePhpunitXml();
            $this->writePhpunitTest();
        }
    }

    public function createDirectories(): void
    {
        $directories = [
            $this->basePath,
            $this->basePath . '/src',
        ];

        if ($this->context['includeApiRoutes'] || $this->context['includeAdminRoutes']) {
            $directories[] = $this->basePath . '/src/routes';
        }

        if ($this->context['includeApiRoutes'] || $this->context['includeAdminRoutes'] || $this->context['includeAdmin']) {
            $directories[] = $this->basePath . '/src/Http/Controllers';
        }

        if ($this->context['includeMigrations']) {
            $directories[] = $this->basePath . '/database/migrations';
        }

        if ($this->context['includeFrontend']) {
            $directories[] = $this->basePath . '/resources/frontend/src';
            $directories[] = $this->basePath . '/resources/frontend/dist';
        }

        if ($this->context['includeAdmin']) {
            $directories[] = $this->basePath . '/resources/views/admin';
        }

        if ($this->context['includeTests']) {
            $directories[] = $this->basePath . '/tests/Unit';
        }

        foreach ($directories as $dir) {
            mkdir($dir, 0755, true);
        }
    }

    public function writeManifest(): void
    {
        $lines = [];
        $lines[] = 'notur: "1.0"';
        $lines[] = 'id: ' . $this->yamlString($this->context['id']);
        $lines[] = 'name: ' . $this->yamlString($this->context['displayName']);
        $lines[] = 'version: "1.0.0"';
        $lines[] = 'description: ' . $this->yamlString($this->context['description']);

        if ($this->context['authorName'] !== '') {
            $lines[] = 'authors:';
            $lines[] = '  - name: ' . $this->yamlString($this->context['authorName']);
            if ($this->context['authorEmail'] !== '') {
                $lines[] = '    email: ' . $this->yamlString($this->context['authorEmail']);
            }
        }

        $lines[] = 'license: ' . $this->yamlString($this->context['license']);
        $lines[] = '';
        $lines[] = 'requires:';
        $lines[] = '  notur: "^1.0"';
        $lines[] = '  pterodactyl: "^1.11"';
        $lines[] = '  php: "^8.2"';

        file_put_contents($this->basePath . '/extension.yaml', implode("\n", $lines) . "\n");
    }

    public function writePhpClass(): void
    {
        // Build the list of interfaces to implement (beyond NoturExtension base class)
        $interfaces = [];
        $uses = [
            'Notur\\Support\\NoturExtension',
        ];

        if ($this->context['includeApiRoutes'] || $this->context['includeAdminRoutes']) {
            $interfaces[] = 'HasRoutes';
            $uses[] = 'Notur\\Contracts\\HasRoutes';
        }

        if ($this->context['includeMigrations']) {
            $interfaces[] = 'HasMigrations';
            $uses[] = 'Notur\\Contracts\\HasMigrations';
        }

        if ($this->context['includeAdmin']) {
            $interfaces[] = 'HasBladeViews';
            $uses[] = 'Notur\\Contracts\\HasBladeViews';
        }

        // Note: HasFrontendSlots is deprecated - slots should be registered in frontend code

        $content = "<?php\n\n";
        $content .= "declare(strict_types=1);\n\n";
        $content .= "namespace {$this->context['namespace']};\n\n";

        foreach ($uses as $use) {
            $content .= "use {$use};\n";
        }

        $content .= "\n";

        // Build class declaration
        $classDeclaration = 'class ' . $this->context['className'] . ' extends NoturExtension';
        if (!empty($interfaces)) {
            $classDeclaration .= ' implements ' . implode(', ', $interfaces);
        }

        $content .= $classDeclaration . "\n";
        $content .= "{\n";

        // Only include methods that are needed - getId/getName/getVersion/getBasePath are inherited from NoturExtension

        $hasCustomMethods = false;

        if ($this->context['includeApiRoutes'] || $this->context['includeAdminRoutes']) {
            $hasCustomMethods = true;
            $content .= "    public function getRouteFiles(): array\n";
            $content .= "    {\n";
            $content .= "        return [\n";
            if ($this->context['includeApiRoutes']) {
                $content .= "            'api-client' => 'src/routes/api-client.php',\n";
            }
            if ($this->context['includeAdminRoutes']) {
                $content .= "            'admin' => 'src/routes/admin.php',\n";
            }
            $content .= "        ];\n";
            $content .= "    }\n";
        }

        if ($this->context['includeMigrations']) {
            if ($hasCustomMethods) {
                $content .= "\n";
            }
            $hasCustomMethods = true;
            $content .= "    public function getMigrationsPath(): string\n";
            $content .= "    {\n";
            $content .= "        return \$this->getBasePath() . '/database/migrations';\n";
            $content .= "    }\n";
        }

        if ($this->context['includeAdmin']) {
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
            $content .= "        return '{$this->context['viewNamespace']}';\n";
            $content .= "    }\n";
        }

        // If no custom methods, add a comment explaining the class
        if (!$hasCustomMethods) {
            $content .= "    // Metadata (id, name, version) is read from extension.yaml automatically.\n";
            $content .= "    // Override register() or boot() to add custom initialization logic.\n";
        }

        $content .= "}\n";

        file_put_contents($this->basePath . '/src/' . $this->context['className'] . '.php', $content);
    }

    public function writeApiRoute(): void
    {
        $content = <<<PHP
<?php

use Illuminate\Support\Facades\Route;
use {$this->context['namespace']}\Http\Controllers\ApiController;

Route::get('/ping', [ApiController::class, 'ping']);
PHP;

        file_put_contents($this->basePath . '/src/routes/api-client.php', $content . "\n");
    }

    public function writeAdminRoute(): void
    {
        $content = <<<PHP
<?php

use Illuminate\Support\Facades\Route;
use {$this->context['namespace']}\Http\Controllers\AdminController;

Route::get('/', [AdminController::class, 'index']);
PHP;

        file_put_contents($this->basePath . '/src/routes/admin.php', $content . "\n");
    }

    public function writeApiController(): void
    {
        $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$this->context['namespace']}\Http\Controllers;

use Illuminate\Http\JsonResponse;

class ApiController
{
    public function ping(): JsonResponse
    {
        return response()->json([
            'message' => '{$this->context['displayName']} is alive.',
        ]);
    }
}
PHP;

        file_put_contents($this->basePath . '/src/Http/Controllers/ApiController.php', $content . "\n");
    }

    public function writeAdminController(): void
    {
        if ($this->context['includeAdmin']) {
            $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$this->context['namespace']}\Http\Controllers;

use Illuminate\View\View;

class AdminController
{
    public function index(): View
    {
        return view('{$this->context['viewNamespace']}::admin.index', [
            'extensionId' => '{$this->context['id']}',
            'extensionName' => '{$this->context['displayName']}',
        ]);
    }
}
PHP;
        } else {
            $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$this->context['namespace']}\Http\Controllers;

use Illuminate\Http\JsonResponse;

class AdminController
{
    public function index(): JsonResponse
    {
        return response()->json([
            'message' => '{$this->context['displayName']} admin endpoint is ready.',
        ]);
    }
}
PHP;
        }

        file_put_contents($this->basePath . '/src/Http/Controllers/AdminController.php', $content . "\n");
    }

    public function writeFrontendIndex(): void
    {
        $stubContent = $this->renderStub('frontend-index.tsx.stub', [
            'id' => $this->context['id'],
            'displayName' => $this->context['displayName'],
        ]);
        if ($stubContent !== null) {
            file_put_contents($this->basePath . '/resources/frontend/src/index.tsx', $stubContent . "\n");
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
                {$this->context['displayName']}
            </h3>
            <p style={{ color: 'var(--notur-text-secondary)' }}>
                Hello from {$this->context['id']}!
            </p>
        </div>
    );
};

// Register extension with slots - name/version are auto-resolved from extension.yaml
createExtension({
    id: '{$this->context['id']}',
    slots: [
        {
            slot: 'dashboard.widgets',
            component: ExampleWidget,
            order: 100,
        },
    ],
});
TSX;

        file_put_contents($this->basePath . '/resources/frontend/src/index.tsx', $content . "\n");
    }

    public function writeFrontendPackageJson(): void
    {
        $stubContent = $this->renderStub('frontend-package.json.stub', [
            'id' => $this->context['id'],
        ]);
        if ($stubContent !== null) {
            file_put_contents($this->basePath . '/package.json', $stubContent . "\n");
            return;
        }

        $content = json_encode([
            'name' => $this->context['id'],
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

        file_put_contents($this->basePath . '/package.json', $content . "\n");
    }

    public function writeFrontendTsConfig(): void
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

        file_put_contents($this->basePath . '/tsconfig.json', $content . "\n");
    }

    public function writeFrontendWebpackConfig(): void
    {
        $libraryName = $this->toClassName($this->context['name']);

        $stubContent = $this->renderStub('webpack.config.js.stub', [
            'libraryName' => $libraryName,
        ]);
        if ($stubContent !== null) {
            file_put_contents($this->basePath . '/webpack.config.js', $stubContent . "\n");
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

        file_put_contents($this->basePath . '/webpack.config.js', $content . "\n");
    }

    public function writeMigration(): void
    {
        $table = str_replace('-', '_', $this->context['vendor'] . '_' . $this->context['name']);
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
        Schema::create('{$table}', function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$table}');
    }
};
PHP;

        file_put_contents($this->basePath . '/database/migrations/' . $filename, $content . "\n");
    }

    public function writeAdminView(): void
    {
        $content = <<<BLADE
@extends('layouts.admin')

@section('title')
    {$this->context['displayName']}
@endsection

@section('content-header')
    <h1>{$this->context['displayName']}<small>Admin</small></h1>
@endsection

@section('content')
    <div class="box box-primary">
        <div class="box-body">
            <p>Welcome to {$this->context['displayName']}.</p>
        </div>
    </div>
@endsection
BLADE;

        file_put_contents($this->basePath . '/resources/views/admin/index.blade.php', $content . "\n");
    }

    public function writeComposerJson(): void
    {
        $content = json_encode([
            'name' => $this->context['id'],
            'description' => $this->context['description'],
            'type' => 'library',
            'license' => $this->context['license'],
            'require' => [
                'php' => '^8.2',
            ],
            'require-dev' => [
                'notur/notur' => '^1.0',
                'phpunit/phpunit' => '^11.0',
            ],
            'autoload' => [
                'psr-4' => [
                    $this->context['namespace'] . '\\' => 'src/',
                ],
            ],
            'autoload-dev' => [
                'psr-4' => [
                    $this->context['namespace'] . '\\Tests\\' => 'tests/',
                ],
            ],
            'minimum-stability' => 'stable',
            'prefer-stable' => true,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        file_put_contents($this->basePath . '/composer.json', $content . "\n");
    }

    public function writePhpunitXml(): void
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

        file_put_contents($this->basePath . '/phpunit.xml', $content . "\n");
    }

    public function writePhpunitTest(): void
    {
        $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$this->context['namespace']}\Tests\Unit;

use {$this->context['namespace']}\\{$this->context['className']};
use PHPUnit\Framework\TestCase;

class ExtensionTest extends TestCase
{
    public function test_extension_metadata(): void
    {
        \$extension = new {$this->context['className']}();

        \$this->assertSame('{$this->context['id']}', \$extension->getId());
        \$this->assertSame('{$this->context['displayName']}', \$extension->getName());
        \$this->assertSame('1.0.0', \$extension->getVersion());
    }
}
PHP;

        file_put_contents($this->basePath . '/tests/Unit/ExtensionTest.php', $content . "\n");
    }

    public function writeReadme(string $packageManager): void
    {
        $resolver = new PackageManagerResolver();

        $lines = [];
        $lines[] = '# ' . $this->context['displayName'];
        $lines[] = '';
        $lines[] = $this->context['description'];
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

        if ($this->context['includeFrontend']) {
            $lines[] = '';
            $lines[] = '## Frontend Development';
            $lines[] = '';
            $lines[] = "```bash";
            $lines[] = $resolver->installCommand($packageManager);
            $lines[] = $resolver->runScriptCommand($packageManager, 'dev');
            $lines[] = "```";
        }

        if ($this->context['includeTests']) {
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

        if ($this->context['includeFrontend']) {
            $lines[] = '- `resources/frontend/` - Frontend source and bundle';
        }

        if ($this->context['includeMigrations']) {
            $lines[] = '- `database/migrations/` - Database migrations';
        }

        if ($this->context['includeAdmin']) {
            $lines[] = '- `resources/views/` - Blade views for admin UI';
        }

        if ($this->context['includeTests']) {
            $lines[] = '- `tests/` - PHPUnit tests';
        }

        file_put_contents($this->basePath . '/README.md', implode("\n", $lines) . "\n");
    }

    public function writeGitignore(): void
    {
        $content = <<<TXT
/node_modules
/vendor
.phpunit.result.cache
.DS_Store
TXT;

        file_put_contents($this->basePath . '/.gitignore', $content . "\n");
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
        $stubPath = dirname(__DIR__, 2) . '/resources/stubs/new-extension/' . $filename;
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
}
