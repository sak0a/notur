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

        // NoturExtension auto-discovers entrypoint from namespace convention when not set in YAML.
        // For this example the class is known; verify it loads and satisfies current contracts.
        $entrypoint = \Notur\HelloWorld\HelloWorldExtension::class;

        $this->assertTrue(class_exists($entrypoint), "Entrypoint class {$entrypoint} should exist");

        $extension = new $entrypoint();
        $this->assertInstanceOf(\Notur\Contracts\ExtensionInterface::class, $extension);
        $this->assertInstanceOf(\Notur\Contracts\HasRoutes::class, $extension);
        // HasFrontendSlots is deprecated; slots are registered in frontend code via createExtension()
        $this->assertNotInstanceOf(\Notur\Contracts\HasFrontendSlots::class, $extension);
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

    public function test_frontend_slots_not_in_manifest(): void
    {
        // Slots are deprecated in manifest — they must be declared in frontend code
        // via createExtension(). The manifest should return an empty slots array.
        $manifest = ExtensionManifest::load($this->extensionPath);
        $slots = $manifest->getFrontendSlots();

        $this->assertEmpty($slots, 'Manifest frontend.slots should be empty; register slots via createExtension() in frontend code instead');
    }
}
