<?php

declare(strict_types=1);

namespace Notur\Tests\Unit\Console;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Notur\Console\Commands\DevPullCommand;
use Notur\NoturServiceProvider;
use Orchestra\Testbench\TestCase;

class DevPullCommandTest extends TestCase
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
        $app['config']->set('notur.repository', 'sak0a/notur');
    }

    public function test_url_encodes_branch_names_with_slashes(): void
    {
        // Create a mock handler to capture HTTP requests
        $container = [];
        $history = Middleware::history($container);
        
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'sha' => 'abc123def456',
                'commit' => [
                    'message' => 'Test commit',
                    'author' => [
                        'name' => 'Test Author',
                        'date' => '2026-02-07T12:00:00Z',
                    ],
                ],
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($history);

        // Test with a branch name containing slashes
        $client = new Client(['handler' => $handlerStack]);
        
        $command = new DevPullCommand();
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('fetchCommitInfo');
        $method->setAccessible(true);

        $method->invoke($command, $client, 'sak0a/notur', 'feature/my-branch');

        // Verify the URL was properly encoded
        $this->assertCount(1, $container);
        $request = $container[0]['request'];
        $uri = (string) $request->getUri();
        
        // The branch name should be URL-encoded: feature/my-branch -> feature%2Fmy-branch
        $this->assertStringContainsString('/commits/feature%2Fmy-branch', $uri);
        $this->assertStringNotContainsString('/commits/feature/my-branch', $uri);
    }

    public function test_url_encodes_branch_names_with_special_characters(): void
    {
        // Create a mock handler
        $container = [];
        $history = Middleware::history($container);
        
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'sha' => 'xyz789abc',
                'commit' => [
                    'message' => 'Another test',
                    'author' => [
                        'name' => 'Test Author',
                        'date' => '2026-02-07T12:00:00Z',
                    ],
                ],
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($history);

        $client = new Client(['handler' => $handlerStack]);
        
        $command = new DevPullCommand();
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('fetchCommitInfo');
        $method->setAccessible(true);

        // Test with a branch name containing spaces and special characters
        $method->invoke($command, $client, 'sak0a/notur', 'feature/my branch-v2');

        $this->assertCount(1, $container);
        $request = $container[0]['request'];
        $uri = (string) $request->getUri();
        
        // The branch name should be URL-encoded
        $this->assertStringContainsString('/commits/feature%2Fmy%20branch-v2', $uri);
    }

    public function test_handles_simple_branch_names_correctly(): void
    {
        // Create a mock handler
        $container = [];
        $history = Middleware::history($container);
        
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'sha' => '123abc456',
                'commit' => [
                    'message' => 'Simple test',
                    'author' => [
                        'name' => 'Test Author',
                        'date' => '2026-02-07T12:00:00Z',
                    ],
                ],
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($history);

        $client = new Client(['handler' => $handlerStack]);
        
        $command = new DevPullCommand();
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('fetchCommitInfo');
        $method->setAccessible(true);

        // Test with a simple branch name without special characters
        $method->invoke($command, $client, 'sak0a/notur', 'main');

        $this->assertCount(1, $container);
        $request = $container[0]['request'];
        $uri = (string) $request->getUri();
        
        // Simple branch names should still work
        $this->assertStringContainsString('/commits/main', $uri);
    }

    public function test_handles_commit_sha(): void
    {
        // Create a mock handler
        $container = [];
        $history = Middleware::history($container);
        
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'sha' => 'abc123def456789',
                'commit' => [
                    'message' => 'SHA test',
                    'author' => [
                        'name' => 'Test Author',
                        'date' => '2026-02-07T12:00:00Z',
                    ],
                ],
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($history);

        $client = new Client(['handler' => $handlerStack]);
        
        $command = new DevPullCommand();
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('fetchCommitInfo');
        $method->setAccessible(true);

        // Test with a commit SHA (no special characters)
        $method->invoke($command, $client, 'sak0a/notur', 'abc123def456789');

        $this->assertCount(1, $container);
        $request = $container[0]['request'];
        $uri = (string) $request->getUri();
        
        // Commit SHAs should work as expected
        $this->assertStringContainsString('/commits/abc123def456789', $uri);
    }
}
