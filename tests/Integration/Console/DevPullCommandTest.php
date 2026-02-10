<?php

declare(strict_types=1);

namespace Notur\Tests\Integration\Console;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Notur\Console\Commands\DevPullCommand;
use Notur\NoturServiceProvider;
use Orchestra\Testbench\TestCase;

class DevPullCommandTest extends TestCase
{
    private string $originalPath;

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
        $app['config']->set('notur.repository', 'sak0a/notur');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
        $this->originalPath = (string) getenv('PATH');
    }

    protected function tearDown(): void
    {
        putenv("PATH={$this->originalPath}");
        Mockery::close();
        parent::tearDown();
    }

    public function test_package_manager_resolution_prefers_npm_then_yarn_then_pnpm_then_bun(): void
    {
        // Only yarn and bun available -> yarn is preferred
        $this->setPathToBinaries(['yarn', 'bun']);
        $this->assertSame('yarn', $this->invokeResolvePackageManager());

        // npm available -> it wins over all others
        $this->setPathToBinaries(['npm', 'yarn', 'pnpm', 'bun']);
        $this->assertSame('npm', $this->invokeResolvePackageManager());

        // pnpm available (without npm/yarn) -> pnpm selected before bun
        $this->setPathToBinaries(['pnpm', 'bun']);
        $this->assertSame('pnpm', $this->invokeResolvePackageManager());
    }

    public function test_package_manager_resolution_returns_null_when_no_supported_binary_is_available(): void
    {
        $this->setPathToBinaries([]);
        $this->assertNull($this->invokeResolvePackageManager());
    }

    public function test_dry_run_shows_what_would_be_done_without_making_changes(): void
    {
        // Mock the GitHub API response for commit info
        $mockClient = Mockery::mock(Client::class);
        
        $mockResponse = new Response(200, [], json_encode([
            'sha' => 'abc123def456abc123def456abc123def456abc1',
            'commit' => [
                'message' => 'Test commit message',
                'author' => [
                    'name' => 'Test Author',
                    'date' => '2024-01-15T10:30:00Z',
                ],
            ],
        ]));

        $mockClient->shouldReceive('get')
            ->once()
            ->with('https://api.github.com/repos/sak0a/notur/commits/master')
            ->andReturn($mockResponse);

        // Bind the mock client to the service container
        $this->app->bind(Client::class, function () use ($mockClient) {
            return $mockClient;
        });

        $this->artisan('notur:dev:pull', ['--dry-run' => true])
            ->expectsOutput('[DRY RUN] Would download and extract commit abc123de to ' . base_path('vendor/notur/notur'))
            ->expectsOutput('[DRY RUN] Would rebuild frontend bridge')
            ->expectsOutput('[DRY RUN] Would copy bridge.js and tailwind.css to public/notur/')
            ->assertExitCode(0);
    }

    public function test_dry_run_with_no_rebuild_option(): void
    {
        $mockClient = Mockery::mock(Client::class);
        
        $mockResponse = new Response(200, [], json_encode([
            'sha' => 'abc123def456abc123def456abc123def456abc1',
            'commit' => [
                'message' => 'Test commit message',
                'author' => [
                    'name' => 'Test Author',
                    'date' => '2024-01-15T10:30:00Z',
                ],
            ],
        ]));

        $mockClient->shouldReceive('get')
            ->once()
            ->with('https://api.github.com/repos/sak0a/notur/commits/master')
            ->andReturn($mockResponse);

        $this->app->bind(Client::class, function () use ($mockClient) {
            return $mockClient;
        });

        $this->artisan('notur:dev:pull', ['--dry-run' => true, '--no-rebuild' => true])
            ->expectsOutput('[DRY RUN] Would download and extract commit abc123de to ' . base_path('vendor/notur/notur'))
            ->doesntExpectOutput('[DRY RUN] Would rebuild frontend bridge')
            ->assertExitCode(0);
    }

    public function test_dry_run_with_specific_commit(): void
    {
        $mockClient = Mockery::mock(Client::class);
        
        $mockResponse = new Response(200, [], json_encode([
            'sha' => 'abcd1234567890abcdef1234567890abcdef1234',
            'commit' => [
                'message' => 'Specific commit message',
                'author' => [
                    'name' => 'Test Author',
                    'date' => '2024-01-15T10:30:00Z',
                ],
            ],
        ]));

        $mockClient->shouldReceive('get')
            ->once()
            ->with('https://api.github.com/repos/sak0a/notur/commits/specific123')
            ->andReturn($mockResponse);

        $this->app->bind(Client::class, function () use ($mockClient) {
            return $mockClient;
        });

        $this->artisan('notur:dev:pull', ['commit' => 'specific123', '--dry-run' => true])
            ->expectsOutput('[DRY RUN] Would download and extract commit abcd1234 to ' . base_path('vendor/notur/notur'))
            ->assertExitCode(0);
    }

    public function test_handles_invalid_ref_error(): void
    {
        $mockClient = Mockery::mock(Client::class);
        
        $request = new Request('GET', 'https://api.github.com/repos/sak0a/notur/commits/invalid-ref');
        $exception = new RequestException(
            'Not Found',
            $request,
            new Response(404, [], '{"message":"Not Found"}')
        );

        $mockClient->shouldReceive('get')
            ->once()
            ->with('https://api.github.com/repos/sak0a/notur/commits/invalid-ref')
            ->andThrow($exception);

        $this->app->bind(Client::class, function () use ($mockClient) {
            return $mockClient;
        });

        $this->artisan('notur:dev:pull', ['branch' => 'invalid-ref', '--dry-run' => true])
            ->expectsOutputToContain('Failed to fetch commit info')
            ->assertExitCode(1);
    }

    public function test_handles_network_error_on_commit_fetch(): void
    {
        $mockClient = Mockery::mock(Client::class);
        
        $request = new Request('GET', 'https://api.github.com/repos/sak0a/notur/commits/master');
        $exception = new RequestException(
            'Connection timeout',
            $request
        );

        $mockClient->shouldReceive('get')
            ->once()
            ->with('https://api.github.com/repos/sak0a/notur/commits/master')
            ->andThrow($exception);

        $this->app->bind(Client::class, function () use ($mockClient) {
            return $mockClient;
        });

        $this->artisan('notur:dev:pull', ['--dry-run' => true])
            ->expectsOutputToContain('Failed to fetch commit info')
            ->assertExitCode(1);
    }

    public function test_handles_malformed_api_response(): void
    {
        $mockClient = Mockery::mock(Client::class);
        
        // Response missing 'sha' field
        $mockResponse = new Response(200, [], json_encode([
            'commit' => [
                'message' => 'Test commit message',
            ],
        ]));

        $mockClient->shouldReceive('get')
            ->once()
            ->with('https://api.github.com/repos/sak0a/notur/commits/master')
            ->andReturn($mockResponse);

        $this->app->bind(Client::class, function () use ($mockClient) {
            return $mockClient;
        });

        $this->artisan('notur:dev:pull', ['--dry-run' => true])
            ->expectsOutputToContain('Failed to fetch commit info')
            ->assertExitCode(1);
    }

    public function test_displays_commit_information(): void
    {
        $mockClient = Mockery::mock(Client::class);
        
        $mockResponse = new Response(200, [], json_encode([
            'sha' => 'abc123def456abc123def456abc123def456abc1',
            'commit' => [
                'message' => "Add new feature\n\nDetailed description here",
                'author' => [
                    'name' => 'Jane Developer',
                    'date' => '2024-01-15T10:30:00Z',
                ],
            ],
        ]));

        $mockClient->shouldReceive('get')
            ->once()
            ->with('https://api.github.com/repos/sak0a/notur/commits/develop')
            ->andReturn($mockResponse);

        $this->app->bind(Client::class, function () use ($mockClient) {
            return $mockClient;
        });

        $this->artisan('notur:dev:pull', ['branch' => 'develop', '--dry-run' => true])
            ->expectsOutput('  Branch:  develop')
            ->expectsOutput('  Commit:  abc123de')
            ->expectsOutput('  Author:  Jane Developer')
            ->expectsOutput('  Date:    2024-01-15T10:30:00Z')
            ->expectsOutput('  Message: Add new feature')
            ->assertExitCode(0);
    }

    public function test_uses_custom_repository_from_config(): void
    {
        config(['notur.repository' => 'custom/repo']);

        $mockClient = Mockery::mock(Client::class);
        
        $mockResponse = new Response(200, [], json_encode([
            'sha' => 'abc123def456abc123def456abc123def456abc1',
            'commit' => [
                'message' => 'Test commit',
                'author' => [
                    'name' => 'Test Author',
                    'date' => '2024-01-15T10:30:00Z',
                ],
            ],
        ]));

        // Should use custom/repo instead of default
        $mockClient->shouldReceive('get')
            ->once()
            ->with('https://api.github.com/repos/custom/repo/commits/master')
            ->andReturn($mockResponse);

        $this->app->bind(Client::class, function () use ($mockClient) {
            return $mockClient;
        });

        $this->artisan('notur:dev:pull', ['--dry-run' => true])
            ->assertExitCode(0);
    }

    private function invokeResolvePackageManager(): ?string
    {
        $command = app(DevPullCommand::class);
        $method = new \ReflectionMethod(DevPullCommand::class, 'resolvePackageManager');
        $method->setAccessible(true);

        $result = $method->invoke($command);

        return is_string($result) ? $result : null;
    }

    /**
     * @param array<int, string> $binaries
     */
    private function setPathToBinaries(array $binaries): void
    {
        $binDir = sys_get_temp_dir() . '/notur-dev-pull-bin-' . uniqid('', true);
        mkdir($binDir, 0755, true);

        foreach ($binaries as $binary) {
            $path = $binDir . DIRECTORY_SEPARATOR . $binary;
            file_put_contents($path, "#!/bin/sh\nexit 0\n");
            chmod($path, 0755);
        }

        putenv("PATH={$binDir}");
    }
}
