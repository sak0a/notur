<?php

declare(strict_types=1);

namespace Notur\Support;

/**
 * Normalizes shorthand settings syntax to full field definitions.
 *
 * Supports:
 * - Shorthand: { type: string, default: "foo", required: true }
 * - Full: { key: "foo", label: "Foo", type: "string", ... }
 */
final class SettingsNormalizer
{
    /**
     * Reserved keys that indicate metadata, not field definitions.
     */
    private const RESERVED_KEYS = ['title', 'description', 'fields'];

    /**
     * Normalize settings from either shorthand or full format.
     *
     * Shorthand format (key => definition):
     * ```yaml
     * settings:
     *   api_key: { type: string, required: true }
     *   enabled: { type: boolean, default: true, public: true }
     *   theme: { type: select, options: [light, dark], default: dark }
     * ```
     *
     * Full format (array of fields):
     * ```yaml
     * settings:
     *   fields:
     *     - key: api_key
     *       type: string
     *       required: true
     * ```
     *
     * @param array<string, mixed> $settings Raw settings from manifest
     * @return array<string, mixed> Normalized settings with fields array
     */
    public static function normalize(array $settings): array
    {
        // Already in full format with fields array
        if (isset($settings['fields']) && is_array($settings['fields'])) {
            return $settings;
        }

        // Check for shorthand format (keys are field names, not reserved keys)
        $isShorthand = false;

        foreach (array_keys($settings) as $key) {
            if (!in_array($key, self::RESERVED_KEYS, true)) {
                $isShorthand = true;
                break;
            }
        }

        if (!$isShorthand) {
            // If only metadata exists (no fields key and no field definitions),
            // ensure we still have a fields array for consistency
            if (!isset($settings['fields']) && !empty($settings)) {
                $settings['fields'] = [];
            }
            return $settings;
        }

        // Convert shorthand to full format
        $fields = [];
        $metadata = [];

        foreach ($settings as $key => $definition) {
            if (in_array($key, self::RESERVED_KEYS, true)) {
                $metadata[$key] = $definition;
                continue;
            }

            $field = self::normalizeField($key, $definition);
            if ($field !== null) {
                $fields[] = $field;
            }
        }

        return array_merge($metadata, ['fields' => $fields]);
    }

    /**
     * Check if the settings array uses shorthand format.
     *
     * @param array<string, mixed> $settings Raw settings from manifest
     */
    public static function isShorthand(array $settings): bool
    {
        // If it has 'fields' array, it's full format
        if (isset($settings['fields']) && is_array($settings['fields'])) {
            return false;
        }

        // Check if any non-reserved keys exist
        foreach (array_keys($settings) as $key) {
            if (!in_array($key, self::RESERVED_KEYS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize a single field from shorthand.
     *
     * @param string $key Field key
     * @param mixed $definition Shorthand definition
     * @return array<string, mixed>|null Normalized field or null if invalid
     */
    private static function normalizeField(string $key, mixed $definition): ?array
    {
        if (!is_array($definition)) {
            // Simple type shorthand: api_key: string
            if (is_string($definition)) {
                return [
                    'key' => $key,
                    'type' => $definition,
                    'label' => self::keyToLabel($key),
                ];
            }
            return null;
        }

        $field = ['key' => $key];

        // Map shorthand properties
        $field['type'] = $definition['type'] ?? 'string';

        // Generate label from key if not provided
        $field['label'] = $definition['label'] ?? self::keyToLabel($key);

        // Copy other properties
        $copyProps = [
            'required',
            'default',
            'help',
            'description',
            'placeholder',
            'input',
            'public',
            'options',
        ];

        foreach ($copyProps as $prop) {
            if (array_key_exists($prop, $definition)) {
                $field[$prop] = $definition[$prop];
            }
        }

        return $field;
    }

    /**
     * Convert a snake_case or kebab-case key to a Title Case label.
     */
    private static function keyToLabel(string $key): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $key));
    }
}
