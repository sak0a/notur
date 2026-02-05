<?php

declare(strict_types=1);

namespace Notur\Support;

class ExtensionPath
{
    public static function base(string $extensionId): string
    {
        return base_path('notur/extensions/' . str_replace('/', DIRECTORY_SEPARATOR, $extensionId));
    }

    public static function public(string $extensionId): string
    {
        return public_path('notur/extensions/' . $extensionId);
    }

    public static function manifest(): string
    {
        return base_path('notur/extensions.json');
    }

    public static function extensionsDir(): string
    {
        return base_path('notur/extensions');
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
}
