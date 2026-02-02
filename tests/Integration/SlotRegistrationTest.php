<?php

declare(strict_types=1);

namespace Notur\Tests\Integration;

use Notur\ExtensionManager;
use Notur\NoturServiceProvider;
use Notur\PermissionBroker;
use Orchestra\Testbench\TestCase;

class SlotRegistrationTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [NoturServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    public function test_frontend_slots_are_empty_with_no_extensions(): void
    {
        $manager = $this->app->make(ExtensionManager::class);
        $this->assertEmpty($manager->getFrontendSlots());
    }

    public function test_permission_broker_is_singleton(): void
    {
        $a = $this->app->make(PermissionBroker::class);
        $b = $this->app->make(PermissionBroker::class);
        $this->assertSame($a, $b);
    }

    public function test_permission_broker_registers_and_queries(): void
    {
        $broker = $this->app->make(PermissionBroker::class);
        $broker->register('acme/test', ['test.view', 'test.edit']);

        $this->assertTrue($broker->extensionDeclares('acme/test', 'test.view'));
        $this->assertFalse($broker->extensionDeclares('acme/test', 'test.delete'));
        $this->assertSame(['test.view', 'test.edit'], $broker->getExtensionPermissions('acme/test'));
    }

    public function test_permission_scoping(): void
    {
        $broker = $this->app->make(PermissionBroker::class);

        $scoped = $broker->scopePermission('acme/test', 'test.view');
        $this->assertSame('notur.acme/test.test.view', $scoped);

        $this->assertTrue($broker->isOwnedBy('acme/test', 'notur.acme/test.test.view'));
        $this->assertFalse($broker->isOwnedBy('acme/other', 'notur.acme/test.test.view'));
    }
}
