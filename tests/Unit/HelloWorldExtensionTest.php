<?php

declare(strict_types=1);

namespace Notur\Tests\Unit;

use Notur\ExtensionManifest;
use PHPUnit\Framework\TestCase;

class HelloWorldExtensionTest extends TestCase
{
    private string $extensionPath;

    protected function setUp(): void
    {
        $this->extensionPath = dirname(__DIR__, 2) . '/examples/hello-world';
    }

    public function test_manifest_loads(): void
    {
        $manifest = ExtensionManifest::load($this->extensionPath);

        $this->assertSame('notur/hello-world', $manifest->getId());
        $this->assertSame('Hello World', $manifest->getName());
        $this->assertSame('1.0.0', $manifest->getVersion());
    }

    public function test_entrypoint_class_exists(): void
    {
        // Register the autoloading manually for this test
        spl_autoload_register(function (string $class) {
            $prefix = 'Notur\\HelloWorld\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
            $file = $this->extensionPath . '/src/' . $relative . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        });

        $manifest = ExtensionManifest::load($this->extensionPath);
        $entrypoint = $manifest->getEntrypoint();

        $this->assertTrue(class_exists($entrypoint), "Entrypoint class {$entrypoint} should exist");

        $extension = new $entrypoint();
        $this->assertInstanceOf(\Notur\Contracts\ExtensionInterface::class, $extension);
        $this->assertInstanceOf(\Notur\Contracts\HasRoutes::class, $extension);
        $this->assertInstanceOf(\Notur\Contracts\HasFrontendSlots::class, $extension);
    }

    public function test_route_file_exists(): void
    {
        $manifest = ExtensionManifest::load($this->extensionPath);
        $routes = $manifest->getRoutes();

        $this->assertArrayHasKey('api-client', $routes);

        $routeFile = $this->extensionPath . '/' . $routes['api-client'];
        $this->assertFileExists($routeFile);
    }

    public function test_frontend_bundle_exists(): void
    {
        $manifest = ExtensionManifest::load($this->extensionPath);
        $bundle = $manifest->getFrontendBundle();

        $this->assertNotEmpty($bundle);

        $bundleFile = $this->extensionPath . '/' . $bundle;
        $this->assertFileExists($bundleFile, "Built JS bundle should exist at {$bundleFile}");
    }

    public function test_frontend_slots_declared(): void
    {
        $manifest = ExtensionManifest::load($this->extensionPath);
        $slots = $manifest->getFrontendSlots();

        $this->assertArrayHasKey('dashboard.widgets', $slots);
    }
}
