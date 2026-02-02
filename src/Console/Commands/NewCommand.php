<?php

declare(strict_types=1);

namespace Notur\Console\Commands;

use Illuminate\Console\Command;

class NewCommand extends Command
{
    protected $signature = 'notur:new
        {id : The extension ID in vendor/name format (e.g. acme/hello-world)}
        {--path= : Directory to create the extension in (defaults to current directory)}';

    protected $description = 'Scaffold a new Notur extension from templates';

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

        $this->info("Scaffolding extension: {$id}");

        // Create directory structure
        $directories = [
            $basePath,
            $basePath . '/src',
            $basePath . '/frontend/src',
        ];

        foreach ($directories as $dir) {
            mkdir($dir, 0755, true);
        }

        // Generate files
        $this->writeManifest($basePath, $id, $name);
        $this->writePhpClass($basePath, $vendor, $name, $id);
        $this->writeFrontendIndex($basePath, $id, $name);
        $this->writeFrontendPackageJson($basePath, $id, $name);
        $this->writeFrontendTsConfig($basePath);
        $this->writeFrontendWebpackConfig($basePath, $name);
        $this->writeReadme($basePath, $id, $name, $vendor);

        $this->newLine();
        $this->info('Extension scaffolded successfully!');
        $this->newLine();
        $this->line("  <comment>Directory:</comment>  {$basePath}");
        $this->line("  <comment>Extension:</comment>  {$id}");
        $this->newLine();
        $this->line('  <info>Next steps:</info>');
        $this->line("    1. cd {$basePath}");
        $this->line('    2. cd frontend && npm install');
        $this->line('    3. npm run build');
        $this->line('    4. Edit src/' . $this->toClassName($name) . '.php to add backend logic');
        $this->line('    5. Edit frontend/src/index.ts to add UI components');
        $this->line('    6. Run: php artisan notur:install ' . $basePath);
        $this->newLine();

        return 0;
    }

    private function writeManifest(string $basePath, string $id, string $name): void
    {
        $displayName = $this->toDisplayName($name);
        $className = $this->toClassName($name);

        $content = <<<YAML
id: "{$id}"
name: "{$displayName}"
version: "1.0.0"
description: "A Notur extension"
entrypoint: "src/{$className}.php"

frontend:
  bundle: "frontend/dist/bundle.js"

permissions: []

dependencies: []
YAML;

        file_put_contents($basePath . '/extension.yaml', $content . "\n");
    }

    private function writePhpClass(string $basePath, string $vendor, string $name, string $id): void
    {
        $namespace = $this->toNamespace($vendor) . '\\' . $this->toClassName($name);
        $className = $this->toClassName($name);
        $displayName = $this->toDisplayName($name);

        $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Notur\Contracts\ExtensionInterface;

class {$className} implements ExtensionInterface
{
    public function getId(): string
    {
        return '{$id}';
    }

    public function getName(): string
    {
        return '{$displayName}';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function register(): void
    {
        // Register bindings, services, or configuration here.
    }

    public function boot(): void
    {
        // Boot logic after all extensions are registered.
    }

    public function getBasePath(): string
    {
        return __DIR__ . '/..';
    }
}
PHP;

        file_put_contents($basePath . '/src/' . $className . '.php', $content . "\n");
    }

    private function writeFrontendIndex(string $basePath, string $id, string $name): void
    {
        $displayName = $this->toDisplayName($name);

        $content = <<<TS
import { createExtension } from '@notur/sdk';

createExtension({
    config: {
        id: '{$id}',
        name: '{$displayName}',
        version: '1.0.0',
    },
    slots: [],
    routes: [],
    onInit() {
        console.log('[{$displayName}] Extension loaded');
    },
});
TS;

        file_put_contents($basePath . '/frontend/src/index.ts', $content . "\n");
    }

    private function writeFrontendPackageJson(string $basePath, string $id, string $name): void
    {
        $content = json_encode([
            'name' => $id,
            'version' => '1.0.0',
            'private' => true,
            'scripts' => [
                'build' => 'webpack --mode production',
                'dev' => 'webpack --mode development --watch',
            ],
            'peerDependencies' => [
                'react' => '^16.14.0',
                'react-dom' => '^16.14.0',
                '@notur/sdk' => '^1.0.0',
            ],
            'devDependencies' => [
                'typescript' => '^5.3.0',
                'ts-loader' => '^9.5.0',
                'webpack' => '^5.89.0',
                'webpack-cli' => '^5.1.0',
                '@types/react' => '^16.14.0',
                '@types/react-dom' => '^16.9.0',
                '@notur/sdk' => '^1.0.0',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        file_put_contents($basePath . '/frontend/package.json', $content . "\n");
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
                'outDir' => './dist',
                'rootDir' => './src',
                'sourceMap' => true,
                'skipLibCheck' => true,
            ],
            'include' => ['src/**/*'],
            'exclude' => ['node_modules', 'dist'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        file_put_contents($basePath . '/frontend/tsconfig.json', $content . "\n");
    }

    private function writeFrontendWebpackConfig(string $basePath, string $name): void
    {
        $libraryName = $this->toClassName($name);

        $content = <<<JS
const path = require('path');

module.exports = {
    entry: './src/index.ts',
    output: {
        filename: 'bundle.js',
        path: path.resolve(__dirname, 'dist'),
        library: {
            name: '__NOTUR_EXT_{$libraryName}__',
            type: 'umd',
        },
        clean: true,
    },
    resolve: {
        extensions: ['.ts', '.tsx', '.js', '.jsx'],
    },
    module: {
        rules: [
            {
                test: /\\.tsx?$/,
                use: 'ts-loader',
                exclude: /node_modules/,
            },
        ],
    },
    externals: {
        react: 'React',
        'react-dom': 'ReactDOM',
        '@notur/sdk': '__NOTUR__',
    },
};
JS;

        file_put_contents($basePath . '/frontend/webpack.config.js', $content . "\n");
    }

    private function writeReadme(string $basePath, string $id, string $name, string $vendor): void
    {
        $displayName = $this->toDisplayName($name);

        $content = <<<MD
# {$displayName}

A Notur extension for Pterodactyl Panel.

## Installation

```bash
php artisan notur:install {$id}
```

## Development

```bash
cd frontend
npm install
npm run dev
```

## Building

```bash
cd frontend
npm run build
```

## Structure

- `extension.yaml` - Extension manifest
- `src/` - PHP backend code
- `frontend/src/` - TypeScript frontend code
- `frontend/dist/` - Compiled frontend bundle
MD;

        file_put_contents($basePath . '/README.md', $content . "\n");
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
}
