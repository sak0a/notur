<?php

declare(strict_types=1);

namespace Notur\Tests\Unit\Console;

use Notur\Console\Commands\DevPullCommand;
use Orchestra\Testbench\TestCase;
use ReflectionClass;

class DevPullCommandTest extends TestCase
{
    private function invokePrivateMethod(object $object, string $methodName, array $args = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }

    public function testIsUnsafePathDetectsAbsolutePaths(): void
    {
        $command = new DevPullCommand();

        // Unix absolute paths
        $this->assertTrue($this->invokePrivateMethod($command, 'isUnsafePath', ['/etc/passwd']));
        $this->assertTrue($this->invokePrivateMethod($command, 'isUnsafePath', ['/tmp/evil']));

        // Windows absolute paths
        $this->assertTrue($this->invokePrivateMethod($command, 'isUnsafePath', ['C:/Windows/System32']));
        $this->assertTrue($this->invokePrivateMethod($command, 'isUnsafePath', ['D:/data']));
    }

    public function testIsUnsafePathDetectsParentDirectoryTraversal(): void
    {
        $command = new DevPullCommand();

        $this->assertTrue($this->invokePrivateMethod($command, 'isUnsafePath', ['../etc/passwd']));
        $this->assertTrue($this->invokePrivateMethod($command, 'isUnsafePath', ['foo/../../etc/passwd']));
        $this->assertTrue($this->invokePrivateMethod($command, 'isUnsafePath', ['foo/../../../bar']));
        $this->assertTrue($this->invokePrivateMethod($command, 'isUnsafePath', ['..']));
    }

    public function testIsUnsafePathDetectsNullBytes(): void
    {
        $command = new DevPullCommand();

        $this->assertTrue($this->invokePrivateMethod($command, 'isUnsafePath', ["foo\0bar"]));
        $this->assertTrue($this->invokePrivateMethod($command, 'isUnsafePath', ["dir/file\0.txt"]));
    }

    public function testIsUnsafePathAllowsSafePaths(): void
    {
        $command = new DevPullCommand();

        $this->assertFalse($this->invokePrivateMethod($command, 'isUnsafePath', ['src/Console/Commands/DevPullCommand.php']));
        $this->assertFalse($this->invokePrivateMethod($command, 'isUnsafePath', ['foo/bar/baz.txt']));
        $this->assertFalse($this->invokePrivateMethod($command, 'isUnsafePath', ['file.txt']));
        $this->assertFalse($this->invokePrivateMethod($command, 'isUnsafePath', ['a/b/c/d/e.json']));
    }

    public function testNormalizePathResolvesRelativeComponents(): void
    {
        $command = new DevPullCommand();

        $this->assertEquals(
            'foo' . DIRECTORY_SEPARATOR . 'bar',
            $this->invokePrivateMethod($command, 'normalizePath', ['foo/./bar'])
        );

        $this->assertEquals(
            'foo' . DIRECTORY_SEPARATOR . 'baz',
            $this->invokePrivateMethod($command, 'normalizePath', ['foo/bar/../baz'])
        );

        $this->assertEquals(
            'baz',
            $this->invokePrivateMethod($command, 'normalizePath', ['foo/bar/../../baz'])
        );
    }

    public function testNormalizePathPreservesLeadingSlash(): void
    {
        $command = new DevPullCommand();

        $result = $this->invokePrivateMethod($command, 'normalizePath', ['/foo/bar']);
        $this->assertTrue(str_starts_with($result, DIRECTORY_SEPARATOR));
    }

    public function testNormalizePathRejectsPathsEscapingRoot(): void
    {
        $command = new DevPullCommand();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('attempts to traverse above root');
        $this->invokePrivateMethod($command, 'normalizePath', ['../../etc/passwd']);
    }

    public function testNormalizePathRejectsMultipleEscapeAttempts(): void
    {
        $command = new DevPullCommand();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('attempts to traverse above root');
        $this->invokePrivateMethod($command, 'normalizePath', ['../../../system']);
    }
}
