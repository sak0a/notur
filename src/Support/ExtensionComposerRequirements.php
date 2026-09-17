<?php

declare(strict_types=1);

namespace Notur\Support;

use Composer\InstalledVersions;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use RuntimeException;

/** Extension archives share the panel's Composer runtime; they do not ship vendor/. */
final class ExtensionComposerRequirements
{
    public function validate(string $path): void
    {
        if (!is_file($path . '/composer.json')) {
            return;
        }
        $package = json_decode((string) file_get_contents($path . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($package['require'] ?? [] as $name => $constraint) {
            $version = null;
            if ($name === 'php' || $name === 'php-64bit') {
                if ($name === 'php-64bit' && PHP_INT_SIZE !== 8) {
                    throw new RuntimeException('Extension requires 64-bit PHP.');
                }
                $version = PHP_VERSION;
            } elseif (str_starts_with($name, 'ext-')) {
                $extension = substr($name, 4);
                if (!extension_loaded($extension)) {
                    throw new RuntimeException("Extension requires PHP module {$name}; install it before retrying.");
                }
                $version = phpversion($extension) ?: '0.0.0';
            } elseif ($name === 'notur/notur') {
                $version = (string) config('notur.version');
            } elseif (InstalledVersions::isInstalled($name)
                && InstalledVersions::satisfies(new VersionParser(), $name, $constraint)) {
                continue;
            }
            if ($version !== null && Semver::satisfies($version, $constraint)) {
                continue;
            }
            throw new RuntimeException("Unsatisfied extension Composer requirement: {$name} {$constraint}. Install a compatible dependency in the panel before retrying.");
        }
    }
}
