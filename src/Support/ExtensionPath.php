<?php

declare(strict_types=1);

namespace Notur\Support;

class ExtensionPath
{
    public static function base(string $extensionId): string
    {
        return self::extensionsDir() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $extensionId);
    }

    public static function public(string $extensionId): string
    {
        return self::publicExtensionsDir() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $extensionId);
    }

    public static function manifest(): string
    {
        $relative = trim(self::extensionsRelativePath(), '/\\');
        $parent = dirname($relative);
        $manifestRelative = ($parent === '.' || $parent === '') ? 'extensions.json' : $parent . '/extensions.json';

        return base_path($manifestRelative);
    }

    public static function extensionsDir(): string
    {
        return base_path(self::extensionsRelativePath());
    }

    public static function publicExtensionsDir(): string
    {
        return public_path(self::extensionsRelativePath());
    }

    public static function bridgeJs(): string
    {
        return public_path('notur/bridge.js');
    }

    public static function tailwindCss(): string
    {
        return public_path('notur/tailwind.css');
    }

    public static function fromId(string $extensionId, string $subPath = ''): string
    {
        $base = self::base($extensionId);
        return $subPath ? $base . '/' . ltrim($subPath, '/') : $base;
    }

    public static function publicUrl(string $extensionId, string $subPath = ''): string
    {
        $path = trim(self::extensionsRelativePath(), '/\\') . '/' . trim($extensionId, '/');
        if ($subPath !== '') {
            $path .= '/' . ltrim($subPath, '/');
        }

        return '/' . str_replace('\\', '/', $path);
    }

    private static function extensionsRelativePath(): string
    {
        $default = 'notur/extensions';

        try {
            if (function_exists('config')) {
                $configured = config('notur.extensions_path');
                if (is_string($configured) && trim($configured) !== '') {
                    return trim($configured, '/\\');
                }
            }
        } catch (\Throwable) {
            // Fallback to default when config/bootstrap is unavailable.
        }

        return $default;
    }
}
