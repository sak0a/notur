# PHP Structural Refactors Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Improve PHP code organization by extracting classes, adding typed exceptions, and eliminating duplication across commands.

**Architecture:** Bottom-up approach — build exception hierarchy first, then shared traits/base classes, then extract `EntrypointResolver` from `ExtensionManager` and `ScaffoldGenerator` from `NewCommand`. Each step builds on the previous.

**Tech Stack:** PHP 8.2+, Laravel 10/11, PSR-12, PHPUnit with Orchestra Testbench

**Spec:** `docs/superpowers/specs/2026-04-02-php-structural-refactors-design.md`

---

### Task 1: Create Exception Hierarchy

**Files:**
- Create: `src/Exceptions/NoturException.php`
- Create: `src/Exceptions/ExtensionNotFoundException.php`
- Create: `src/Exceptions/ManifestException.php`
- Create: `src/Exceptions/DependencyResolutionException.php`
- Create: `src/Exceptions/ExtensionBootException.php`

- [ ] **Step 1: Create NoturException base class**

```php
<?php

declare(strict_types=1);

namespace Notur\Exceptions;

use RuntimeException;

abstract class NoturException extends RuntimeException
{
}
```

- [ ] **Step 2: Create ExtensionNotFoundException**

```php
<?php

declare(strict_types=1);

namespace Notur\Exceptions;

class ExtensionNotFoundException extends NoturException
{
    public readonly string $extensionId;

    public function __construct(string $extensionId, string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        $this->extensionId = $extensionId;

        if ($message === '') {
            $message = "Extension '{$extensionId}' not found.";
        }

        parent::__construct($message, $code, $previous);
    }
}
```

- [ ] **Step 3: Create ManifestException**

```php
<?php

declare(strict_types=1);

namespace Notur\Exceptions;

class ManifestException extends NoturException
{
}
```

- [ ] **Step 4: Create DependencyResolutionException**

```php
<?php

declare(strict_types=1);

namespace Notur\Exceptions;

class DependencyResolutionException extends NoturException
{
}
```

- [ ] **Step 5: Create ExtensionBootException**

```php
<?php

declare(strict_types=1);

namespace Notur\Exceptions;

class ExtensionBootException extends NoturException
{
}
```

- [ ] **Step 6: Run tests to verify nothing is broken**

Run: `./vendor/bin/phpunit`
Expected: All tests pass (no changes to existing code yet)

- [ ] **Step 7: Commit**

```bash
git add src/Exceptions/
git commit -m "refactor: add custom exception hierarchy

Add NoturException base class with four specific exception types:
ExtensionNotFoundException, ManifestException,
DependencyResolutionException, and ExtensionBootException."
```

---

### Task 2: Wire ManifestException into ExtensionManifest

**Files:**
- Modify: `src/ExtensionManifest.php`
- Modify: `tests/Unit/ManifestParserTest.php`

- [ ] **Step 1: Update test expectations to use ManifestException**

In `tests/Unit/ManifestParserTest.php`, replace the `use` import and exception expectations:

Replace:
```php
use InvalidArgumentException;
```
With:
```php
use Notur\Exceptions\ManifestException;
```

Replace in `test_throws_on_missing_required_fields`:
```php
$this->expectException(InvalidArgumentException::class);
```
With:
```php
$this->expectException(ManifestException::class);
```

Replace in `test_throws_on_invalid_id_format`:
```php
$this->expectException(InvalidArgumentException::class);
```
With:
```php
$this->expectException(ManifestException::class);
```

Replace in `test_throws_on_missing_file`:
```php
$this->expectException(InvalidArgumentException::class);
```
With:
```php
$this->expectException(ManifestException::class);
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Unit/ManifestParserTest.php`
Expected: 3 tests fail (expecting `ManifestException` but getting `InvalidArgumentException`)

- [ ] **Step 3: Update ExtensionManifest to throw ManifestException**

In `src/ExtensionManifest.php`:

Replace:
```php
use InvalidArgumentException;
```
With:
```php
use Notur\Exceptions\ManifestException;
```

Replace in constructor (line 24):
```php
throw new InvalidArgumentException("Manifest not found: {$path}");
```
With:
```php
throw new ManifestException("Manifest not found: {$path}");
```

Replace in `load()` (line 55):
```php
throw new InvalidArgumentException("No extension.yaml found in: {$extensionPath}");
```
With:
```php
throw new ManifestException("No extension.yaml found in: {$extensionPath}");
```

Replace in `validate()` (line 63):
```php
throw new InvalidArgumentException(
```
With:
```php
throw new ManifestException(
```

Replace in `validate()` (line 72):
```php
throw new InvalidArgumentException(
```
With:
```php
throw new ManifestException(
```

Replace in `validate()` (line 79):
```php
throw new InvalidArgumentException(
```
With:
```php
throw new ManifestException(
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Unit/ManifestParserTest.php`
Expected: All tests pass

- [ ] **Step 5: Commit**

```bash
git add src/ExtensionManifest.php tests/Unit/ManifestParserTest.php
git commit -m "refactor: use ManifestException in ExtensionManifest

Replace InvalidArgumentException with ManifestException for all
manifest validation and loading failures."
```

---

### Task 3: Wire DependencyResolutionException into DependencyResolver

**Files:**
- Modify: `src/DependencyResolver.php`
- Modify: `tests/Unit/DependencyResolverTest.php`

- [ ] **Step 1: Update test expectations to use DependencyResolutionException**

In `tests/Unit/DependencyResolverTest.php`:

Replace:
```php
use RuntimeException;
```
With:
```php
use Notur\Exceptions\DependencyResolutionException;
```

Replace in `test_detects_circular_dependency`:
```php
$this->expectException(RuntimeException::class);
```
With:
```php
$this->expectException(DependencyResolutionException::class);
```

Replace in `test_detects_self_dependency`:
```php
$this->expectException(RuntimeException::class);
```
With:
```php
$this->expectException(DependencyResolutionException::class);
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Unit/DependencyResolverTest.php`
Expected: 2 tests fail (expecting `DependencyResolutionException` but getting `RuntimeException`)

- [ ] **Step 3: Update DependencyResolver to throw DependencyResolutionException**

In `src/DependencyResolver.php`:

Replace:
```php
use RuntimeException;
```
With:
```php
use Notur\Exceptions\DependencyResolutionException;
```

Replace in `visit()` (line 42):
```php
throw new RuntimeException(
    "Circular dependency detected involving extension: {$node}"
);
```
With:
```php
throw new DependencyResolutionException(
    "Circular dependency detected involving extension: {$node}"
);
```

Update the `@throws` docblock on `resolve()` (line 18):
```php
 * @throws RuntimeException On circular dependency.
```
To:
```php
 * @throws DependencyResolutionException On circular dependency.
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Unit/DependencyResolverTest.php`
Expected: All tests pass

- [ ] **Step 5: Commit**

```bash
git add src/DependencyResolver.php tests/Unit/DependencyResolverTest.php
git commit -m "refactor: use DependencyResolutionException in DependencyResolver

Replace RuntimeException with DependencyResolutionException for
circular dependency detection errors."
```

---

### Task 4: Wire Exceptions into ExtensionManager

**Files:**
- Modify: `src/ExtensionManager.php`

- [ ] **Step 1: Add exception imports and update throws**

In `src/ExtensionManager.php`:

Replace:
```php
use RuntimeException;
```
With:
```php
use Notur\Exceptions\ExtensionBootException;
use Notur\Exceptions\ExtensionNotFoundException;
use Notur\Exceptions\ManifestException;
```

Replace in `boot()` (line 94) — the silent catch:
```php
} catch (\Throwable) {
    continue;
}
```
With:
```php
} catch (ManifestException) {
    continue;
}
```

Replace in `bootExtension()` (line 169):
```php
throw new RuntimeException(
    "Extension '{$id}' entrypoint must implement " . ExtensionInterface::class
);
```
With:
```php
throw new ExtensionBootException(
    "Extension '{$id}' entrypoint must implement " . ExtensionInterface::class
);
```

Replace in `setExtensionEnabled()` (line 353):
```php
throw new RuntimeException("Extension '{$id}' is not installed.");
```
With:
```php
throw new ExtensionNotFoundException($id, "Extension '{$id}' is not installed.");
```

- [ ] **Step 2: Run tests to verify everything passes**

Run: `./vendor/bin/phpunit`
Expected: All tests pass

- [ ] **Step 3: Commit**

```bash
git add src/ExtensionManager.php
git commit -m "refactor: use typed exceptions in ExtensionManager

Replace RuntimeException with ExtensionBootException,
ExtensionNotFoundException, and ManifestException catch."
```

---

### Task 5: Create ManagesFilesystem Trait

**Files:**
- Create: `src/Console/Concerns/ManagesFilesystem.php`

- [ ] **Step 1: Create the trait**

```php
<?php

declare(strict_types=1);

namespace Notur\Console\Concerns;

trait ManagesFilesystem
{
    /**
     * Recursively delete a directory and all its contents.
     */
    protected function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }

    /**
     * Recursively copy a directory.
     */
    protected function copyDirectory(string $source, string $dest): void
    {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $target = $dest . '/' . $iterator->getSubPathname();
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                copy($item->getPathname(), $target);
            }
        }
    }

    /**
     * Remove a file, link, or directory.
     */
    protected function cleanupPath(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }

        if (is_dir($path)) {
            $this->deleteDirectory($path);
        }
    }
}
```

- [ ] **Step 2: Run tests to verify nothing is broken**

Run: `./vendor/bin/phpunit`
Expected: All tests pass

- [ ] **Step 3: Commit**

```bash
git add src/Console/Concerns/ManagesFilesystem.php
git commit -m "refactor: extract ManagesFilesystem trait

Extract deleteDirectory, copyDirectory, and cleanupPath into
a shared trait to eliminate duplication across commands."
```

---

### Task 6: Create ExtensionLifecycleCommand Base Class

**Files:**
- Create: `src/Console/Commands/ExtensionLifecycleCommand.php`

- [ ] **Step 1: Create the abstract base class**

```php
<?php

declare(strict_types=1);

namespace Notur\Console\Commands;

use Illuminate\Console\Command;
use Notur\Console\Concerns\ManagesFilesystem;
use Notur\Support\ExtensionPath;

abstract class ExtensionLifecycleCommand extends Command
{
    use ManagesFilesystem;

    /**
     * Clear Notur-related caches.
     */
    protected function clearNoturCaches(): void
    {
        $this->call('cache:clear');
        $this->call('view:clear');
    }

    /**
     * Remove an extension's files and public assets.
     */
    protected function removeExtensionFiles(string $extensionId): void
    {
        $extensionPath = ExtensionPath::base($extensionId);
        if (is_dir($extensionPath)) {
            $this->deleteDirectory($extensionPath);
        }

        $publicPath = ExtensionPath::public($extensionId);
        if (is_dir($publicPath)) {
            $this->deleteDirectory($publicPath);
        }
    }
}
```

- [ ] **Step 2: Run tests to verify nothing is broken**

Run: `./vendor/bin/phpunit`
Expected: All tests pass

- [ ] **Step 3: Commit**

```bash
git add src/Console/Commands/ExtensionLifecycleCommand.php
git commit -m "refactor: add ExtensionLifecycleCommand base class

Abstract base for InstallCommand and RemoveCommand with shared
cache clearing and extension file removal utilities."
```

---

### Task 7: Refactor InstallCommand to Use Base Class

**Files:**
- Modify: `src/Console/Commands/InstallCommand.php`

- [ ] **Step 1: Update InstallCommand to extend ExtensionLifecycleCommand**

In `src/Console/Commands/InstallCommand.php`:

Replace:
```php
use Illuminate\Console\Command;
```
With nothing (remove the import — it's inherited from ExtensionLifecycleCommand).

Replace:
```php
class InstallCommand extends Command
```
With:
```php
class InstallCommand extends ExtensionLifecycleCommand
```

- [ ] **Step 2: Replace inline cache clearing with clearNoturCaches()**

In `finalizeInstall()`, replace (lines 256-257):
```php
        // Clear caches
        $this->call('cache:clear');
        $this->call('view:clear');
```
With:
```php
        // Clear caches
        $this->clearNoturCaches();
```

- [ ] **Step 3: Remove the three duplicated private methods**

Delete the `copyDirectory()` method (lines 264-285), the `deleteDirectory()` method (lines 287-307), and the `cleanupPath()` method (lines 309-319). These are now provided by the `ManagesFilesystem` trait via the base class.

- [ ] **Step 4: Run tests to verify everything passes**

Run: `./vendor/bin/phpunit`
Expected: All tests pass

- [ ] **Step 5: Commit**

```bash
git add src/Console/Commands/InstallCommand.php
git commit -m "refactor: InstallCommand extends ExtensionLifecycleCommand

Remove duplicated deleteDirectory, copyDirectory, cleanupPath
methods and inline cache clearing."
```

---

### Task 8: Refactor RemoveCommand to Use Base Class

**Files:**
- Modify: `src/Console/Commands/RemoveCommand.php`

- [ ] **Step 1: Update RemoveCommand to extend ExtensionLifecycleCommand**

In `src/Console/Commands/RemoveCommand.php`:

Replace:
```php
use Illuminate\Console\Command;
```
With nothing (remove the import).

Replace:
```php
class RemoveCommand extends Command
```
With:
```php
class RemoveCommand extends ExtensionLifecycleCommand
```

- [ ] **Step 2: Replace inline file removal with removeExtensionFiles()**

Replace (lines 61-71):
```php
        // Remove files
        $extensionPath = ExtensionPath::base($extensionId);
        if (is_dir($extensionPath)) {
            $this->deleteDirectory($extensionPath);
            $this->info('Removed extension files.');
        }

        // Remove public assets
        $publicPath = ExtensionPath::public($extensionId);
        if (is_dir($publicPath)) {
            $this->deleteDirectory($publicPath);
        }
```
With:
```php
        // Remove files and public assets
        $this->removeExtensionFiles($extensionId);
        $this->info('Removed extension files.');
```

- [ ] **Step 3: Replace inline cache clearing with clearNoturCaches()**

Replace (lines 82-83):
```php
        $this->call('cache:clear');
        $this->call('view:clear');
```
With:
```php
        $this->clearNoturCaches();
```

- [ ] **Step 4: Remove the duplicated deleteDirectory() method and unused ExtensionPath import**

Delete the `deleteDirectory()` method (lines 91-111). The `ExtensionPath` import can also be removed since `removeExtensionFiles()` in the base class handles it.

Remove:
```php
use Notur\Support\ExtensionPath;
```

- [ ] **Step 5: Run tests to verify everything passes**

Run: `./vendor/bin/phpunit`
Expected: All tests pass

- [ ] **Step 6: Commit**

```bash
git add src/Console/Commands/RemoveCommand.php
git commit -m "refactor: RemoveCommand extends ExtensionLifecycleCommand

Remove duplicated deleteDirectory and inline cache clearing,
use removeExtensionFiles and clearNoturCaches from base class."
```

---

### Task 9: Refactor UninstallCommand to Use ManagesFilesystem Trait

**Files:**
- Modify: `src/Console/Commands/UninstallCommand.php`

- [ ] **Step 1: Add the trait import and use statement**

In `src/Console/Commands/UninstallCommand.php`, add after the existing `use` imports:
```php
use Notur\Console\Concerns\ManagesFilesystem;
```

Add the trait use inside the class:
```php
class UninstallCommand extends Command
{
    use ManagesFilesystem;
```

- [ ] **Step 2: Remove the duplicated deleteDirectory() method**

Delete the `deleteDirectory()` method (lines 440-460). It's now provided by the trait.

- [ ] **Step 3: Run tests to verify everything passes**

Run: `./vendor/bin/phpunit`
Expected: All tests pass

- [ ] **Step 4: Commit**

```bash
git add src/Console/Commands/UninstallCommand.php
git commit -m "refactor: UninstallCommand uses ManagesFilesystem trait

Remove duplicated deleteDirectory method, use shared trait instead."
```

---

### Task 10: Create EntrypointResolver with Tests

**Files:**
- Create: `src/Support/EntrypointResolver.php`
- Create: `tests/Unit/EntrypointResolverTest.php`

- [ ] **Step 1: Write the test file**

```php
<?php

declare(strict_types=1);

namespace Notur\Tests\Unit;

use Notur\Contracts\ExtensionInterface;
use Notur\ExtensionManifest;
use Notur\Support\EntrypointResolver;
use PHPUnit\Framework\TestCase;

class EntrypointResolverTest extends TestCase
{
    private EntrypointResolver $resolver;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->resolver = new EntrypointResolver();
        $this->tempDir = sys_get_temp_dir() . '/notur-entrypoint-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->tempDir);
    }

    public function test_resolves_explicit_entrypoint(): void
    {
        $manifest = ExtensionManifest::fromArray([
            'id' => 'acme/test',
            'name' => 'Test',
            'version' => '1.0.0',
            'entrypoint' => 'Acme\Test\TestExtension',
        ], $this->tempDir);

        // The class doesn't exist, so resolve returns the string but it won't be bootable
        $result = $this->resolver->resolve($manifest, $this->tempDir, []);
        $this->assertSame('Acme\Test\TestExtension', $result);
    }

    public function test_resolves_from_composer_json_extra(): void
    {
        file_put_contents($this->tempDir . '/composer.json', json_encode([
            'extra' => [
                'notur' => [
                    'entrypoint' => 'Acme\Composer\MyExtension',
                ],
            ],
        ]));

        $manifest = ExtensionManifest::fromArray([
            'id' => 'acme/test',
            'name' => 'Test',
            'version' => '1.0.0',
        ], $this->tempDir);

        $result = $this->resolver->resolve($manifest, $this->tempDir, []);
        $this->assertSame('Acme\Composer\MyExtension', $result);
    }

    public function test_returns_null_when_no_entrypoint_found(): void
    {
        $manifest = ExtensionManifest::fromArray([
            'id' => 'acme/test',
            'name' => 'Test',
            'version' => '1.0.0',
        ], $this->tempDir);

        $result = $this->resolver->resolve($manifest, $this->tempDir, []);
        $this->assertNull($result);
    }

    public function test_infers_namespace_from_id(): void
    {
        $manifest = ExtensionManifest::fromArray([
            'id' => 'acme/hello-world',
            'name' => 'Hello World',
            'version' => '1.0.0',
        ], $this->tempDir);

        // Create a matching PHP class file in src/
        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir, 0755, true);

        $classContent = <<<'PHP'
<?php

namespace Acme\HelloWorld;

use Notur\Contracts\ExtensionInterface;

class HelloWorldExtension implements ExtensionInterface
{
    public function getId(): string { return 'acme/hello-world'; }
    public function getName(): string { return 'Hello World'; }
    public function getVersion(): string { return '1.0.0'; }
    public function register(): void {}
    public function boot(): void {}
    public function getBasePath(): string { return ''; }
}
PHP;

        file_put_contents($srcDir . '/HelloWorldExtension.php', $classContent);

        $psr4 = ['Acme\HelloWorld\\' => 'src/'];
        $result = $this->resolver->resolve($manifest, $this->tempDir, $psr4);

        $this->assertSame('Acme\HelloWorld\HelloWorldExtension', $result);
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Unit/EntrypointResolverTest.php`
Expected: FAIL — `EntrypointResolver` class not found

- [ ] **Step 3: Create EntrypointResolver class**

Extract the following methods from `src/ExtensionManager.php` (lines 459-757) into `src/Support/EntrypointResolver.php`:

```php
<?php

declare(strict_types=1);

namespace Notur\Support;

use Notur\Contracts\ExtensionInterface;
use Notur\ExtensionManifest;

class EntrypointResolver
{
    /**
     * Resolve the entrypoint class for an extension.
     *
     * @param array<string, string|array<int, string>> $psr4 PSR-4 autoload mappings.
     * @return string|null Fully qualified class name, or null if not found.
     */
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
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Unit/EntrypointResolverTest.php`
Expected: All tests pass

- [ ] **Step 5: Commit**

```bash
git add src/Support/EntrypointResolver.php tests/Unit/EntrypointResolverTest.php
git commit -m "refactor: extract EntrypointResolver from ExtensionManager

Move entrypoint resolution, namespace inference, class discovery,
and PHP token parsing into dedicated Support class (~300 lines)."
```

---

### Task 11: Wire EntrypointResolver into ExtensionManager

**Files:**
- Modify: `src/ExtensionManager.php`
- Modify: `src/NoturServiceProvider.php`

- [ ] **Step 1: Add EntrypointResolver as a constructor dependency**

In `src/ExtensionManager.php`, add the import:
```php
use Notur\Support\EntrypointResolver;
```

Update the constructor to accept `EntrypointResolver`:
```php
public function __construct(
    private readonly Application $app,
    private readonly DependencyResolver $resolver,
    private readonly PermissionBroker $permissionBroker,
    private ?ThemeCompiler $themeCompiler = null,
    ?FeatureRegistry $featureRegistry = null,
    private readonly EntrypointResolver $entrypointResolver = new EntrypointResolver(),
) {
    $this->featureRegistry = $featureRegistry ?? FeatureRegistry::defaults();
}
```

- [ ] **Step 2: Update bootExtension() to use EntrypointResolver**

In `bootExtension()`, replace (line 156):
```php
$entrypoint = $this->resolveEntrypoint($manifest, $extPath, $psr4);
```
With:
```php
$entrypoint = $this->entrypointResolver->resolve($manifest, $extPath, $psr4);
```

- [ ] **Step 3: Remove the extracted private methods from ExtensionManager**

Delete the following methods (they now live in `EntrypointResolver`):
- `resolveEntrypoint()` (lines 459-482)
- `readComposerEntrypoint()` (lines 484-503)
- `readComposerJson()` (lines 508-522) — **Note:** This is also used by `resolveAutoloadPsr4()`, so keep one copy. Check if `resolveAutoloadPsr4()` still calls it. Yes, line 442 calls `$this->readComposerJson()`. Keep this method in ExtensionManager.
- `buildDefaultEntrypoint()` (lines 524-537)
- `inferNamespaceFromId()` (lines 539-551) — **Note:** Also used by `resolveAutoloadPsr4()` line 451. Keep this method.
- `inferClassNameFromId()` (lines 553-566)
- `toStudly()` (lines 568-571) — Used by `inferNamespaceFromId()`. Keep this method.
- `resolveAutoloadDirs()` (lines 576-594)
- `discoverEntrypoint()` (lines 596-632)
- `findExtensionClassCandidates()` (lines 638-671)
- `extractPhpClasses()` (lines 676-735)

So delete only: `resolveEntrypoint()`, `buildDefaultEntrypoint()`, `inferClassNameFromId()`, `resolveAutoloadDirs()`, `discoverEntrypoint()`, `findExtensionClassCandidates()`, `extractPhpClasses()`.

Keep: `readComposerJson()`, `inferNamespaceFromId()`, `toStudly()`, `resolvePath()`, `isAbsolutePath()` — these are still used by `resolveAutoloadPsr4()` and `registerAutoloading()`.

- [ ] **Step 4: Update NoturServiceProvider to inject EntrypointResolver**

In `src/NoturServiceProvider.php`, add import:
```php
use Notur\Support\EntrypointResolver;
```

Add singleton registration in `register()`:
```php
$this->app->singleton(EntrypointResolver::class);
```

Update the `ExtensionManager` singleton to inject it:
```php
$this->app->singleton(ExtensionManager::class, function ($app) {
    return new ExtensionManager(
        $app,
        $app->make(DependencyResolver::class),
        $app->make(PermissionBroker::class),
        $app->make(Support\ThemeCompiler::class),
        $app->make(FeatureRegistry::class),
        $app->make(EntrypointResolver::class),
    );
});
```

- [ ] **Step 5: Run full test suite**

Run: `./vendor/bin/phpunit`
Expected: All tests pass

- [ ] **Step 6: Commit**

```bash
git add src/ExtensionManager.php src/NoturServiceProvider.php
git commit -m "refactor: wire EntrypointResolver into ExtensionManager

Inject EntrypointResolver as dependency, remove extracted methods.
ExtensionManager drops from ~800 to ~500 lines."
```

---

### Task 12: Create ScaffoldGenerator

**Files:**
- Create: `src/Support/ScaffoldGenerator.php`

- [ ] **Step 1: Create ScaffoldGenerator class**

Extract lines 341-1031 from `NewCommand` into `src/Support/ScaffoldGenerator.php`. The class takes `$basePath` and `$context` in the constructor:

```php
<?php

declare(strict_types=1);

namespace Notur\Support;

class ScaffoldGenerator
{
    public function __construct(
        private readonly string $basePath,
        private readonly array $context,
    ) {
    }

    /**
     * Generate all scaffold files based on context flags.
     */
    public function generate(): void
    {
        $this->createDirectories();
        $this->writeManifest();
        $this->writePhpClass();

        if ($this->context['includeApiRoutes']) {
            $this->writeApiRoute();
            $this->writeApiController();
        }

        if ($this->context['includeAdminRoutes']) {
            $this->writeAdminRoute();
        }

        if ($this->context['includeAdminRoutes'] || $this->context['includeAdmin']) {
            $this->writeAdminController();
        }

        if ($this->context['includeFrontend']) {
            $this->writeFrontendIndex();
            $this->writeFrontendPackageJson();
            $this->writeFrontendTsConfig();
            $this->writeFrontendWebpackConfig();
        }

        if ($this->context['includeMigrations']) {
            $this->writeMigration();
        }

        if ($this->context['includeAdmin']) {
            $this->writeAdminView();
        }

        if ($this->context['includeTests']) {
            $this->writeComposerJson();
            $this->writePhpunitXml();
            $this->writePhpunitTest();
        }
    }

    public function createDirectories(): void
    {
        $directories = [
            $this->basePath,
            $this->basePath . '/src',
        ];

        if ($this->context['includeApiRoutes'] || $this->context['includeAdminRoutes']) {
            $directories[] = $this->basePath . '/src/routes';
        }

        if ($this->context['includeApiRoutes'] || $this->context['includeAdminRoutes'] || $this->context['includeAdmin']) {
            $directories[] = $this->basePath . '/src/Http/Controllers';
        }

        if ($this->context['includeMigrations']) {
            $directories[] = $this->basePath . '/database/migrations';
        }

        if ($this->context['includeFrontend']) {
            $directories[] = $this->basePath . '/resources/frontend/src';
            $directories[] = $this->basePath . '/resources/frontend/dist';
        }

        if ($this->context['includeAdmin']) {
            $directories[] = $this->basePath . '/resources/views/admin';
        }

        if ($this->context['includeTests']) {
            $directories[] = $this->basePath . '/tests/Unit';
        }

        foreach ($directories as $dir) {
            mkdir($dir, 0755, true);
        }
    }

    public function writeManifest(): void
    {
        $lines = [];
        $lines[] = 'notur: "1.0"';
        $lines[] = 'id: ' . $this->yamlString($this->context['id']);
        $lines[] = 'name: ' . $this->yamlString($this->context['displayName']);
        $lines[] = 'version: "1.0.0"';
        $lines[] = 'description: ' . $this->yamlString($this->context['description']);

        if ($this->context['authorName'] !== '') {
            $lines[] = 'authors:';
            $lines[] = '  - name: ' . $this->yamlString($this->context['authorName']);
            if ($this->context['authorEmail'] !== '') {
                $lines[] = '    email: ' . $this->yamlString($this->context['authorEmail']);
            }
        }

        $lines[] = 'license: ' . $this->yamlString($this->context['license']);
        $lines[] = '';
        $lines[] = 'requires:';
        $lines[] = '  notur: "^1.0"';
        $lines[] = '  pterodactyl: "^1.11"';
        $lines[] = '  php: "^8.2"';

        file_put_contents($this->basePath . '/extension.yaml', implode("\n", $lines) . "\n");
    }

    public function writePhpClass(): void
    {
        $interfaces = [];
        $uses = [
            'Notur\\Support\\NoturExtension',
        ];

        if ($this->context['includeApiRoutes'] || $this->context['includeAdminRoutes']) {
            $interfaces[] = 'HasRoutes';
            $uses[] = 'Notur\\Contracts\\HasRoutes';
        }

        if ($this->context['includeMigrations']) {
            $interfaces[] = 'HasMigrations';
            $uses[] = 'Notur\\Contracts\\HasMigrations';
        }

        if ($this->context['includeAdmin']) {
            $interfaces[] = 'HasBladeViews';
            $uses[] = 'Notur\\Contracts\\HasBladeViews';
        }

        $content = "<?php\n\n";
        $content .= "declare(strict_types=1);\n\n";
        $content .= "namespace {$this->context['namespace']};\n\n";

        foreach ($uses as $use) {
            $content .= "use {$use};\n";
        }

        $content .= "\n";

        $classDeclaration = 'class ' . $this->context['className'] . ' extends NoturExtension';
        if (!empty($interfaces)) {
            $classDeclaration .= ' implements ' . implode(', ', $interfaces);
        }

        $content .= $classDeclaration . "\n";
        $content .= "{\n";

        $hasCustomMethods = false;

        if ($this->context['includeApiRoutes'] || $this->context['includeAdminRoutes']) {
            $hasCustomMethods = true;
            $content .= "    public function getRouteFiles(): array\n";
            $content .= "    {\n";
            $content .= "        return [\n";
            if ($this->context['includeApiRoutes']) {
                $content .= "            'api-client' => 'src/routes/api-client.php',\n";
            }
            if ($this->context['includeAdminRoutes']) {
                $content .= "            'admin' => 'src/routes/admin.php',\n";
            }
            $content .= "        ];\n";
            $content .= "    }\n";
        }

        if ($this->context['includeMigrations']) {
            if ($hasCustomMethods) {
                $content .= "\n";
            }
            $hasCustomMethods = true;
            $content .= "    public function getMigrationsPath(): string\n";
            $content .= "    {\n";
            $content .= "        return \$this->getBasePath() . '/database/migrations';\n";
            $content .= "    }\n";
        }

        if ($this->context['includeAdmin']) {
            if ($hasCustomMethods) {
                $content .= "\n";
            }
            $hasCustomMethods = true;
            $content .= "    public function getViewsPath(): string\n";
            $content .= "    {\n";
            $content .= "        return \$this->getBasePath() . '/resources/views';\n";
            $content .= "    }\n\n";
            $content .= "    public function getViewNamespace(): string\n";
            $content .= "    {\n";
            $content .= "        return '{$this->context['viewNamespace']}';\n";
            $content .= "    }\n";
        }

        if (!$hasCustomMethods) {
            $content .= "    // Metadata (id, name, version) is read from extension.yaml automatically.\n";
            $content .= "    // Override register() or boot() to add custom initialization logic.\n";
        }

        $content .= "}\n";

        file_put_contents($this->basePath . '/src/' . $this->context['className'] . '.php', $content);
    }

    public function writeApiRoute(): void
    {
        $namespace = $this->context['namespace'];
        $content = <<<PHP
<?php

use Illuminate\Support\Facades\Route;
use {$namespace}\Http\Controllers\ApiController;

Route::get('/ping', [ApiController::class, 'ping']);
PHP;

        file_put_contents($this->basePath . '/src/routes/api-client.php', $content . "\n");
    }

    public function writeAdminRoute(): void
    {
        $namespace = $this->context['namespace'];
        $content = <<<PHP
<?php

use Illuminate\Support\Facades\Route;
use {$namespace}\Http\Controllers\AdminController;

Route::get('/', [AdminController::class, 'index']);
PHP;

        file_put_contents($this->basePath . '/src/routes/admin.php', $content . "\n");
    }

    public function writeApiController(): void
    {
        $namespace = $this->context['namespace'];
        $displayName = $this->context['displayName'];
        $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\Http\Controllers;

use Illuminate\Http\JsonResponse;

class ApiController
{
    public function ping(): JsonResponse
    {
        return response()->json([
            'message' => '{$displayName} is alive.',
        ]);
    }
}
PHP;

        file_put_contents($this->basePath . '/src/Http/Controllers/ApiController.php', $content . "\n");
    }

    public function writeAdminController(): void
    {
        $namespace = $this->context['namespace'];
        $displayName = $this->context['displayName'];

        if ($this->context['includeAdmin']) {
            $viewNamespace = $this->context['viewNamespace'];
            $id = $this->context['id'];
            $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\Http\Controllers;

use Illuminate\View\View;

class AdminController
{
    public function index(): View
    {
        return view('{$viewNamespace}::admin.index', [
            'extensionId' => '{$id}',
            'extensionName' => '{$displayName}',
        ]);
    }
}
PHP;
        } else {
            $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\Http\Controllers;

use Illuminate\Http\JsonResponse;

class AdminController
{
    public function index(): JsonResponse
    {
        return response()->json([
            'message' => '{$displayName} admin endpoint is ready.',
        ]);
    }
}
PHP;
        }

        file_put_contents($this->basePath . '/src/Http/Controllers/AdminController.php', $content . "\n");
    }

    public function writeFrontendIndex(): void
    {
        $stubContent = $this->renderStub('frontend-index.tsx.stub', [
            'id' => $this->context['id'],
            'displayName' => $this->context['displayName'],
        ]);
        if ($stubContent !== null) {
            file_put_contents($this->basePath . '/resources/frontend/src/index.tsx', $stubContent . "\n");
            return;
        }

        $id = $this->context['id'];
        $displayName = $this->context['displayName'];
        $content = <<<TSX
import * as React from 'react';
import { createExtension } from '@notur/sdk';

const ExampleWidget: React.FC<{ extensionId: string }> = () => {
    return (
        <div
            style={{
                padding: '1rem',
                background: 'var(--notur-bg-secondary)',
                border: '1px solid var(--notur-border)',
                borderRadius: 'var(--notur-radius-md)',
            }}
        >
            <h3 style={{ color: 'var(--notur-text-primary)' }}>
                {$displayName}
            </h3>
            <p style={{ color: 'var(--notur-text-secondary)' }}>
                Hello from {$id}!
            </p>
        </div>
    );
};

// Register extension with slots - name/version are auto-resolved from extension.yaml
createExtension({
    id: '{$id}',
    slots: [
        {
            slot: 'dashboard.widgets',
            component: ExampleWidget,
            order: 100,
        },
    ],
});
TSX;

        file_put_contents($this->basePath . '/resources/frontend/src/index.tsx', $content . "\n");
    }

    public function writeFrontendPackageJson(): void
    {
        $stubContent = $this->renderStub('frontend-package.json.stub', [
            'id' => $this->context['id'],
        ]);
        if ($stubContent !== null) {
            file_put_contents($this->basePath . '/package.json', $stubContent . "\n");
            return;
        }

        $content = json_encode([
            'name' => $this->context['id'],
            'version' => '1.0.0',
            'private' => true,
            'scripts' => [
                'build' => 'webpack-cli --mode production --config webpack.config.js',
                'dev' => 'webpack-cli --mode development --watch --config webpack.config.js',
            ],
            'peerDependencies' => [
                'react' => '^16.14.0',
                'react-dom' => '^16.14.0',
            ],
            'devDependencies' => [
                '@notur/sdk' => '^1.2.0',
                '@types/react' => '^16.14.0',
                '@types/react-dom' => '^16.9.0',
                'css-loader' => '^7.1.2',
                'react' => '^16.14.0',
                'react-dom' => '^16.14.0',
                'style-loader' => '^4.0.0',
                'ts-loader' => '^9.5.0',
                'typescript' => '^5.3.0',
                'webpack' => '^5.90.0',
                'webpack-cli' => '^6.0.0',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        file_put_contents($this->basePath . '/package.json', $content . "\n");
    }

    public function writeFrontendTsConfig(): void
    {
        $content = json_encode([
            'compilerOptions' => [
                'target' => 'ES2018',
                'module' => 'ESNext',
                'lib' => ['DOM', 'ES2018'],
                'jsx' => 'react',
                'strict' => true,
                'esModuleInterop' => true,
                'moduleResolution' => 'node',
                'outDir' => './resources/frontend/dist',
                'rootDir' => './resources/frontend/src',
                'sourceMap' => true,
                'skipLibCheck' => true,
            ],
            'include' => ['resources/frontend/src/**/*'],
            'exclude' => ['node_modules', 'resources/frontend/dist'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        file_put_contents($this->basePath . '/tsconfig.json', $content . "\n");
    }

    public function writeFrontendWebpackConfig(): void
    {
        $libraryName = $this->toClassName($this->context['name']);

        $stubContent = $this->renderStub('webpack.config.js.stub', [
            'libraryName' => $libraryName,
        ]);
        if ($stubContent !== null) {
            file_put_contents($this->basePath . '/webpack.config.js', $stubContent . "\n");
            return;
        }

        $content = <<<JS
const path = require('path');
const base = require('@notur/sdk/webpack.extension.config');

module.exports = {
    ...base,
    entry: './resources/frontend/src/index.tsx',
    output: {
        ...base.output,
        filename: 'extension.js',
        path: path.resolve(__dirname, 'resources/frontend/dist'),
        library: {
            ...base.output.library,
            name: '__NOTUR_EXT_{$libraryName}__',
            type: 'umd',
        },
    },
};
JS;

        file_put_contents($this->basePath . '/webpack.config.js', $content . "\n");
    }

    public function writeMigration(): void
    {
        $table = str_replace('-', '_', $this->context['vendor'] . '_' . $this->context['name']);
        $timestamp = date('Y_m_d_His');
        $filename = $timestamp . '_create_' . $table . '_table.php';

        $content = <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$table}', function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$table}');
    }
};
PHP;

        file_put_contents($this->basePath . '/database/migrations/' . $filename, $content . "\n");
    }

    public function writeAdminView(): void
    {
        $displayName = $this->context['displayName'];
        $content = <<<BLADE
@extends('layouts.admin')

@section('title')
    {$displayName}
@endsection

@section('content-header')
    <h1>{$displayName}<small>Admin</small></h1>
@endsection

@section('content')
    <div class="box box-primary">
        <div class="box-body">
            <p>Welcome to {$displayName}.</p>
        </div>
    </div>
@endsection
BLADE;

        file_put_contents($this->basePath . '/resources/views/admin/index.blade.php', $content . "\n");
    }

    public function writeComposerJson(): void
    {
        $content = json_encode([
            'name' => $this->context['id'],
            'description' => $this->context['description'],
            'type' => 'library',
            'license' => $this->context['license'],
            'require' => [
                'php' => '^8.2',
            ],
            'require-dev' => [
                'notur/notur' => '^1.0',
                'phpunit/phpunit' => '^11.0',
            ],
            'autoload' => [
                'psr-4' => [
                    $this->context['namespace'] . '\\' => 'src/',
                ],
            ],
            'autoload-dev' => [
                'psr-4' => [
                    $this->context['namespace'] . '\\Tests\\' => 'tests/',
                ],
            ],
            'minimum-stability' => 'stable',
            'prefer-stable' => true,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        file_put_contents($this->basePath . '/composer.json', $content . "\n");
    }

    public function writePhpunitXml(): void
    {
        $content = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php" colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
    </testsuites>
</phpunit>
XML;

        file_put_contents($this->basePath . '/phpunit.xml', $content . "\n");
    }

    public function writePhpunitTest(): void
    {
        $namespace = $this->context['namespace'];
        $className = $this->context['className'];
        $id = $this->context['id'];
        $displayName = $this->context['displayName'];
        $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\Tests\Unit;

use {$namespace}\\{$className};
use PHPUnit\Framework\TestCase;

class ExtensionTest extends TestCase
{
    public function test_extension_metadata(): void
    {
        \$extension = new {$className}();

        \$this->assertSame('{$id}', \$extension->getId());
        \$this->assertSame('{$displayName}', \$extension->getName());
        \$this->assertSame('1.0.0', \$extension->getVersion());
    }
}
PHP;

        file_put_contents($this->basePath . '/tests/Unit/ExtensionTest.php', $content . "\n");
    }

    public function writeReadme(string $packageManager): void
    {
        $lines = [];
        $lines[] = '# ' . $this->context['displayName'];
        $lines[] = '';
        $lines[] = $this->context['description'];
        $lines[] = '';
        $lines[] = '## Local Development';
        $lines[] = '';
        $lines[] = '```bash';
        $lines[] = 'php artisan notur:dev /path/to/extension';
        $lines[] = '```';

        $lines[] = '';
        $lines[] = '## Export (Optional)';
        $lines[] = '';
        $lines[] = '```bash';
        $lines[] = 'php artisan notur:export /path/to/extension';
        $lines[] = '```';

        if ($this->context['includeFrontend']) {
            $resolver = new PackageManagerResolver();
            $lines[] = '';
            $lines[] = '## Frontend Development';
            $lines[] = '';
            $lines[] = '```bash';
            $lines[] = $resolver->installCommand($packageManager);
            $lines[] = $resolver->runScriptCommand($packageManager, 'dev');
            $lines[] = '```';
        }

        if ($this->context['includeTests']) {
            $lines[] = '';
            $lines[] = '## Tests';
            $lines[] = '';
            $lines[] = '```bash';
            $lines[] = 'composer install';
            $lines[] = './vendor/bin/phpunit';
            $lines[] = '```';
        }

        $lines[] = '';
        $lines[] = '## Structure';
        $lines[] = '';
        $lines[] = '- `extension.yaml` - Extension manifest';
        $lines[] = '- `src/` - PHP backend code';

        if ($this->context['includeFrontend']) {
            $lines[] = '- `resources/frontend/` - Frontend source and bundle';
        }

        if ($this->context['includeMigrations']) {
            $lines[] = '- `database/migrations/` - Database migrations';
        }

        if ($this->context['includeAdmin']) {
            $lines[] = '- `resources/views/` - Blade views for admin UI';
        }

        if ($this->context['includeTests']) {
            $lines[] = '- `tests/` - PHPUnit tests';
        }

        file_put_contents($this->basePath . '/README.md', implode("\n", $lines) . "\n");
    }

    public function writeGitignore(): void
    {
        $content = <<<TXT
/node_modules
/vendor
.phpunit.result.cache
.DS_Store
TXT;

        file_put_contents($this->basePath . '/.gitignore', $content . "\n");
    }

    private function yamlString(string $value): string
    {
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
        return '"' . $escaped . '"';
    }

    private function toClassName(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $name)));
    }

    private function toNamespace(string $vendor): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $vendor)));
    }

    private function toDisplayName(string $name): string
    {
        return ucwords(str_replace('-', ' ', $name));
    }

    private function toViewNamespace(string $vendor, string $name): string
    {
        return $vendor . '-' . $name;
    }

    private function renderStub(string $filename, array $variables): ?string
    {
        $stubPath = dirname(__DIR__, 2) . '/resources/stubs/new-extension/' . $filename;
        if (!is_file($stubPath)) {
            return null;
        }

        $content = file_get_contents($stubPath);
        if (!is_string($content)) {
            return null;
        }

        $replacements = [];
        foreach ($variables as $key => $value) {
            $replacements['{{' . $key . '}}'] = (string) $value;
        }

        return strtr($content, $replacements);
    }
}
```

- [ ] **Step 2: Run tests to verify nothing is broken**

Run: `./vendor/bin/phpunit`
Expected: All tests pass

- [ ] **Step 3: Commit**

```bash
git add src/Support/ScaffoldGenerator.php
git commit -m "refactor: extract ScaffoldGenerator from NewCommand

Move all file-writing, directory creation, and template rendering
logic into dedicated Support class (~690 lines)."
```

---

### Task 13: Refactor NewCommand to Use ScaffoldGenerator

**Files:**
- Modify: `src/Console/Commands/NewCommand.php`

- [ ] **Step 1: Add ScaffoldGenerator import**

In `src/Console/Commands/NewCommand.php`, add:
```php
use Notur\Support\ScaffoldGenerator;
```

- [ ] **Step 2: Replace handle() file-writing logic with ScaffoldGenerator**

Replace lines 85-127 in `handle()` (everything between context building and "next steps" output):

```php
        $this->info("Scaffolding extension: {$id}");

        $this->createDirectories($basePath, $context);

        $this->writeManifest($basePath, $context);
        $this->writePhpClass($basePath, $context);

        if ($context['includeApiRoutes']) {
            $this->writeApiRoute($basePath, $context);
            $this->writeApiController($basePath, $context);
        }

        if ($context['includeAdminRoutes']) {
            $this->writeAdminRoute($basePath, $context);
        }

        if ($context['includeAdminRoutes'] || $context['includeAdmin']) {
            $this->writeAdminController($basePath, $context);
        }

        if ($context['includeFrontend']) {
            $this->writeFrontendIndex($basePath, $context);
            $this->writeFrontendPackageJson($basePath, $context);
            $this->writeFrontendTsConfig($basePath);
            $this->writeFrontendWebpackConfig($basePath, $context);
        }

        if ($context['includeMigrations']) {
            $this->writeMigration($basePath, $context);
        }

        if ($context['includeAdmin']) {
            $this->writeAdminView($basePath, $context);
        }

        if ($context['includeTests']) {
            $this->writeComposerJson($basePath, $context);
            $this->writePhpunitXml($basePath, $context);
            $this->writePhpunitTest($basePath, $context);
        }

        $packageManager = $this->detectPackageManager($basePath);

        $this->writeReadme($basePath, $context, $packageManager);
        $this->writeGitignore($basePath);
```

With:

```php
        $this->info("Scaffolding extension: {$id}");

        $generator = new ScaffoldGenerator($basePath, $context);
        $generator->generate();

        $packageManager = $this->detectPackageManager($basePath);

        $generator->writeReadme($packageManager);
        $generator->writeGitignore();
```

- [ ] **Step 3: Remove all extracted private methods**

Delete the following methods from `NewCommand` (they now live in `ScaffoldGenerator`):
- `createDirectories()`
- `writeManifest()`
- `writePhpClass()`
- `writeApiRoute()`
- `writeAdminRoute()`
- `writeApiController()`
- `writeAdminController()`
- `writeFrontendIndex()`
- `writeFrontendPackageJson()`
- `writeFrontendTsConfig()`
- `writeFrontendWebpackConfig()`
- `writeMigration()`
- `writeAdminView()`
- `writeComposerJson()`
- `writePhpunitXml()`
- `writePhpunitTest()`
- `writeReadme()`
- `writeGitignore()`
- `yamlString()`
- `toClassName()`
- `toNamespace()`
- `toDisplayName()`
- `toViewNamespace()`
- `renderStub()`

Keep the following methods (command-specific):
- `handle()`
- `resolveMetadata()`
- `resolveFeatures()`
- `featuresForPreset()`
- `hasFeatureOverrides()`
- `applyFeatureOverrides()`
- `detectPackageManager()`
- `frontendInstallCommand()`
- `frontendRunScriptCommand()`

- [ ] **Step 4: Run full test suite**

Run: `./vendor/bin/phpunit`
Expected: All tests pass

- [ ] **Step 5: Commit**

```bash
git add src/Console/Commands/NewCommand.php
git commit -m "refactor: NewCommand delegates to ScaffoldGenerator

Remove all file-writing methods, delegate to ScaffoldGenerator.
NewCommand drops from ~1030 to ~340 lines."
```

---

### Task 14: Final Verification

**Files:** None (verification only)

- [ ] **Step 1: Run full PHP test suite**

Run: `./vendor/bin/phpunit`
Expected: All tests pass with no failures or errors

- [ ] **Step 2: Run frontend tests**

Run: `npm run test:frontend`
Expected: All frontend tests pass (no PHP changes should affect frontend)

- [ ] **Step 3: Verify file line counts**

Run: `wc -l src/ExtensionManager.php src/Console/Commands/NewCommand.php`
Expected: `ExtensionManager.php` ~500 lines, `NewCommand.php` ~340 lines

- [ ] **Step 4: Verify no remaining RuntimeException usage in refactored files**

Run: `grep -n 'RuntimeException' src/ExtensionManager.php src/DependencyResolver.php src/ExtensionManifest.php`
Expected: No matches (all replaced with typed exceptions)

- [ ] **Step 5: Verify no duplicated deleteDirectory methods**

Run: `grep -rn 'private function deleteDirectory' src/Console/Commands/`
Expected: No matches (all removed in favor of trait)
