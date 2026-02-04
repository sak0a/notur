<?php

declare(strict_types=1);

namespace Notur\Tests\Integration;

use Notur\DependencyResolver;
use Notur\ExtensionManager;
use Notur\Features\FeatureRegistry;
use Notur\NoturServiceProvider;
use Notur\PermissionBroker;
use Notur\Support\ThemeCompiler;
use Orchestra\Testbench\TestCase;

class MinimalManifestBootTest extends TestCase
{
    private string $basePath;
    private string $extensionId = 'acme/minimal';
    private string $extensionPath;

    protected function getPackageProviders($app): array
    {
        return [NoturServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->basePath = base_path();
        $this->extensionPath = $this->basePath . '/notur/extensions/acme/minimal';

        $this->createExtensionFixture();
        $this->writeExtensionsManifest();
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->basePath . '/notur');
        parent::tearDown();
    }

    public function test_minimal_manifest_boots_extension(): void
    {
        $manager = new ExtensionManager(
            $this->app,
            new DependencyResolver(),
            new PermissionBroker(),
            new ThemeCompiler(),
            FeatureRegistry::defaults(),
        );

        $manager->boot();

        $this->assertTrue($manager->isEnabled($this->extensionId));
        $manifest = $manager->getManifest($this->extensionId);
        $this->assertNotNull($manifest);

        $routes = $manifest->getRoutes();
        $this->assertArrayHasKey('api-client', $routes);
        $this->assertSame('src/routes/api-client.php', $routes['api-client']);

        $registered = collect($this->app['router']->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/client/notur/' . $this->extensionId));
        $this->assertTrue($registered->isNotEmpty(), 'Default route file should be registered.');
    }

    private function createExtensionFixture(): void
    {
        $dirs = [
            $this->extensionPath,
            $this->extensionPath . '/src',
            $this->extensionPath . '/src/routes',
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        $manifest = <<<YAML
id: "{$this->extensionId}"
name: "Minimal Extension"
version: "1.0.0"
YAML;

        file_put_contents($this->extensionPath . '/extension.yaml', $manifest);

        $entrypoint = <<<PHP
<?php

declare(strict_types=1);

namespace Acme\\Minimal;

use Notur\\Contracts\\ExtensionInterface;

class MinimalExtension implements ExtensionInterface
{
    public function getId(): string
    {
        return "{$this->extensionId}";
    }

    public function getName(): string
    {
        return "Minimal Extension";
    }

    public function getVersion(): string
    {
        return "1.0.0";
    }

    public function register(): void
    {
        // no-op
    }

    public function boot(): void
    {
        // no-op
    }

    public function getBasePath(): string
    {
        return __DIR__ . '/..';
    }
}
PHP;

        file_put_contents($this->extensionPath . '/src/MinimalExtension.php', $entrypoint . "\n");

        $routes = <<<PHP
<?php

use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json(['ok' => true]);
});
PHP;

        file_put_contents($this->extensionPath . '/src/routes/api-client.php', $routes . "\n");
    }

    private function writeExtensionsManifest(): void
    {
        $manifestPath = $this->basePath . '/notur/extensions.json';
        $dir = dirname($manifestPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $manifest = [
            'extensions' => [
                $this->extensionId => [
                    'version' => '1.0.0',
                    'enabled' => true,
                    'installed_at' => now()->toIso8601String(),
                ],
            ],
        ];

        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
