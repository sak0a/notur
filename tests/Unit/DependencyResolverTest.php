<?php

declare(strict_types=1);

namespace Notur\Tests\Unit;

use Notur\DependencyResolver;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DependencyResolverTest extends TestCase
{
    private DependencyResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new DependencyResolver();
    }

    public function test_resolves_empty_graph(): void
    {
        $result = $this->resolver->resolve([]);
        $this->assertSame([], $result);
    }

    public function test_resolves_single_extension(): void
    {
        $result = $this->resolver->resolve([
            'acme/foo' => [],
        ]);

        $this->assertSame(['acme/foo'], $result);
    }

    public function test_resolves_independent_extensions(): void
    {
        $result = $this->resolver->resolve([
            'acme/foo' => [],
            'acme/bar' => [],
        ]);

        $this->assertCount(2, $result);
        $this->assertContains('acme/foo', $result);
        $this->assertContains('acme/bar', $result);
    }

    public function test_resolves_linear_dependency_chain(): void
    {
        $result = $this->resolver->resolve([
            'acme/c' => ['acme/b'],
            'acme/b' => ['acme/a'],
            'acme/a' => [],
        ]);

        $this->assertSame(['acme/a', 'acme/b', 'acme/c'], $result);
    }

    public function test_resolves_diamond_dependency(): void
    {
        $result = $this->resolver->resolve([
            'acme/d' => ['acme/b', 'acme/c'],
            'acme/b' => ['acme/a'],
            'acme/c' => ['acme/a'],
            'acme/a' => [],
        ]);

        $aIndex = array_search('acme/a', $result);
        $bIndex = array_search('acme/b', $result);
        $cIndex = array_search('acme/c', $result);
        $dIndex = array_search('acme/d', $result);

        $this->assertLessThan($bIndex, $aIndex);
        $this->assertLessThan($cIndex, $aIndex);
        $this->assertLessThan($dIndex, $bIndex);
        $this->assertLessThan($dIndex, $cIndex);
    }

    public function test_detects_circular_dependency(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Circular dependency');

        $this->resolver->resolve([
            'acme/a' => ['acme/b'],
            'acme/b' => ['acme/a'],
        ]);
    }

    public function test_detects_self_dependency(): void
    {
        $this->expectException(RuntimeException::class);

        $this->resolver->resolve([
            'acme/a' => ['acme/a'],
        ]);
    }

    public function test_ignores_missing_dependencies(): void
    {
        // Dependencies not in the graph are skipped
        $result = $this->resolver->resolve([
            'acme/foo' => ['acme/not-installed'],
        ]);

        $this->assertSame(['acme/foo'], $result);
    }

    public function test_find_missing_dependencies(): void
    {
        $missing = $this->resolver->findMissing([
            'acme/foo' => ['acme/bar', 'acme/baz'],
            'acme/bar' => [],
        ]);

        $this->assertArrayHasKey('acme/foo', $missing);
        $this->assertSame(['acme/baz'], $missing['acme/foo']);
    }

    public function test_find_missing_returns_empty_when_all_present(): void
    {
        $missing = $this->resolver->findMissing([
            'acme/foo' => ['acme/bar'],
            'acme/bar' => [],
        ]);

        $this->assertEmpty($missing);
    }
}
