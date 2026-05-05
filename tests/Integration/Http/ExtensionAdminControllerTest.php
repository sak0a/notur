<?php

declare(strict_types=1);

namespace Notur\Tests\Integration\Http;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\RedirectResponse;
use Mockery;
use Notur\ExtensionManager;
use Notur\Http\Controllers\ExtensionAdminController;
use Notur\NoturServiceProvider;
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

    private function assertRemovalRedirect(RedirectResponse $response): void
    {
        $this->assertSame(route('admin.notur.extensions'), $response->getTargetUrl());
        $this->assertNotNull($response->getSession());
    }
}
