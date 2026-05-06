<?php

declare(strict_types=1);

namespace Pterodactyl\Models {
    use Illuminate\Database\Eloquent\Model;

    if (!class_exists(Server::class, false)) {
        class Server extends Model
        {
            protected $table = 'servers';
            protected $guarded = [];
            public $timestamps = false;

            public function subusers()
            {
                return $this->hasMany(Subuser::class);
            }
        }

        class Subuser extends Model
        {
            protected $table = 'subusers';
            protected $guarded = [];
            public $timestamps = false;
        }
    }
}

namespace Notur\Tests\Integration\Http\Middleware {

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Notur\Http\Middleware\VerifyServerAccess;
use Notur\NoturServiceProvider;
use Orchestra\Testbench\TestCase;
use Pterodactyl\Models\Server;

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

    public function test_admin_request_accepts_model_bound_server_parameter(): void
    {
        $server = new Server([
            'id' => 2,
            'uuid' => '13a9a711-382f-4ce4-9aa9-ab62cbe498b7',
            'uuidShort' => '13a9a711',
            'owner_id' => 1,
        ]);

        $request = Request::create('/api/client/notur/acme/test/servers/13a9a711/status', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $request->setRouteResolver(fn () => new class($server) {
            public function __construct(private readonly Server $server) {}

            public function parameter(string $name): ?Server
            {
                return $name === 'server' ? $this->server : null;
            }
        });
        $request->setUserResolver(fn () => new class {
            public int $id = 99;
            public bool $root_admin = true;
        });

        $response = (new VerifyServerAccess())->handle($request, fn () => response('ok'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($server, $request->attributes->get('server'));
    }

    public function test_string_lookup_does_not_require_suspended_column(): void
    {
        Schema::dropIfExists('servers');
        Schema::create('servers', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid');
            $table->string('uuidShort');
            $table->unsignedInteger('owner_id');
        });

        Server::query()->create([
            'uuid' => '13a9a711-382f-4ce4-9aa9-ab62cbe498b7',
            'uuidShort' => '13a9a711',
            'owner_id' => 1,
        ]);

        $request = Request::create('/api/client/notur/acme/test/servers/13a9a711/status', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $request->setRouteResolver(fn () => new class {
            public function parameter(string $name): ?string
            {
                return $name === 'server' ? '13a9a711' : null;
            }
        });
        $request->setUserResolver(fn () => new class {
            public int $id = 99;
            public bool $root_admin = true;
        });

        $response = (new VerifyServerAccess())->handle($request, fn () => response('ok'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('13a9a711', $request->attributes->get('server')->uuidShort);
    }

    public function test_admin_request_extracts_uuid_from_object_route_parameter(): void
    {
        Schema::dropIfExists('servers');
        Schema::create('servers', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid');
            $table->string('uuidShort');
            $table->unsignedInteger('owner_id');
        });

        Server::query()->create([
            'uuid' => '13a9a711-382f-4ce4-9aa9-ab62cbe498b7',
            'uuidShort' => '13a9a711',
            'owner_id' => 1,
        ]);

        $boundServerPayload = (object) [
            'uuid' => '13a9a711-382f-4ce4-9aa9-ab62cbe498b7',
            'uuidShort' => '13a9a711',
            'name' => 'CS2',
        ];

        $request = Request::create('/api/client/notur/acme/test/servers/13a9a711/status', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $request->setRouteResolver(fn () => new class($boundServerPayload) {
            public function __construct(private readonly object $server) {}

            public function parameter(string $name): ?object
            {
                return $name === 'server' ? $this->server : null;
            }
        });
        $request->setUserResolver(fn () => new class {
            public int $id = 99;
            public bool $root_admin = true;
        });

        $response = (new VerifyServerAccess())->handle($request, fn () => response('ok'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('13a9a711', $request->attributes->get('server')->uuidShort);
    }
}
}
