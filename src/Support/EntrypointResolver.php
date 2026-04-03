<?php

declare(strict_types=1);

namespace Notur\Support;

use Notur\Contracts\ExtensionInterface;
use Notur\ExtensionManifest;

class EntrypointResolver
{
    public function resolve(ExtensionManifest $manifest, string $extPath, array $psr4): ?string
    {
        $entrypoint = $manifest->getEntrypoint();
        if (is_string($entrypoint) && $entrypoint !== '') {
            return $entrypoint;
        }

        $composerEntrypoint = $this->readComposerEntrypoint($extPath);
        if ($composerEntrypoint !== null) {
            return $composerEntrypoint;
        }

        $defaultEntrypoint = $this->buildDefaultEntrypoint($manifest->getId());
        if ($defaultEntrypoint !== '' && class_exists($defaultEntrypoint) && is_subclass_of($defaultEntrypoint, ExtensionInterface::class)) {
            return $defaultEntrypoint;
        }

        $discovered = $this->discoverEntrypoint($extPath, $psr4, $defaultEntrypoint);
        if ($discovered !== null) {
            return $discovered;
        }

        return null;
    }

    private function readComposerEntrypoint(string $extPath): ?string
    {
        $composer = $this->readComposerJson($extPath);
        $extra = $composer['extra'] ?? null;
        if (!is_array($extra)) {
            return null;
        }

        $notur = $extra['notur'] ?? null;
        if (!is_array($notur)) {
            return null;
        }

        $entrypoint = $notur['entrypoint'] ?? null;
        if (!is_string($entrypoint) || $entrypoint === '') {
            return null;
        }

        return $entrypoint;
    }

    /**
     * @return array<string, mixed>
     */
    private function readComposerJson(string $extPath): array
    {
        $path = rtrim($extPath, '/') . '/composer.json';
        if (!file_exists($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function buildDefaultEntrypoint(string $id): string
    {
        $namespace = $this->inferNamespaceFromId($id);
        if ($namespace === '') {
            return '';
        }

        $className = $this->inferClassNameFromId($id);
        if ($className === '') {
            return '';
        }

        return $namespace . '\\' . $className;
    }

    private function inferNamespaceFromId(string $id): string
    {
        if (!str_contains($id, '/')) {
            return '';
        }

        [$vendor, $name] = explode('/', $id, 2);
        if ($vendor === '' || $name === '') {
            return '';
        }

        return $this->toStudly($vendor) . '\\' . $this->toStudly($name);
    }

    private function inferClassNameFromId(string $id): string
    {
        if (!str_contains($id, '/')) {
            return '';
        }

        [, $name] = explode('/', $id, 2);
        if ($name === '') {
            return '';
        }

        $classBase = $this->toStudly($name);
        return str_ends_with($classBase, 'Extension') ? $classBase : $classBase . 'Extension';
    }

    private function toStudly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $value)));
    }

    /**
     * @return array<int, string>
     */
    private function resolveAutoloadDirs(string $extPath, array $psr4): array
    {
        $dirs = [];

        foreach ($psr4 as $paths) {
            foreach ((array) $paths as $path) {
                if (!is_string($path) || $path === '') {
                    continue;
                }

                $dir = $this->resolvePath($extPath, $path);
                if (is_dir($dir)) {
                    $dirs[] = $dir;
                }
            }
        }

        return array_values(array_unique($dirs));
    }

    private function discoverEntrypoint(string $extPath, array $psr4, string $preferred): ?string
    {
        $dirs = $this->resolveAutoloadDirs($extPath, $psr4);
        if ($dirs === []) {
            $fallback = rtrim($extPath, '/') . '/src';
            if (is_dir($fallback)) {
                $dirs[] = $fallback;
            }
        }

        if ($dirs === []) {
            return null;
        }

        $candidates = $this->findExtensionClassCandidates($dirs);
        if ($candidates === []) {
            return null;
        }

        if ($preferred !== '' && isset($candidates[$preferred])) {
            $preferredFile = $candidates[$preferred];
            unset($candidates[$preferred]);
            $candidates = [$preferred => $preferredFile] + $candidates;
        }

        foreach ($candidates as $class => $file) {
            if (!class_exists($class)) {
                require_once $file;
            }

            if (is_subclass_of($class, ExtensionInterface::class)) {
                return $class;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $dirs
     * @return array<string, string> Map of class => file path.
     */
    private function findExtensionClassCandidates(array $dirs): array
    {
        $candidates = [];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $filename = $file->getFilename();
                if (!str_ends_with($filename, 'Extension.php')) {
                    continue;
                }

                $classes = $this->extractPhpClasses($file->getPathname());
                foreach ($classes as $class) {
                    if (!isset($candidates[$class])) {
                        $candidates[$class] = $file->getPathname();
                    }
                }
            }
        }

        return $candidates;
    }

    /**
     * @return array<int, string>
     */
    private function extractPhpClasses(string $file): array
    {
        $contents = file_get_contents($file);
        if ($contents === false) {
            return [];
        }

        $tokens = token_get_all($contents);
        $namespace = '';
        $classes = [];
        $previousToken = null;

        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token)) {
                if ($token[0] === T_NAMESPACE) {
                    $namespace = '';
                    for ($j = $i + 1; $j < $count; $j++) {
                        $next = $tokens[$j];
                        if (is_array($next) && in_array($next[0], [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                            $namespace .= $next[1];
                            continue;
                        }
                        if ($next === ';' || $next === '{') {
                            break;
                        }
                    }
                }

                if ($token[0] === T_CLASS) {
                    if ($previousToken === T_NEW) {
                        continue;
                    }

                    for ($j = $i + 1; $j < $count; $j++) {
                        $next = $tokens[$j];
                        if (is_array($next) && $next[0] === T_STRING) {
                            $className = $next[1];
                            $classes[] = $namespace !== '' ? $namespace . '\\' . $className : $className;
                            break;
                        }

                        if ($next === '{' || $next === ';') {
                            break;
                        }
                    }
                }

                if (!in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    $previousToken = $token[0];
                }
            } elseif (trim($token) !== '') {
                $previousToken = null;
            }
        }

        return $classes;
    }

    private function resolvePath(string $extPath, string $path): string
    {
        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return rtrim($extPath, '/') . '/' . ltrim($path, '/');
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
    }
}
