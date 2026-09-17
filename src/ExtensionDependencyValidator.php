<?php

declare(strict_types=1);

namespace Notur;

use Composer\Semver\Semver;
use Notur\Exceptions\DependencyResolutionException;

/** Validates the proposed enabled extension set before it is committed or booted. */
class ExtensionDependencyValidator
{
    public function __construct(private readonly DependencyResolver $resolver) {}

    /**
     * @param array<string, array<string, mixed>> $entries Master manifest entries.
     * @param array<string, ExtensionManifest> $manifests Manifests read from installed files (or a proposed candidate).
     * @return array<string> Dependency-first load order.
     */
    public function validate(array $entries, array $manifests): array
    {
        $graph = [];

        foreach ($entries as $id => $entry) {
            if (!($entry['enabled'] ?? false)) {
                continue;
            }

            $graph[$id] = $this->validateRequirements($id, $entries, $manifests);
        }

        return $this->resolver->resolve($graph);
    }

    /**
     * Validate one extension's direct requirements. Boot calls this after its
     * dependencies have had a chance to load, so failures stay local.
     *
     * @param array<string, array<string, mixed>> $entries
     * @param array<string, ExtensionManifest> $manifests
     * @return array<string> Required extension IDs.
     */
    public function validateRequirements(string $id, array $entries, array $manifests): array
    {
        if (!isset($manifests[$id])) {
            throw new DependencyResolutionException(
                "Enabled extension '{$id}' has no readable manifest. Restore its files or disable it."
            );
        }
        if ($manifests[$id]->getId() !== $id) {
            throw new DependencyResolutionException(
                "Installed files for '{$id}' declare a different extension ID. Restore the correct manifest before continuing."
            );
        }

        $dependencies = [];
        foreach ($manifests[$id]->getDependencies() as $dependencyId => $constraint) {
            if (!isset($entries[$dependencyId])) {
                throw new DependencyResolutionException(
                    "Extension '{$id}' requires '{$dependencyId}' ({$constraint}), which is not installed. Install it first."
                );
            }
            if (!($entries[$dependencyId]['enabled'] ?? false)) {
                throw new DependencyResolutionException(
                    "Extension '{$id}' requires '{$dependencyId}' ({$constraint}), which is disabled. Enable it first."
                );
            }
            if (!isset($manifests[$dependencyId])) {
                throw new DependencyResolutionException(
                    "Extension '{$id}' requires '{$dependencyId}' ({$constraint}), but its manifest cannot be read. Restore its files."
                );
            }

            $version = $manifests[$dependencyId]->getVersion();
            try {
                $compatible = Semver::satisfies($version, (string) $constraint);
            } catch (\UnexpectedValueException | \InvalidArgumentException $e) {
                throw new DependencyResolutionException(
                    "Extension '{$id}' declares an invalid version constraint '{$constraint}' for '{$dependencyId}': {$e->getMessage()}"
                );
            }
            if (!$compatible) {
                throw new DependencyResolutionException(
                    "Extension '{$id}' requires '{$dependencyId}' {$constraint}, but version {$version} is installed. Install a compatible version or disable '{$id}' first."
                );
            }

            $dependencies[] = $dependencyId;
        }

        return $dependencies;
    }
}
