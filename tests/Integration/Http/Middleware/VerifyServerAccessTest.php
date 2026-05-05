<?php

declare(strict_types=1);

namespace Notur\Tests\Integration\Http\Middleware;

use Illuminate\Http\Request;
use Notur\Http\Middleware\VerifyServerAccess;
use Notur\NoturServiceProvider;
use Orchestra\Testbench\TestCase;

class VerifyServerAccessTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [NoturServiceProvider::class];
    }

    public function test_api_request_without_server_identifier_returns_json_404(): void
    {
        $request = Request::create('/api/client/notur/acme/test/servers', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response = (new VerifyServerAccess())->handle($request, fn () => response('ok'));
        $payload = $response->getData(true);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Server identifier required.', $payload['message']);
    }

    public function test_api_request_without_authenticated_user_returns_json_403(): void
    {
        $request = Request::create('/api/client/notur/acme/test/servers/test-server', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $request->setRouteResolver(fn () => new class {
            public function parameter(string $name): ?string
            {
                return $name === 'server' ? 'test-server' : null;
            }
        });

        $response = (new VerifyServerAccess())->handle($request, fn () => response('ok'));
        $payload = $response->getData(true);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('Authentication required.', $payload['message']);
    }
}
