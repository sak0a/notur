<?php

declare(strict_types=1);

namespace Notur\Tests\Unit;

use Notur\Support\SchemaValidator;
use PHPUnit\Framework\TestCase;

class SchemaValidatorTest extends TestCase
{
    public function test_valid_manifest_passes(): void
    {
        $manifest = [
            'id' => 'acme/test',
            'name' => 'Test Extension',
            'version' => '1.0.0',
            'entrypoint' => 'Acme\\Test\\TestExtension',
            'description' => 'A test extension',
        ];

        $errors = SchemaValidator::validateManifest($manifest);
        $this->assertEmpty($errors, implode("\n", $errors));
        $this->assertTrue(SchemaValidator::isValidManifest($manifest));
    }

    public function test_manifest_missing_required_id(): void
    {
        $manifest = [
            'name' => 'Test',
            'version' => '1.0.0',
            'entrypoint' => 'Foo\\Bar',
        ];

        $errors = SchemaValidator::validateManifest($manifest);
        $this->assertNotEmpty($errors);
        $this->assertFalse(SchemaValidator::isValidManifest($manifest));

        $hasIdError = false;
        foreach ($errors as $error) {
            if (str_contains($error, "'id'")) {
                $hasIdError = true;
            }
        }
        $this->assertTrue($hasIdError, 'Should report missing id field');
    }

    public function test_manifest_missing_required_entrypoint(): void
    {
        $manifest = [
            'id' => 'acme/test',
            'name' => 'Test',
            'version' => '1.0.0',
        ];

        $errors = SchemaValidator::validateManifest($manifest);
        $this->assertNotEmpty($errors);

        $hasEntrypointError = false;
        foreach ($errors as $error) {
            if (str_contains($error, "'entrypoint'")) {
                $hasEntrypointError = true;
            }
        }
        $this->assertTrue($hasEntrypointError, 'Should report missing entrypoint field');
    }

    public function test_manifest_invalid_id_pattern(): void
    {
        $manifest = [
            'id' => 'INVALID_ID',
            'name' => 'Test',
            'version' => '1.0.0',
            'entrypoint' => 'Foo\\Bar',
        ];

        $errors = SchemaValidator::validateManifest($manifest);
        $this->assertNotEmpty($errors);

        $hasPatternError = false;
        foreach ($errors as $error) {
            if (str_contains($error, 'pattern')) {
                $hasPatternError = true;
            }
        }
        $this->assertTrue($hasPatternError, 'Should report pattern mismatch for id');
    }

    public function test_manifest_with_full_structure(): void
    {
        $manifest = [
            'notur' => '1.0',
            'id' => 'acme/full-ext',
            'name' => 'Full Extension',
            'version' => '2.0.0',
            'description' => 'A fully featured extension',
            'entrypoint' => 'Acme\\Full\\Extension',
            'license' => 'MIT',
            'authors' => [
                ['name' => 'John Doe', 'email' => 'john@example.com'],
            ],
            'requires' => [
                'notur' => '^1.0',
                'php' => '^8.2',
            ],
            'dependencies' => [
                'acme/other' => '^1.0',
            ],
            'autoload' => [
                'psr-4' => ['Acme\\Full\\' => 'src/'],
            ],
            'backend' => [
                'routes' => ['api-client' => 'routes/api.php'],
                'migrations' => 'database/migrations',
                'permissions' => ['full.view', 'full.edit'],
            ],
            'frontend' => [
                'bundle' => 'dist/bundle.js',
                'styles' => 'dist/styles.css',
            ],
        ];

        $errors = SchemaValidator::validateManifest($manifest);
        $this->assertEmpty($errors, implode("\n", $errors));
    }

    public function test_valid_registry_index_passes(): void
    {
        $index = [
            'version' => '1.0',
            'extensions' => [
                [
                    'id' => 'acme/test',
                    'name' => 'Test',
                    'repository' => 'https://github.com/acme/test',
                ],
            ],
        ];

        $errors = SchemaValidator::validateRegistryIndex($index);
        $this->assertEmpty($errors, implode("\n", $errors));
        $this->assertTrue(SchemaValidator::isValidRegistryIndex($index));
    }

    public function test_registry_index_missing_version(): void
    {
        $index = [
            'extensions' => [],
        ];

        $errors = SchemaValidator::validateRegistryIndex($index);
        $this->assertNotEmpty($errors);
        $this->assertFalse(SchemaValidator::isValidRegistryIndex($index));
    }

    public function test_registry_index_missing_extensions(): void
    {
        $index = [
            'version' => '1.0',
        ];

        $errors = SchemaValidator::validateRegistryIndex($index);
        $this->assertNotEmpty($errors);
    }

    public function test_full_registry_index(): void
    {
        $index = [
            'version' => '1.0',
            'updated_at' => '2025-01-01T00:00:00Z',
            'extensions' => [
                [
                    'id' => 'acme/hello',
                    'name' => 'Hello World',
                    'description' => 'A demo extension',
                    'repository' => 'https://github.com/acme/hello',
                    'latest_version' => '1.0.0',
                    'versions' => ['1.0.0', '0.9.0'],
                    'license' => 'MIT',
                    'authors' => [['name' => 'Jane']],
                    'tags' => ['demo'],
                ],
            ],
        ];

        $errors = SchemaValidator::validateRegistryIndex($index);
        $this->assertEmpty($errors, implode("\n", $errors));
    }
}
