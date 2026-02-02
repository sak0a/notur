<?php

declare(strict_types=1);

namespace Notur\Tests\Unit;

use Illuminate\Contracts\Foundation\Application;
use Notur\DependencyResolver;
use Notur\ExtensionManager;
use Notur\PermissionBroker;
use PHPUnit\Framework\TestCase;

class ExtensionManagerTest extends TestCase
{
    private ExtensionManager $manager;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/notur-manager-test-' . uniqid();
        mkdir($this->tempDir . '/notur/extensions', 0755, true);
        mkdir($this->tempDir . '/public/notur/extensions', 0755, true);

        $app = $this->createMock(Application::class);
        $app->method('basePath')->willReturn($this->tempDir);

        // base_path() is a global function — test the manager logic that doesn't depend on it
        $resolver = new DependencyResolver();
        $broker = new PermissionBroker();

        $this->manager = new ExtensionManager($app, $resolver, $broker);
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->tempDir);
    }

    public function test_starts_with_no_extensions(): void
    {
        $this->assertEmpty($this->manager->all());
    }

    public function test_get_returns_null_for_unknown_extension(): void
    {
        $this->assertNull($this->manager->get('acme/nonexistent'));
    }

    public function test_is_enabled_returns_false_for_unloaded(): void
    {
        $this->assertFalse($this->manager->isEnabled('acme/nonexistent'));
    }

    public function test_get_frontend_slots_returns_empty_initially(): void
    {
        $this->assertEmpty($this->manager->getFrontendSlots());
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) return;

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
