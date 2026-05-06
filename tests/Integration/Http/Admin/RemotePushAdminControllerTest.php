<?php

declare(strict_types=1);

namespace Notur\Tests\Integration\Http\Admin;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User as AuthUser;
use Notur\Models\InstalledExtension;
use Notur\Models\RemotePushApiKey;
use Notur\NoturServiceProvider;
use Orchestra\Testbench\TestCase;

class RemotePushAdminControllerTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [NoturServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('auth.providers.users.model', \Notur\Tests\Integration\Http\Admin\TestAdminUser::class);
        $app['router']->aliasMiddleware('admin', \Notur\Tests\Integration\Http\Admin\AllowAdminMiddleware::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__ . '/../../../../database/migrations');
        // CSRF is included in the `web` group; disable for these tests.
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
    }

    private function actingAsAdmin(): self
    {
        $admin = new TestAdminUser(['id' => 1, 'root_admin' => true]);
        $this->actingAs($admin);
        return $this;
    }

    private function actingAsUser(): self
    {
        $user = new TestAdminUser(['id' => 2, 'root_admin' => false]);
        $this->actingAs($user);
        return $this;
    }

    public function test_non_admin_user_is_forbidden(): void
    {
        $this->actingAsUser()->get('/admin/notur/dev-push')->assertForbidden();
    }

    public function test_admin_index_renders_empty_state(): void
    {
        $response = $this->actingAsAdmin()->get('/admin/notur/dev-push');

        $response->assertOk();
        $response->assertSee('No API keys yet', false);
        $response->assertSee('No remote-pushed extensions yet', false);
    }

    public function test_admin_can_create_key_and_plaintext_is_shown_once(): void
    {
        $create = $this->actingAsAdmin()->post('/admin/notur/dev-push/keys', ['name' => 'My laptop']);
        $create->assertRedirect('/admin/notur/dev-push');

        $followup = $this->actingAsAdmin()->get('/admin/notur/dev-push');
        $followup->assertOk();
        $followup->assertSee('My laptop');
        $followup->assertSee('notur_', false);

        // The plaintext should be in session flash exactly once.
        $secondFollowup = $this->actingAsAdmin()->get('/admin/notur/dev-push');
        $secondFollowup->assertOk();
        $secondFollowup->assertDontSee('Copy this key now');

        $this->assertSame(1, RemotePushApiKey::query()->count());
        $key = RemotePushApiKey::query()->first();
        $this->assertSame('My laptop', $key->name);
        $this->assertNotEmpty($key->token_hash);
        $this->assertSame(64, strlen((string) $key->token_hash));
    }

    public function test_admin_can_revoke_key(): void
    {
        $plaintext = RemotePushApiKey::generatePlaintext();
        $key = RemotePushApiKey::create([
            'name' => 'Existing',
            'prefix' => RemotePushApiKey::prefixOf($plaintext),
            'token_hash' => RemotePushApiKey::hashToken($plaintext),
        ]);

        $response = $this->actingAsAdmin()->post('/admin/notur/dev-push/keys/' . $key->id . '/revoke');
        $response->assertRedirect('/admin/notur/dev-push');

        $key->refresh();
        $this->assertNotNull($key->revoked_at);
    }

    public function test_admin_can_regenerate_key(): void
    {
        $plaintext = RemotePushApiKey::generatePlaintext();
        $original = RemotePushApiKey::create([
            'name' => 'Rotate me',
            'prefix' => RemotePushApiKey::prefixOf($plaintext),
            'token_hash' => RemotePushApiKey::hashToken($plaintext),
        ]);

        $response = $this->actingAsAdmin()->post('/admin/notur/dev-push/keys/' . $original->id . '/regenerate');
        $response->assertRedirect('/admin/notur/dev-push');

        $original->refresh();
        $this->assertNotNull($original->revoked_at);

        $newKey = RemotePushApiKey::query()
            ->where('name', 'Rotate me')
            ->whereNull('revoked_at')
            ->first();
        $this->assertNotNull($newKey);
        $this->assertNotSame($original->id, $newKey->id);
    }

    public function test_admin_can_view_extension_manifest(): void
    {
        InstalledExtension::create([
            'extension_id' => 'acme/visible',
            'name' => 'Visible',
            'version' => '1.0.0',
            'enabled' => true,
            'manifest' => ['id' => 'acme/visible', 'name' => 'Visible', 'version' => '1.0.0'],
            'source' => 'remote_push',
        ]);

        $response = $this->actingAsAdmin()
            ->get('/admin/notur/dev-push/extensions/acme%2Fvisible/manifest');

        $response->assertOk();
        $response->assertJson(['id' => 'acme/visible', 'version' => '1.0.0']);
    }

    public function test_pushed_extension_appears_in_index(): void
    {
        $plaintext = RemotePushApiKey::generatePlaintext();
        $key = RemotePushApiKey::create([
            'name' => 'CI',
            'prefix' => RemotePushApiKey::prefixOf($plaintext),
            'token_hash' => RemotePushApiKey::hashToken($plaintext),
        ]);

        InstalledExtension::create([
            'extension_id' => 'acme/listed',
            'name' => 'Listed',
            'version' => '2.0.0',
            'enabled' => true,
            'manifest' => ['id' => 'acme/listed', 'name' => 'Listed', 'version' => '2.0.0'],
            'source' => 'remote_push',
            'pushed_via_key_id' => $key->id,
            'last_pushed_at' => now(),
            'package_checksum' => str_repeat('a', 64),
            'package_size' => 1234,
        ]);

        $response = $this->actingAsAdmin()->get('/admin/notur/dev-push');

        $response->assertOk();
        $response->assertSee('acme/listed');
        $response->assertSee('2.0.0');
        $response->assertSee('CI');
    }
}

class TestAdminUser extends AuthUser
{
    protected $guarded = [];

    protected $casts = [
        'root_admin' => 'boolean',
    ];
}

class AllowAdminMiddleware
{
    public function handle($request, \Closure $next)
    {
        $user = $request->user();
        if (!$user || !($user->root_admin ?? false)) {
            abort(403);
        }
        return $next($request);
    }
}
