<?php

declare(strict_types=1);

namespace Notur;

use Notur\Exceptions\DependencyResolutionException;

class DependencyResolver
{
    /**
     * Resolve extension load order via topological sort.
     *
     * @param array<string, array<string>> $graph Extension IDs mapped to their dependency IDs.
     * @return array<string> Sorted extension IDs in load order.
     *
     * @throws DependencyResolutionException On circular dependency.
     */
    public function resolve(array $graph): array
    {
        foreach ($this->findMissing($graph) as $extensionId => $dependencies) {
            throw new DependencyResolutionException(
                "Extension '{$extensionId}' requires missing extension(s): " . implode(', ', $dependencies) . '. Install and enable them first.'
            );
        }

        $sorted = [];
        $visited = [];
        $visiting = [];

        foreach (array_keys($graph) as $node) {
            if (!isset($visited[$node])) {
                $this->visit($node, $graph, $visited, $visiting, $sorted);
            }
        }

        return $sorted;
    }

    private function visit(
        string $node,
        array $graph,
        array &$visited,
        array &$visiting,
        array &$sorted,
    ): void {
        if (isset($visiting[$node])) {
            $path = array_keys($visiting);
            $cycle = array_slice($path, array_search($node, $path, true));
            $cycle[] = $node;
            throw new DependencyResolutionException(
                'Circular dependency detected: ' . implode(' -> ', $cycle) . '. Remove one dependency in this cycle.'
            );
        }

        if (isset($visited[$node])) {
            return;
        }

        $visiting[$node] = true;

        foreach ($graph[$node] ?? [] as $dependency) {
            $this->visit($dependency, $graph, $visited, $visiting, $sorted);
        }

        unset($visiting[$node]);
        $visited[$node] = true;
        $sorted[] = $node;
    }

    /**
     * Check if all required dependencies are present.
     *
     * @param array<string, array<string>> $graph
     * @return array<string, array<string>> Missing dependencies keyed by extension ID.
     */
    public function findMissing(array $graph): array
    {
        $missing = [];
        $available = array_keys($graph);

        foreach ($graph as $extensionId => $dependencies) {
            foreach ($dependencies as $dep) {
                if (!in_array($dep, $available, true)) {
                    $missing[$extensionId][] = $dep;
                }
            }
        }

        return $missing;
    }
}
