<?php

declare(strict_types=1);

namespace Notur\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Notur\Support\RegistryClient;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RegistryClientTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/notur-registry-test-' . uniqid();
        mkdir($this->cacheDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->cacheDir);
    }

    private function sampleIndex(): array
    {
        return [
            'version' => '1.0',
            'updated_at' => '2025-01-01T00:00:00Z',
            'extensions' => [
                [
                    'id' => 'acme/hello-world',
                    'name' => 'Hello World',
                    'description' => 'A simple hello world extension',
                    'latest_version' => '1.2.2',
                    'repository' => 'https://github.com/acme/hello-world',
                    'tags' => ['demo', 'starter'],
                ],
                [
                    'id' => 'acme/server-stats',
                    'name' => 'Server Statistics',
                    'description' => 'Real-time server monitoring dashboard',
                    'latest_version' => '2.0.1',
                    'repository' => 'https://github.com/acme/server-stats',
                    'tags' => ['monitoring', 'dashboard'],
                ],
            ],
        ];
    }

    private function makeClient(array $responses): Client
    {
        $mock = new MockHandler($responses);
        $handler = HandlerStack::create($mock);
        return new Client(['handler' => $handler]);
    }

    public function test_fetch_index_returns_parsed_json(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode($this->sampleIndex())),
        ]);

        $registry = new RegistryClient($client);
        $index = $registry->fetchIndex();

        $this->assertSame('1.0', $index['version']);
        $this->assertCount(2, $index['extensions']);
    }

    public function test_fetch_index_caches_in_memory(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode($this->sampleIndex())),
            // Second call should not happen — only one response queued
        ]);

        $registry = new RegistryClient($client);
        $first = $registry->fetchIndex();
        $second = $registry->fetchIndex();

        $this->assertSame($first, $second);
    }

    public function test_fetch_index_throws_on_network_error(): void
    {
        $client = $this->makeClient([
            new ConnectException('Connection refused', new Request('GET', 'test')),
        ]);

        $registry = new RegistryClient($client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to fetch registry index');

        $registry->fetchIndex();
    }

    public function test_search_finds_by_id(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode($this->sampleIndex())),
        ]);

        $registry = new RegistryClient($client);
        $results = $registry->search('hello-world');

        $this->assertCount(1, $results);
        $this->assertSame('acme/hello-world', $results[0]['id']);
    }

    public function test_search_finds_by_description(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode($this->sampleIndex())),
        ]);

        $registry = new RegistryClient($client);
        $results = $registry->search('monitoring');

        $this->assertCount(1, $results);
        $this->assertSame('acme/server-stats', $results[0]['id']);
    }

    public function test_search_finds_by_tag(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode($this->sampleIndex())),
        ]);

        $registry = new RegistryClient($client);
        $results = $registry->search('dashboard');

        $this->assertCount(1, $results);
        $this->assertSame('acme/server-stats', $results[0]['id']);
    }

    public function test_search_returns_empty_for_no_match(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode($this->sampleIndex())),
        ]);

        $registry = new RegistryClient($client);
        $results = $registry->search('nonexistent-xyz');

        $this->assertEmpty($results);
    }

    public function test_get_extension_returns_metadata(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode($this->sampleIndex())),
        ]);

        $registry = new RegistryClient($client);
        $ext = $registry->getExtension('acme/server-stats');

        $this->assertNotNull($ext);
        $this->assertSame('Server Statistics', $ext['name']);
        $this->assertSame('2.0.1', $ext['latest_version']);
    }

    public function test_get_extension_returns_null_for_unknown(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode($this->sampleIndex())),
        ]);

        $registry = new RegistryClient($client);
        $ext = $registry->getExtension('unknown/ext');

        $this->assertNull($ext);
    }

    public function test_sync_to_cache_writes_file(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode($this->sampleIndex())),
        ]);

        $cachePath = $this->cacheDir . '/registry-cache.json';
        $registry = new RegistryClient($client);
        $count = $registry->syncToCache($cachePath);

        $this->assertSame(2, $count);
        $this->assertFileExists($cachePath);

        $cached = json_decode(file_get_contents($cachePath), true);
        $this->assertArrayHasKey('fetched_at', $cached);
        $this->assertArrayHasKey('registry', $cached);
        $this->assertCount(2, $cached['registry']['extensions']);
    }

    public function test_load_from_cache_returns_data(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode($this->sampleIndex())),
        ]);

        $cachePath = $this->cacheDir . '/registry-cache.json';
        $registry = new RegistryClient($client, cacheTtl: 3600);

        $registry->syncToCache($cachePath);
        $loaded = $registry->loadFromCache($cachePath);

        $this->assertNotNull($loaded);
        $this->assertCount(2, $loaded['extensions']);
    }

    public function test_load_from_cache_returns_null_when_expired(): void
    {
        $cachePath = $this->cacheDir . '/registry-cache.json';

        // Write cache with old timestamp
        $cacheData = [
            'fetched_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 7200),
            'registry' => $this->sampleIndex(),
        ];
        file_put_contents($cachePath, json_encode($cacheData));

        $client = $this->makeClient([]);
        $registry = new RegistryClient($client, cacheTtl: 3600);

        $loaded = $registry->loadFromCache($cachePath);
        $this->assertNull($loaded);
    }

    public function test_load_from_cache_ignores_expiry_when_requested(): void
    {
        $cachePath = $this->cacheDir . '/registry-cache.json';

        $cacheData = [
            'fetched_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 7200),
            'registry' => $this->sampleIndex(),
        ];
        file_put_contents($cachePath, json_encode($cacheData));

        $client = $this->makeClient([]);
        $registry = new RegistryClient($client, cacheTtl: 3600);

        $loaded = $registry->loadFromCache($cachePath, ignoreExpiry: true);
        $this->assertNotNull($loaded);
    }

    public function test_load_from_cache_returns_null_for_missing_file(): void
    {
        $client = $this->makeClient([]);
        $registry = new RegistryClient($client);

        $this->assertNull($registry->loadFromCache('/nonexistent/path.json'));
    }

    public function test_download_throws_for_unknown_extension(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode($this->sampleIndex())),
        ]);

        $registry = new RegistryClient($client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("not found in registry");

        $registry->download('unknown/ext', '1.0.0', '/tmp/test.notur');
    }

    public function test_get_expected_archive_checksum_reads_string_value(): void
    {
        $index = $this->sampleIndex();
        $index['extensions'][0]['sha256'] = strtoupper(str_repeat('a1', 32));

        $client = $this->makeClient([
            new Response(200, [], json_encode($index)),
        ]);

        $registry = new RegistryClient($client);
        $checksum = $registry->getExpectedArchiveChecksum('acme/hello-world', '1.2.2');

        $this->assertSame(strtolower(str_repeat('a1', 32)), $checksum);
    }

    public function test_get_expected_archive_checksum_reads_versioned_value(): void
    {
        $index = $this->sampleIndex();
        $index['extensions'][0]['sha256'] = [
            '1.2.2' => strtoupper(str_repeat('b2', 32)),
        ];

        $client = $this->makeClient([
            new Response(200, [], json_encode($index)),
        ]);

        $registry = new RegistryClient($client);
        $checksum = $registry->getExpectedArchiveChecksum('acme/hello-world', '1.2.2');

        $this->assertSame(strtolower(str_repeat('b2', 32)), $checksum);
    }

    public function test_download_signature_writes_sidecar_file(): void
    {
        $target = $this->cacheDir . '/archive.notur.sig';
        $client = $this->makeClient([
            new Response(200, [], json_encode($this->sampleIndex())),
            new Response(200, [], 'signature-bytes'),
        ]);

        $registry = new RegistryClient($client);
        $registry->downloadSignature('acme/hello-world', '1.2.2', $target);

        $this->assertFileExists($target);
        $this->assertSame('signature-bytes', file_get_contents($target));
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
