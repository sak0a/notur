<?php

declare(strict_types=1);

namespace Notur\Tests\Integration\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Notur\Http\Middleware\VerifyRemotePushKey;
use Notur\NoturServiceProvider;
use Orchestra\Testbench\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class VerifyRemotePushKeyTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [NoturServiceProvider::class];
    }

    public function test_disabled_remote_push_returns_not_found(): void
    {
        config(['notur.remote_push.enabled' => false]);

        $request = Request::create('/api/notur/dev/push', 'POST');

        $this->expectException(NotFoundHttpException::class);

        (new VerifyRemotePushKey())->handle($request, fn () => new Response('ok'));
    }

    public function test_valid_bearer_key_allows_request(): void
    {
        config([
            'notur.remote_push.enabled' => true,
            'notur.remote_push.keys' => ['notur_test_key'],
        ]);

        $request = Request::create('/api/notur/dev/push', 'POST', server: [
            'HTTP_AUTHORIZATION' => 'Bearer notur_test_key',
        ]);

        $response = (new VerifyRemotePushKey())->handle($request, fn () => new Response('ok'));

        $this->assertSame('ok', $response->getContent());
    }

    public function test_invalid_key_is_rejected(): void
    {
        config([
            'notur.remote_push.enabled' => true,
            'notur.remote_push.keys' => ['notur_test_key'],
        ]);

        $request = Request::create('/api/notur/dev/push', 'POST', server: [
            'HTTP_AUTHORIZATION' => 'Bearer wrong',
        ]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Invalid Notur remote push key.');

        (new VerifyRemotePushKey())->handle($request, fn () => new Response('ok'));
    }
}
