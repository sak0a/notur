<?php

declare(strict_types=1);

namespace Notur\Tests\Integration\Http;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\RedirectResponse;
use Mockery;
use Notur\ExtensionManager;
use Notur\Http\Controllers\ExtensionAdminController;
use Notur\NoturServiceProvider;
use Notur\Support\SystemDiagnostics;
use Orchestra\Testbench\TestCase;

class ExtensionAdminControllerTest extends TestCase
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
        $app['config']->set('cache.default', 'array');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_remove_redirects_with_success_when_artisan_command_succeeds(): void
    {
        $manager = Mockery::mock(ExtensionManager::class);

        $controller = new class($manager) extends ExtensionAdminController {
            protected function runRemoveCommand(string $extensionId): array
            {
                return [
                    'exitCode' => 0,
                    'output' => '',
                ];
            }
        };

        $response = $controller->remove('acme/test');

        $this->assertRemovalRedirect($response);
        $this->assertSame("Extension 'acme/test' has been removed.", $response->getSession()->get('success'));
        $this->assertNull($response->getSession()->get('error'));
    }

    public function test_remove_redirects_with_error_when_artisan_command_fails(): void
    {
        $manager = Mockery::mock(ExtensionManager::class);

        $controller = new class($manager) extends ExtensionAdminController {
            protected function runRemoveCommand(string $extensionId): array
            {
                return [
                    'exitCode' => 1,
                    'output' => "Extension '{$extensionId}' could not be removed.",
                ];
            }
        };

        $response = $controller->remove('acme/test');

        $this->assertRemovalRedirect($response);
        $this->assertSame("Removal failed: Extension 'acme/test' could not be removed.", $response->getSession()->get('error'));
        $this->assertNull($response->getSession()->get('success'));
    }

    public function test_run_remove_command_uses_force_for_non_interactive_web_removal(): void
    {
        $manager = Mockery::mock(ExtensionManager::class);
        $fakeArtisan = new class {
            public array $calls = [];

            public function call(string $command, array $parameters = []): int
            {
                $this->calls[] = [$command, $parameters];

                return 0;
            }

            public function output(): string
            {
                return '';
            }
        };

        Artisan::swap($fakeArtisan);

        $controller = new class($manager) extends ExtensionAdminController {
            public function invokeRunRemoveCommand(string $extensionId): array
            {
                return $this->runRemoveCommand($extensionId);
            }
        };

        $result = $controller->invokeRunRemoveCommand('acme/test');

        $this->assertSame(['exitCode' => 0, 'output' => ''], $result);
        $this->assertSame([[
            'notur:remove',
            [
                'extension' => 'acme/test',
                '--force' => true,
                '--no-interaction' => true,
            ],
        ]], $fakeArtisan->calls);
    }

    public function test_run_registry_sync_command_forces_refresh(): void
    {
        $manager = Mockery::mock(ExtensionManager::class);
        $fakeArtisan = new class {
            public array $calls = [];

            public function call(string $command, array $parameters = []): int
            {
                $this->calls[] = [$command, $parameters];

                return 0;
            }

            public function output(): string
            {
                return 'Registry synced.';
            }
        };

        Artisan::swap($fakeArtisan);

        $controller = new class($manager) extends ExtensionAdminController {
            public function invokeRunRegistrySyncCommand(): array
            {
                return $this->runRegistrySyncCommand();
            }
        };

        $result = $controller->invokeRunRegistrySyncCommand();

        $this->assertSame(['exitCode' => 0, 'output' => 'Registry synced.'], $result);
        $this->assertSame([[
            'notur:registry:sync',
            ['--force' => true],
        ]], $fakeArtisan->calls);
    }

    public function test_run_update_command_reinstalls_extension_with_force(): void
    {
        $manager = Mockery::mock(ExtensionManager::class);
        $fakeArtisan = new class {
            public array $calls = [];

            public function call(string $command, array $parameters = []): int
            {
                $this->calls[] = [$command, $parameters];

                return 0;
            }

            public function output(): string
            {
                return 'Extension updated.';
            }
        };

        Artisan::swap($fakeArtisan);

        $controller = new class($manager) extends ExtensionAdminController {
            public function invokeRunUpdateCommand(string $extensionId): array
            {
                return $this->runUpdateCommand($extensionId);
            }
        };

        $result = $controller->invokeRunUpdateCommand('notur/cs2-modframework');

        $this->assertSame(['exitCode' => 0, 'output' => 'Extension updated.'], $result);
        $this->assertSame([[
            'notur:add',
            [
                'extension' => 'notur/cs2-modframework',
                '--force' => true,
            ],
        ]], $fakeArtisan->calls);
    }

    public function test_update_notur_runs_self_update_when_packagist_reports_newer_version(): void
    {
        Http::fake([
            'repo.packagist.org/*' => Http::response([
                'packages' => [
                    'notur/notur' => [
                        ['version' => '99.0.0', 'version_normalized' => '99.0.0.0'],
                    ],
                ],
            ], 200),
        ]);

        $manager = Mockery::mock(ExtensionManager::class);
        $fakeArtisan = new class {
            public array $calls = [];

            public function call(string $command, array $parameters = []): int
            {
                $this->calls[] = [$command, $parameters];

                return 0;
            }

            public function output(): string
            {
                return 'Caches cleared.';
            }
        };

        Artisan::swap($fakeArtisan);

        $controller = new class($manager) extends ExtensionAdminController {
            public ?string $requestedVersion = null;

            protected function runNoturSelfUpdateCommand(string $latestVersion): array
            {
                $this->requestedVersion = $latestVersion;

                return [
                    'exitCode' => 0,
                    'output' => 'Composer updated Notur.',
                ];
            }
        };

        $response = $controller->updateNotur($this->app->make(SystemDiagnostics::class));

        $this->assertSame(route('admin.notur.diagnostics'), $response->getTargetUrl());
        $this->assertSame('99.0.0', $controller->requestedVersion);
        $this->assertStringContainsString('Notur updated to v99.0.0', (string) $response->getSession()->get('success'));
        $this->assertSame([['optimize:clear', []]], $fakeArtisan->calls);
    }

    private function assertRemovalRedirect(RedirectResponse $response): void
    {
        $this->assertSame(route('admin.notur.extensions'), $response->getTargetUrl());
        $this->assertNotNull($response->getSession());
    }
}
