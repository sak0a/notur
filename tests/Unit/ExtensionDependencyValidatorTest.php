<?php

declare(strict_types=1);

namespace Notur\Tests\Unit;

use Notur\DependencyResolver;
use Notur\Exceptions\DependencyResolutionException;
use Notur\ExtensionDependencyValidator;
use Notur\ExtensionManifest;
use PHPUnit\Framework\TestCase;

class ExtensionDependencyValidatorTest extends TestCase
{
    private ExtensionDependencyValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ExtensionDependencyValidator(new DependencyResolver());
    }

    public function test_orders_compatible_dependencies_using_composer_constraints(): void
    {
        $entries = $this->entries('acme/app', 'acme/core');
        $manifests = [
            'acme/app' => $this->manifest('acme/app', '1.0.0', ['acme/core' => '^1.2 || ^2.0']),
            'acme/core' => $this->manifest('acme/core', '2.3.0'),
        ];

        $this->assertSame(['acme/core', 'acme/app'], $this->validator->validate($entries, $manifests));
    }

    public function test_rejects_missing_dependency(): void
    {
        $this->expectException(DependencyResolutionException::class);
        $this->expectExceptionMessage("'acme/core' (^1.0), which is not installed. Install it first.");

        $this->validator->validate(
            $this->entries('acme/app'),
            ['acme/app' => $this->manifest('acme/app', '1.0.0', ['acme/core' => '^1.0'])],
        );
    }

    public function test_rejects_disabled_dependency(): void
    {
        $entries = $this->entries('acme/app', 'acme/core');
        $entries['acme/core']['enabled'] = false;

        $this->expectException(DependencyResolutionException::class);
        $this->expectExceptionMessage("'acme/core' (^1.0), which is disabled. Enable it first.");

        $this->validator->validate(
            $entries,
            ['acme/app' => $this->manifest('acme/app', '1.0.0', ['acme/core' => '^1.0'])],
        );
    }

    public function test_rejects_incompatible_dependency_version(): void
    {
        $this->expectException(DependencyResolutionException::class);
        $this->expectExceptionMessage("requires 'acme/core' ^1.0, but version 2.0.0 is installed");

        $this->validator->validate(
            $this->entries('acme/app', 'acme/core'),
            [
                'acme/app' => $this->manifest('acme/app', '1.0.0', ['acme/core' => '^1.0']),
                'acme/core' => $this->manifest('acme/core', '2.0.0'),
            ],
        );
    }

    public function test_rejects_incompatible_prerelease(): void
    {
        $this->expectException(DependencyResolutionException::class);
        $this->validator->validate(
            $this->entries('acme/app', 'acme/core'),
            [
                'acme/app' => $this->manifest('acme/app', '1.0.0', ['acme/core' => '2.0.0']),
                'acme/core' => $this->manifest('acme/core', '2.0.0-beta.1'),
            ],
        );
    }

    public function test_reports_invalid_constraint(): void
    {
        $this->expectException(DependencyResolutionException::class);
        $this->expectExceptionMessage("invalid version constraint 'not-a-constraint' for 'acme/core'");

        $this->validator->validate(
            $this->entries('acme/app', 'acme/core'),
            [
                'acme/app' => $this->manifest('acme/app', '1.0.0', ['acme/core' => 'not-a-constraint']),
                'acme/core' => $this->manifest('acme/core', '1.0.0'),
            ],
        );
    }

    public function test_rejects_cycle(): void
    {
        $this->expectException(DependencyResolutionException::class);
        $this->expectExceptionMessage('Circular dependency');

        $this->validator->validate(
            $this->entries('acme/app', 'acme/core'),
            [
                'acme/app' => $this->manifest('acme/app', '1.0.0', ['acme/core' => '^1.0']),
                'acme/core' => $this->manifest('acme/core', '1.0.0', ['acme/app' => '^1.0']),
            ],
        );
    }

    /** @return array<string, array{enabled: bool}> */
    private function entries(string ...$ids): array
    {
        return array_fill_keys($ids, ['enabled' => true]);
    }

    /** @param array<string, string> $dependencies */
    private function manifest(string $id, string $version, array $dependencies = []): ExtensionManifest
    {
        return ExtensionManifest::fromArray([
            'id' => $id,
            'name' => $id,
            'version' => $version,
            'dependencies' => $dependencies,
        ]);
    }
}
