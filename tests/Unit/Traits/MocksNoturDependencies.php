<?php

declare(strict_types=1);

namespace Notur\Tests\Unit\Traits;

use Illuminate\Http\Request;
use Notur\ExtensionManager;
use Notur\PermissionBroker;
use Symfony\Component\HttpFoundation\ParameterBag;

trait MocksNoturDependencies
{
    protected function createMockExtensionManager(array $methods = []): ExtensionManager
    {
        $mock = $this->createMock(ExtensionManager::class);
        foreach ($methods as $method => $return) {
            $mock->method($method)->willReturn($return);
        }
        return $mock;
    }

    protected function createMockPermissionBroker(array $overrides = []): PermissionBroker
    {
        $mock = $this->createMock(PermissionBroker::class);

        $mock->method('extensionDeclares')->willReturn($overrides['extensionDeclares'] ?? true);
        $mock->method('scopePermission')->willReturnCallback(
            $overrides['scopePermission'] ?? fn($extId, $perm) => "notur.{$extId}.{$perm}"
        );

        return $mock;
    }

    protected function createMockRequest(array $attributes = [], array $options = []): Request
    {
        $request = $this->createMock(Request::class);
        $request->attributes = new ParameterBag($attributes);

        if (isset($options['path'])) {
            $request->method('path')->willReturn($options['path']);
        }

        if (isset($options['user'])) {
            $request->method('user')->willReturn($options['user']);
        }

        return $request;
    }

    protected function createMockUser(array $options = []): object
    {
        return new class($options) {
            public bool $root_admin;
            private array $permissions;
            private bool $canResult;

            public function __construct(array $options)
            {
                $this->root_admin = $options['root_admin'] ?? false;
                $this->permissions = $options['permissions'] ?? [];
                $this->canResult = $options['can'] ?? true;
            }

            public function permissions($server): array
            {
                return $this->permissions;
            }

            public function can(string $permission): bool
            {
                return $this->canResult;
            }

            public function isRootAdmin(): bool
            {
                return $this->root_admin;
            }
        };
    }
}
