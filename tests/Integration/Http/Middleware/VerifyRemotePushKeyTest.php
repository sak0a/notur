<?php

declare(strict_types=1);

namespace Notur\Tests\Integration\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Notur\Http\Middleware\VerifyRemotePushKey;
use Notur\Models\RemotePushApiKey;
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

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__ . '/../../../../database/migrations');
    }

    public function test_disabled_remote_push_returns_not_found(): void
    {
        config(['notur.remote_push.enabled' => false]);

        $request = Request::create('/api/notur/dev/push', 'POST');

        $this->expectException(NotFoundHttpException::class);

        (new VerifyRemotePushKey())->handle($request, fn () => new Response('ok'));
    }

    public function test_valid_env_key_allows_request(): void
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
        $this->assertNull($request->attributes->get('notur_remote_push_key'));
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

    public function test_valid_db_key_allows_request_and_attaches_model(): void
    {
        config(['notur.remote_push.enabled' => true]);

        $plaintext = RemotePushApiKey::generatePlaintext();
        $key = RemotePushApiKey::create([
            'name' => 'CI key',
            'prefix' => RemotePushApiKey::prefixOf($plaintext),
            'token_hash' => RemotePushApiKey::hashToken($plaintext),
        ]);

        $request = Request::create('/api/notur/dev/push', 'POST', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $plaintext,
            'REMOTE_ADDR' => '203.0.113.7',
        ]);

        $response = (new VerifyRemotePushKey())->handle($request, fn () => new Response('ok'));

        $this->assertSame('ok', $response->getContent());

        $attached = $request->attributes->get('notur_remote_push_key');
        $this->assertInstanceOf(RemotePushApiKey::class, $attached);
        $this->assertSame($key->id, $attached->id);

        $key->refresh();
        $this->assertNotNull($key->last_used_at);
        $this->assertSame('203.0.113.7', $key->last_used_ip);
    }

    public function test_revoked_db_key_is_rejected(): void
    {
        config(['notur.remote_push.enabled' => true]);

        $plaintext = RemotePushApiKey::generatePlaintext();
        RemotePushApiKey::create([
            'name' => 'Revoked',
            'prefix' => RemotePushApiKey::prefixOf($plaintext),
            'token_hash' => RemotePushApiKey::hashToken($plaintext),
            'revoked_at' => now(),
        ]);

        $request = Request::create('/api/notur/dev/push', 'POST', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $plaintext,
        ]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Invalid Notur remote push key.');

        (new VerifyRemotePushKey())->handle($request, fn () => new Response('ok'));
    }

    public function test_no_keys_anywhere_returns_403(): void
    {
        config([
            'notur.remote_push.enabled' => true,
            'notur.remote_push.keys' => [],
        ]);

        $request = Request::create('/api/notur/dev/push', 'POST', server: [
            'HTTP_AUTHORIZATION' => 'Bearer notur_anything',
        ]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Invalid Notur remote push key.');

        (new VerifyRemotePushKey())->handle($request, fn () => new Response('ok'));
    }
}
