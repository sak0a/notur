<?php

declare(strict_types=1);

namespace Notur\Tests\Unit;

use Notur\Support\ExtensionStateStore;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ExtensionStateStoreTest extends TestCase
{
    private string $dir;
    private ExtensionStateStore $store;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/notur-state-' . bin2hex(random_bytes(8));
        mkdir($this->dir);
        $this->store = new ExtensionStateStore($this->dir . '/extensions.json');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    public function test_concurrent_mutations_preserve_every_entry(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl is required for the process concurrency test.');
        }

        $children = [];
        for ($worker = 0; $worker < 4; $worker++) {
            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid);
            if ($pid === 0) {
                try {
                    for ($i = 0; $i < 20; $i++) {
                        $id = "worker{$worker}/extension{$i}";
                        $this->store->update(function (array $state) use ($id): array {
                            $state['extensions'][$id] = ['version' => '1.0.0', 'enabled' => true];
                            return $state;
                        }, static function (): void {});
                    }
                    exit(0);
                } catch (\Throwable) {
                    exit(1);
                }
            }
            $children[] = $pid;
        }

        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            $this->assertTrue(pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0);
        }

        $state = $this->store->reconcile(static function (): void {});
        $this->assertCount(80, $state['extensions']);
        $this->assertCount(80, json_decode(file_get_contents($this->dir . '/extensions.json'), true, 512, JSON_THROW_ON_ERROR)['extensions']);
    }

    public function test_failed_projection_is_recovered_from_committed_json(): void
    {
        $mirror = [];
        try {
            $this->store->update(function (array $state): array {
                $state['extensions']['acme/example'] = ['version' => '2.0.0', 'enabled' => false];
                return $state;
            }, static function (): void {
                throw new RuntimeException('database unavailable');
            });
            $this->fail('Projection should fail.');
        } catch (RuntimeException $e) {
            $this->assertSame('database unavailable', $e->getMessage());
        }

        $state = $this->store->reconcile(function (array $state) use (&$mirror): void {
            $mirror = $state['extensions'];
        });
        $this->assertSame($state['extensions'], $mirror);
        $this->assertFalse($mirror['acme/example']['enabled']);
    }

    public function test_invalid_json_is_rejected_without_overwrite(): void
    {
        $path = $this->dir . '/extensions.json';
        file_put_contents($path, '{broken');

        $this->expectException(RuntimeException::class);
        try {
            $this->store->update(static fn (array $state): array => $state, static function (): void {});
        } finally {
            $this->assertSame('{broken', file_get_contents($path));
        }
    }

    public function test_failed_encoding_preserves_previous_state_and_skips_projection(): void
    {
        $this->store->update(function (array $state): array {
            $state['extensions']['acme/one'] = ['version' => '1.0.0', 'enabled' => true];
            return $state;
        }, static function (): void {});
        $before = file_get_contents($this->dir . '/extensions.json');
        $projected = false;

        try {
            $this->store->update(function (array $state): array {
                $state['invalid_number'] = INF;
                return $state;
            }, static function () use (&$projected): void {
                $projected = true;
            });
            $this->fail('Encoding should fail.');
        } catch (RuntimeException $e) {
            $this->assertSame('Could not encode extension state.', $e->getMessage());
        }

        $this->assertSame($before, file_get_contents($this->dir . '/extensions.json'));
        $this->assertFalse($projected);
    }

    public function test_empty_state_round_trips_as_an_object(): void
    {
        $this->store->update(static fn (array $state): array => $state, static function (): void {});
        $this->assertSame(['extensions' => []], $this->store->reconcile(static function (): void {}));
        $this->assertSame('{', substr(trim(file_get_contents($this->dir . '/extensions.json')), 0, 1));
        $this->assertStringContainsString('"extensions": {}', file_get_contents($this->dir . '/extensions.json'));
    }

    public function test_reconcile_without_manifest_does_not_create_parent_or_lock(): void
    {
        $uninitialized = $this->dir . '/uninitialized';
        $store = new ExtensionStateStore($uninitialized . '/extensions.json');
        $this->assertNull($store->reconcile(static function (): void {
            throw new RuntimeException('Nothing should be projected.');
        }));
        $this->assertDirectoryDoesNotExist($uninitialized);

        $this->assertNull($this->store->reconcile(static function (): void {
            throw new RuntimeException('Nothing should be projected.');
        }));
        $this->assertFileDoesNotExist($this->dir . '/extensions.json.lock');
    }
}
