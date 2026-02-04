<?php

declare(strict_types=1);

namespace Notur\Tests\Unit;

use Notur\Support\SettingsNormalizer;
use PHPUnit\Framework\TestCase;

class SettingsNormalizerTest extends TestCase
{
    public function test_full_format_passes_through_unchanged(): void
    {
        $settings = [
            'title' => 'My Settings',
            'fields' => [
                ['key' => 'api_key', 'type' => 'string', 'label' => 'API Key'],
                ['key' => 'enabled', 'type' => 'boolean', 'default' => true],
            ],
        ];

        $normalized = SettingsNormalizer::normalize($settings);

        $this->assertEquals($settings, $normalized);
    }

    public function test_shorthand_format_converts_to_full_format(): void
    {
        $settings = [
            'api_key' => ['type' => 'string', 'required' => true],
            'enabled' => ['type' => 'boolean', 'default' => true, 'public' => true],
        ];

        $normalized = SettingsNormalizer::normalize($settings);

        $this->assertArrayHasKey('fields', $normalized);
        $this->assertCount(2, $normalized['fields']);

        $apiKeyField = $normalized['fields'][0];
        $this->assertEquals('api_key', $apiKeyField['key']);
        $this->assertEquals('string', $apiKeyField['type']);
        $this->assertEquals('Api Key', $apiKeyField['label']);
        $this->assertTrue($apiKeyField['required']);

        $enabledField = $normalized['fields'][1];
        $this->assertEquals('enabled', $enabledField['key']);
        $this->assertEquals('boolean', $enabledField['type']);
        $this->assertEquals('Enabled', $enabledField['label']);
        $this->assertTrue($enabledField['default']);
        $this->assertTrue($enabledField['public']);
    }

    public function test_shorthand_with_metadata_preserves_title_and_description(): void
    {
        $settings = [
            'title' => 'Extension Settings',
            'description' => 'Configure the extension',
            'refresh_rate' => ['type' => 'number', 'default' => 60],
        ];

        $normalized = SettingsNormalizer::normalize($settings);

        $this->assertEquals('Extension Settings', $normalized['title']);
        $this->assertEquals('Configure the extension', $normalized['description']);
        $this->assertCount(1, $normalized['fields']);
        $this->assertEquals('refresh_rate', $normalized['fields'][0]['key']);
    }

    public function test_key_to_label_conversion(): void
    {
        $settings = [
            'api_key' => ['type' => 'string'],
            'refresh-rate' => ['type' => 'number'],
            'enableFeature' => ['type' => 'boolean'],
        ];

        $normalized = SettingsNormalizer::normalize($settings);

        $this->assertEquals('Api Key', $normalized['fields'][0]['label']);
        $this->assertEquals('Refresh Rate', $normalized['fields'][1]['label']);
        $this->assertEquals('EnableFeature', $normalized['fields'][2]['label']);
    }

    public function test_custom_label_not_overwritten(): void
    {
        $settings = [
            'api_key' => ['type' => 'string', 'label' => 'Your API Key'],
        ];

        $normalized = SettingsNormalizer::normalize($settings);

        $this->assertEquals('Your API Key', $normalized['fields'][0]['label']);
    }

    public function test_all_properties_are_copied(): void
    {
        $settings = [
            'theme' => [
                'type' => 'select',
                'label' => 'Theme',
                'required' => false,
                'default' => 'dark',
                'help' => 'Choose a theme',
                'description' => 'The visual theme',
                'placeholder' => 'Select...',
                'public' => true,
                'options' => ['light', 'dark', 'auto'],
            ],
        ];

        $normalized = SettingsNormalizer::normalize($settings);
        $field = $normalized['fields'][0];

        $this->assertEquals('theme', $field['key']);
        $this->assertEquals('select', $field['type']);
        $this->assertEquals('Theme', $field['label']);
        $this->assertFalse($field['required']);
        $this->assertEquals('dark', $field['default']);
        $this->assertEquals('Choose a theme', $field['help']);
        $this->assertEquals('The visual theme', $field['description']);
        $this->assertEquals('Select...', $field['placeholder']);
        $this->assertTrue($field['public']);
        $this->assertEquals(['light', 'dark', 'auto'], $field['options']);
    }

    public function test_isShorthand_detects_shorthand_format(): void
    {
        $shorthand = [
            'api_key' => ['type' => 'string'],
        ];

        $full = [
            'fields' => [
                ['key' => 'api_key', 'type' => 'string'],
            ],
        ];

        $this->assertTrue(SettingsNormalizer::isShorthand($shorthand));
        $this->assertFalse(SettingsNormalizer::isShorthand($full));
    }

    public function test_empty_settings_returns_empty(): void
    {
        $normalized = SettingsNormalizer::normalize([]);

        $this->assertEquals([], $normalized);
    }

    public function test_only_metadata_returns_empty_fields(): void
    {
        $settings = [
            'title' => 'Settings',
            'description' => 'Description',
        ];

        $normalized = SettingsNormalizer::normalize($settings);

        $this->assertEquals('Settings', $normalized['title']);
        $this->assertEquals('Description', $normalized['description']);
        $this->assertArrayHasKey('fields', $normalized);
        $this->assertEquals([], $normalized['fields']);
    }

    public function test_type_defaults_to_string(): void
    {
        $settings = [
            'username' => ['required' => true],
        ];

        $normalized = SettingsNormalizer::normalize($settings);

        $this->assertEquals('string', $normalized['fields'][0]['type']);
    }
}
