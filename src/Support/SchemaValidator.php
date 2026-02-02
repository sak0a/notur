<?php

declare(strict_types=1);

namespace Notur\Support;

use RuntimeException;

/**
 * Validates data structures against JSON schemas for:
 * - Extension manifests (extension.yaml structure)
 * - Registry index (registry.json structure)
 */
class SchemaValidator
{
    private const SCHEMAS_DIR = __DIR__ . '/../../registry/schema';

    /**
     * Validate an extension manifest array against the schema.
     *
     * @param array<string, mixed> $data The parsed manifest data.
     * @return array<int, string> List of validation errors (empty if valid).
     */
    public static function validateManifest(array $data): array
    {
        $schema = self::loadSchema('extension-manifest.schema.json');
        return self::validate($data, $schema);
    }

    /**
     * Validate a registry index array against the schema.
     *
     * @param array<string, mixed> $data The parsed registry index data.
     * @return array<int, string> List of validation errors (empty if valid).
     */
    public static function validateRegistryIndex(array $data): array
    {
        $schema = self::loadSchema('registry-index.schema.json');
        return self::validate($data, $schema);
    }

    /**
     * Check if a manifest is valid (convenience boolean wrapper).
     */
    public static function isValidManifest(array $data): bool
    {
        return empty(self::validateManifest($data));
    }

    /**
     * Check if a registry index is valid (convenience boolean wrapper).
     */
    public static function isValidRegistryIndex(array $data): bool
    {
        return empty(self::validateRegistryIndex($data));
    }

    /**
     * Load a JSON schema file.
     *
     * @return array<string, mixed>
     * @throws RuntimeException If the schema file cannot be loaded.
     */
    private static function loadSchema(string $filename): array
    {
        $path = self::SCHEMAS_DIR . '/' . $filename;

        if (!file_exists($path)) {
            throw new RuntimeException("Schema file not found: {$path}");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("Cannot read schema file: {$path}");
        }

        $schema = json_decode($content, true);
        if (!is_array($schema)) {
            throw new RuntimeException("Invalid JSON in schema file: {$path}");
        }

        return $schema;
    }

    /**
     * Validate data against a JSON schema (simplified implementation).
     *
     * This implements a subset of JSON Schema Draft-07 sufficient for
     * validating Notur manifests and registry indexes without requiring
     * an external library dependency.
     *
     * @return array<int, string> List of validation errors.
     */
    private static function validate(array $data, array $schema, string $path = '$'): array
    {
        $errors = [];

        // Check type
        if (isset($schema['type'])) {
            if (!self::checkType($data, $schema['type'])) {
                $errors[] = "{$path}: expected type '{$schema['type']}'";
                return $errors;
            }
        }

        // Check required properties
        if (isset($schema['required']) && is_array($schema['required'])) {
            foreach ($schema['required'] as $requiredField) {
                if (!array_key_exists($requiredField, $data)) {
                    $errors[] = "{$path}: missing required property '{$requiredField}'";
                }
            }
        }

        // Check properties
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $propName => $propSchema) {
                if (!array_key_exists($propName, $data)) {
                    continue;
                }

                $propPath = "{$path}.{$propName}";
                $value = $data[$propName];

                // Type check for scalar values
                if (isset($propSchema['type'])) {
                    $propType = $propSchema['type'];

                    if ($propType === 'string' && !is_string($value)) {
                        $errors[] = "{$propPath}: expected string";
                    } elseif ($propType === 'integer' && !is_int($value)) {
                        $errors[] = "{$propPath}: expected integer";
                    } elseif ($propType === 'number' && !is_numeric($value)) {
                        $errors[] = "{$propPath}: expected number";
                    } elseif ($propType === 'boolean' && !is_bool($value)) {
                        $errors[] = "{$propPath}: expected boolean";
                    } elseif ($propType === 'array' && !is_array($value)) {
                        $errors[] = "{$propPath}: expected array";
                    } elseif ($propType === 'object' && !is_array($value)) {
                        $errors[] = "{$propPath}: expected object";
                    }

                    // Validate nested objects recursively
                    if ($propType === 'object' && is_array($value)) {
                        $errors = array_merge($errors, self::validate($value, $propSchema, $propPath));
                    }

                    // Validate array items
                    if ($propType === 'array' && is_array($value) && isset($propSchema['items'])) {
                        foreach ($value as $i => $item) {
                            $itemPath = "{$propPath}[{$i}]";
                            if (is_array($item) && isset($propSchema['items']['type']) && $propSchema['items']['type'] === 'object') {
                                $errors = array_merge($errors, self::validate($item, $propSchema['items'], $itemPath));
                            } elseif (isset($propSchema['items']['type'])) {
                                if (!self::checkScalarType($item, $propSchema['items']['type'])) {
                                    $errors[] = "{$itemPath}: expected {$propSchema['items']['type']}";
                                }
                            }
                        }
                    }
                }

                // Pattern check
                if (isset($propSchema['pattern']) && is_string($value)) {
                    $pattern = '/' . str_replace('/', '\/', $propSchema['pattern']) . '/';
                    if (!preg_match($pattern, $value)) {
                        $errors[] = "{$propPath}: does not match pattern '{$propSchema['pattern']}'";
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Check if data matches the expected JSON Schema type.
     */
    private static function checkType(mixed $data, string $type): bool
    {
        return match ($type) {
            'object' => is_array($data),
            'array' => is_array($data) && array_is_list($data),
            'string' => is_string($data),
            'integer' => is_int($data),
            'number' => is_numeric($data),
            'boolean' => is_bool($data),
            'null' => is_null($data),
            default => true,
        };
    }

    /**
     * Check scalar type for array item validation.
     */
    private static function checkScalarType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_numeric($value),
            'boolean' => is_bool($value),
            default => true,
        };
    }
}
