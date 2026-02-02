<?php

declare(strict_types=1);

namespace Notur\Facades;

use Illuminate\Support\Facades\Facade;
use Notur\ExtensionManager;

/**
 * @method static void boot()
 * @method static \Notur\Contracts\ExtensionInterface|null get(string $id)
 * @method static array all()
 * @method static \Notur\ExtensionManifest|null getManifest(string $id)
 * @method static array getFrontendSlots()
 * @method static bool isEnabled(string $id)
 * @method static void enable(string $id)
 * @method static void disable(string $id)
 * @method static void registerExtension(string $id, string $version)
 * @method static void unregisterExtension(string $id)
 * @method static string getExtensionsPath()
 * @method static string getManifestPath()
 * @method static string getPublicPath()
 *
 * @see \Notur\ExtensionManager
 */
class Notur extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ExtensionManager::class;
    }
}
