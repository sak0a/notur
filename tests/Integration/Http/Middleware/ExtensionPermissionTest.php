<?php

declare(strict_types=1);

namespace Notur\Tests\Integration\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mockery;
use Notur\Http\Middleware\ExtensionPermission;
use Notur\NoturServiceProvider;
use Notur\PermissionBroker;
use Orchestra\Testbench\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ExtensionPermissionTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [NoturServiceProvider::class];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_admin_user_bypasses_scoped_permission_check(): void
    {
        $broker = Mockery::mock(PermissionBroker::class);
        $broker->shouldReceive('extensionDeclares')->with('acme/test', 'view')->once()->andReturn(true);
        $broker->shouldReceive('scopePermission')->with('acme/test', 'view')->once()->andReturn('notur.acme/test.view');

        $request = Request::create('/api/client/notur/acme/test/report', 'GET');
        $request->attributes->set('notur.extension_id', 'acme/test');
        $request->setUserResolver(fn () => new class {
            public bool $root_admin = true;
        });

        $response = (new ExtensionPermission($broker))->handle(
            $request,
            fn () => new Response('ok'),
            'view',
        );

        $this->assertSame('ok', $response->getContent());
    }

    public function test_server_scoped_user_must_have_scoped_permission_or_wildcard(): void
    {
        $broker = Mockery::mock(PermissionBroker::class);
        $broker->shouldReceive('extensionDeclares')->with('acme/test', 'manage')->once()->andReturn(true);
        $broker->shouldReceive('scopePermission')->with('acme/test', 'manage')->once()->andReturn('notur.acme/test.manage');

        $request = Request::create('/api/client/notur/acme/test/server-action', 'POST');
        $request->attributes->set('notur.extension_id', 'acme/test');
        $request->attributes->set('server', (object) ['id' => 123]);
        $request->setUserResolver(fn () => new class {
            public bool $root_admin = false;

            public function permissions(object $server): array
            {
                return ['notur.acme/test.manage'];
            }
        });

        $response = (new ExtensionPermission($broker))->handle(
            $request,
            fn () => new Response('ok'),
            'manage',
        );

        $this->assertSame('ok', $response->getContent());
    }

    public function test_undeclared_permission_is_rejected(): void
    {
        $broker = Mockery::mock(PermissionBroker::class);
        $broker->shouldReceive('extensionDeclares')->with('acme/test', 'delete')->once()->andReturn(false);
        $broker->shouldNotReceive('scopePermission');

        $request = Request::create('/api/client/notur/acme/test/delete', 'DELETE');
        $request->attributes->set('notur.extension_id', 'acme/test');
        $request->setUserResolver(fn () => new class {
            public bool $root_admin = true;
        });

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage("Permission 'delete' is not declared by extension 'acme/test'.");

        (new ExtensionPermission($broker))->handle($request, fn () => new Response('ok'), 'delete');
    }
}
