<?php

declare(strict_types=1);

namespace Notur\Support;

class ThemeCompiler
{
    /** @var array<string, array<string, string>> View namespace overrides keyed by extension ID */
    private array $viewOverrides = [];

    /** @var array<string, array<string, mixed>> CSS variable overrides keyed by extension ID */
    private array $cssOverrides = [];

    /**
     * Generate CSS custom properties from a theme configuration.
     */
    public function compile(array $themeConfig): string
    {
        $css = ":root {\n";

        foreach ($this->flattenConfig($themeConfig) as $key => $value) {
            $cssVar = '--notur-' . str_replace('.', '-', str_replace('_', '-', $key));
            $css .= "    {$cssVar}: {$value};\n";
        }

        $css .= "}\n";

        return $css;
    }

    /**
     * Merge a theme extension's variables over the base theme.
     */
    public function mergeTheme(array $baseTheme, array $overrides): string
    {
        $merged = array_replace_recursive($baseTheme, $overrides);
        return $this->compile($merged);
    }

    /**
     * Write compiled CSS to a file.
     */
    public function writeTo(string $path, string $css): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $css);
    }

    /**
     * Register Blade view overrides from a theme extension.
     *
     * @param string $extensionId  The extension ID registering the overrides.
     * @param array<string, string> $overrides Map of view namespace => path to views directory.
     */
    public function registerViewOverrides(string $extensionId, array $overrides): void
    {
        $this->viewOverrides[$extensionId] = $overrides;
    }

    /**
     * Register CSS variable overrides from a theme extension.
     *
     * @param string $extensionId  The extension ID registering the overrides.
     * @param array<string, mixed> $variables  CSS variable overrides (nested or flat).
     */
    public function registerCssOverrides(string $extensionId, array $variables): void
    {
        $this->cssOverrides[$extensionId] = $variables;
    }

    /**
     * Get all registered view overrides.
     *
     * @return array<string, array<string, string>>
     */
    public function getViewOverrides(): array
    {
        return $this->viewOverrides;
    }

    /**
     * Get all registered CSS variable overrides.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getCssOverrides(): array
    {
        return $this->cssOverrides;
    }

    /**
     * Compile all CSS overrides into a single CSS string, merged onto a base theme.
     */
    public function compileAllOverrides(array $baseTheme = []): string
    {
        $merged = $baseTheme;

        foreach ($this->cssOverrides as $overrides) {
            $merged = array_replace_recursive($merged, $overrides);
        }

        return $this->compile($merged);
    }

    /**
     * Flatten a nested configuration array with dot notation.
     */
    private function flattenConfig(array $config, string $prefix = ''): array
    {
        $result = [];

        foreach ($config as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value)) {
                $result = array_merge($result, $this->flattenConfig($value, $fullKey));
            } else {
                $result[$fullKey] = (string) $value;
            }
        }

        return $result;
    }
}
