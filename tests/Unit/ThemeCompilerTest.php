<?php

declare(strict_types=1);

namespace Notur\Tests\Unit;

use Notur\Support\ThemeCompiler;
use PHPUnit\Framework\TestCase;

class ThemeCompilerTest extends TestCase
{
    private ThemeCompiler $compiler;

    protected function setUp(): void
    {
        $this->compiler = new ThemeCompiler();
    }

    public function test_compile_generates_css_custom_properties(): void
    {
        $css = $this->compiler->compile([
            'primary' => '#3490dc',
            'danger' => '#e3342f',
        ]);

        $this->assertStringContainsString('--notur-primary: #3490dc;', $css);
        $this->assertStringContainsString('--notur-danger: #e3342f;', $css);
        $this->assertStringStartsWith(':root {', $css);
        $this->assertStringEndsWith("}\n", $css);
    }

    public function test_compile_flattens_nested_config(): void
    {
        $css = $this->compiler->compile([
            'colors' => [
                'primary' => '#3490dc',
                'secondary' => '#6c757d',
            ],
        ]);

        $this->assertStringContainsString('--notur-colors-primary: #3490dc;', $css);
        $this->assertStringContainsString('--notur-colors-secondary: #6c757d;', $css);
    }

    public function test_compile_converts_underscores_to_hyphens(): void
    {
        $css = $this->compiler->compile([
            'font_size' => '16px',
        ]);

        $this->assertStringContainsString('--notur-font-size: 16px;', $css);
    }

    public function test_merge_theme_overrides_base_values(): void
    {
        $base = ['primary' => '#3490dc', 'danger' => '#e3342f'];
        $overrides = ['primary' => '#ff0000'];

        $css = $this->compiler->mergeTheme($base, $overrides);

        $this->assertStringContainsString('--notur-primary: #ff0000;', $css);
        $this->assertStringContainsString('--notur-danger: #e3342f;', $css);
    }

    public function test_merge_theme_deep_merges_nested_config(): void
    {
        $base = [
            'colors' => [
                'primary' => '#3490dc',
                'secondary' => '#6c757d',
            ],
        ];
        $overrides = [
            'colors' => [
                'primary' => '#ff0000',
            ],
        ];

        $css = $this->compiler->mergeTheme($base, $overrides);

        $this->assertStringContainsString('--notur-colors-primary: #ff0000;', $css);
        $this->assertStringContainsString('--notur-colors-secondary: #6c757d;', $css);
    }

    public function test_write_to_creates_directory_and_file(): void
    {
        $tempDir = sys_get_temp_dir() . '/notur-theme-test-' . uniqid();
        $filePath = $tempDir . '/sub/theme.css';

        try {
            $css = $this->compiler->compile(['primary' => '#000']);
            $this->compiler->writeTo($filePath, $css);

            $this->assertFileExists($filePath);
            $this->assertStringContainsString('--notur-primary: #000;', file_get_contents($filePath));
        } finally {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            if (is_dir($tempDir . '/sub')) {
                rmdir($tempDir . '/sub');
            }
            if (is_dir($tempDir)) {
                rmdir($tempDir);
            }
        }
    }

    public function test_register_view_overrides_stores_by_extension_id(): void
    {
        $this->compiler->registerViewOverrides('acme/dark-theme', [
            'pterodactyl' => '/path/to/views',
        ]);

        $overrides = $this->compiler->getViewOverrides();

        $this->assertArrayHasKey('acme/dark-theme', $overrides);
        $this->assertSame(['pterodactyl' => '/path/to/views'], $overrides['acme/dark-theme']);
    }

    public function test_register_css_overrides_stores_by_extension_id(): void
    {
        $this->compiler->registerCssOverrides('acme/dark-theme', [
            'colors' => ['primary' => '#1a1a2e'],
        ]);

        $overrides = $this->compiler->getCssOverrides();

        $this->assertArrayHasKey('acme/dark-theme', $overrides);
        $this->assertSame(['colors' => ['primary' => '#1a1a2e']], $overrides['acme/dark-theme']);
    }

    public function test_compile_all_overrides_merges_multiple_extensions(): void
    {
        $base = [
            'colors' => [
                'primary' => '#3490dc',
                'secondary' => '#6c757d',
                'background' => '#ffffff',
            ],
        ];

        $this->compiler->registerCssOverrides('acme/dark-theme', [
            'colors' => [
                'primary' => '#1a1a2e',
                'background' => '#0f0f0f',
            ],
        ]);

        $this->compiler->registerCssOverrides('acme/accent-pack', [
            'colors' => [
                'secondary' => '#e94560',
            ],
        ]);

        $css = $this->compiler->compileAllOverrides($base);

        $this->assertStringContainsString('--notur-colors-primary: #1a1a2e;', $css);
        $this->assertStringContainsString('--notur-colors-secondary: #e94560;', $css);
        $this->assertStringContainsString('--notur-colors-background: #0f0f0f;', $css);
    }

    public function test_compile_all_overrides_with_empty_base(): void
    {
        $this->compiler->registerCssOverrides('acme/theme', [
            'primary' => '#ff0000',
        ]);

        $css = $this->compiler->compileAllOverrides();

        $this->assertStringContainsString('--notur-primary: #ff0000;', $css);
    }

    public function test_multiple_view_overrides_from_different_extensions(): void
    {
        $this->compiler->registerViewOverrides('acme/dark-theme', [
            'pterodactyl' => '/path/to/dark/views',
        ]);

        $this->compiler->registerViewOverrides('acme/custom-layout', [
            'notur' => '/path/to/custom/views',
        ]);

        $overrides = $this->compiler->getViewOverrides();

        $this->assertCount(2, $overrides);
        $this->assertArrayHasKey('acme/dark-theme', $overrides);
        $this->assertArrayHasKey('acme/custom-layout', $overrides);
    }
}
