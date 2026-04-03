# PHP Structural Refactors — Design Spec

**Date:** 2026-04-02
**Group:** 1 of 4 (PHP Structural Refactors)
**Scope:** Custom exception hierarchy, filesystem trait, base lifecycle command, EntrypointResolver extraction, ScaffoldGenerator extraction

## Overview

Five refactors to improve code organization, reduce duplication, and add type-safe error handling across the PHP backend. All changes are internal — no public API, CLI interface, or behavior changes.

## 1. Custom Exception Hierarchy

**New directory:** `src/Exceptions/`

**Classes:**

| Exception | Extends | Purpose |
|-----------|---------|---------|
| `NoturException` | `RuntimeException` | Abstract base for all Notur exceptions |
| `ExtensionNotFoundException` | `NoturException` | Extension ID not found in manifest/filesystem/database |
| `ManifestException` | `NoturException` | YAML parse failure, missing required fields, invalid ID format |
| `DependencyResolutionException` | `NoturException` | Circular dependencies, missing dependency references |
| `ExtensionBootException` | `NoturException` | Entrypoint class missing, doesn't implement `ExtensionInterface`, or fails during register/boot |

**Where exceptions replace existing throws:**

- `ExtensionManager::setExtensionEnabled()` — `RuntimeException("Extension '{$id}' is not installed.")` becomes `ExtensionNotFoundException`
- `ExtensionManager::bootExtension()` line 169 — `RuntimeException("Extension '{$id}' entrypoint must implement...")` becomes `ExtensionBootException`
- `ExtensionManager::boot()` line 94 — silent `catch (\Throwable)` becomes `catch (ManifestException)` (logged, continues to next extension)
- `ExtensionManifest::load()` — existing `RuntimeException` throws become `ManifestException`
- `DependencyResolver::resolve()` — existing `RuntimeException` throws become `DependencyResolutionException`

**Design notes:**
- `NoturException` is abstract — never thrown directly
- All exceptions accept a standard `string $message` and optional `int $code` / `?\Throwable $previous`
- `ExtensionNotFoundException` also accepts `string $extensionId` as first constructor arg for convenience, with auto-generated message

## 2. Filesystem Trait

**New file:** `src/Console/Concerns/ManagesFilesystem.php`

**Methods:**

```php
trait ManagesFilesystem
{
    protected function deleteDirectory(string $dir): void
    protected function copyDirectory(string $source, string $dest): void
    protected function cleanupPath(string $path): void
}
```

- `deleteDirectory()` — Recursive delete using `RecursiveIteratorIterator` with `CHILD_FIRST`. Currently duplicated identically in `InstallCommand`, `RemoveCommand`, and `UninstallCommand`.
- `copyDirectory()` — Recursive copy using `RecursiveIteratorIterator` with `SELF_FIRST`. Currently only in `InstallCommand`.
- `cleanupPath()` — Unlinks files/links, delegates directories to `deleteDirectory()`. Currently only in `InstallCommand`.

**Consumers:**
- `InstallCommand` — `use ManagesFilesystem` (via `ExtensionLifecycleCommand`)
- `RemoveCommand` — `use ManagesFilesystem` (via `ExtensionLifecycleCommand`)
- `UninstallCommand` — `use ManagesFilesystem` directly (stays extending `Command`)

**Location rationale:** `Console/Concerns/` is the existing home for `HasInteractiveUI` and `HasProgressOutput` traits.

## 3. Base Lifecycle Command

**New file:** `src/Console/Commands/ExtensionLifecycleCommand.php`

```php
abstract class ExtensionLifecycleCommand extends Command
{
    use ManagesFilesystem;

    protected function clearNoturCaches(): void
    protected function removeExtensionFiles(string $extensionId): void
}
```

- `clearNoturCaches()` — Calls `cache:clear` and `view:clear`. Replaces inline `$this->call(...)` pairs in both commands.
- `removeExtensionFiles()` — Deletes extension directory and public assets directory for a given extension ID.

**What changes in each command:**

- **`InstallCommand`** extends `ExtensionLifecycleCommand`. Removes private `deleteDirectory()`, `copyDirectory()`, `cleanupPath()`. Uses `$this->clearNoturCaches()`.
- **`RemoveCommand`** extends `ExtensionLifecycleCommand`. Removes private `deleteDirectory()`. Uses `$this->removeExtensionFiles()` and `$this->clearNoturCaches()`.
- **`UninstallCommand`** stays extending `Command` directly (framework teardown is a different domain). Adds `use ManagesFilesystem` to eliminate its private `deleteDirectory()`.

**Design notes:**
- Intentionally thin — shared utilities only, no forced workflow template method pattern. Both commands keep their own `handle()` logic since install-from-registry and remove-with-rollback are fundamentally different flows.

## 4. EntrypointResolver Extraction

**New file:** `src/Support/EntrypointResolver.php`

Extracts lines 459-757 from `ExtensionManager` (~300 lines). Brings `ExtensionManager` from 803 to ~500 lines.

**Public API:**

```php
class EntrypointResolver
{
    public function resolve(ExtensionManifest $manifest, string $extPath, array $psr4): ?string
}
```

**Private methods that move with it (14 total):**

- `readComposerEntrypoint(string $extPath): ?string`
- `readComposerJson(string $extPath): array`
- `buildDefaultEntrypoint(string $id): string`
- `inferNamespaceFromId(string $id): string`
- `inferClassNameFromId(string $id): string`
- `toStudly(string $value): string`
- `resolveAutoloadDirs(string $extPath, array $psr4): array`
- `discoverEntrypoint(string $extPath, array $psr4, string $preferred): ?string`
- `findExtensionClassCandidates(array $dirs): array`
- `extractPhpClasses(string $file): array`
- `resolvePath(string $extPath, string $path): string`
- `isAbsolutePath(string $path): bool`

**What stays in `ExtensionManager`:**

- `resolveAutoloadPsr4()` — called independently during boot loop (line 115) before entrypoint resolution
- `registerAutoloading()` — uses its own `resolvePath()` / `isAbsolutePath()` copies (7 lines each, duplicated intentionally to avoid a shared utility for trivial methods)

**Integration:**

- `ExtensionManager` constructor gains `EntrypointResolver $entrypointResolver` parameter (with default `new EntrypointResolver()` for backwards compatibility)
- `bootExtension()` line 156 becomes: `$entrypoint = $this->entrypointResolver->resolve($manifest, $extPath, $psr4);`

**Exception integration:**
- `resolve()` throws `ExtensionBootException` when an entrypoint is explicitly declared but the class doesn't exist (currently returns null in this case)

## 5. ScaffoldGenerator Extraction

**New file:** `src/Support/ScaffoldGenerator.php`

Extracts lines 341-1031 from `NewCommand` (~690 lines). Brings `NewCommand` from 1,031 to ~340 lines.

**Public API:**

```php
class ScaffoldGenerator
{
    public function __construct(
        private readonly string $basePath,
        private readonly array $context,
    ) {}

    public function generate(): void
}
```

- `generate()` — Orchestrates all file creation based on context flags. Calls `createDirectories()`, then conditionally calls `write*()` methods based on `$context` feature flags.

**Public write methods (individually testable):**

- `createDirectories(): void`
- `writeManifest(): void`
- `writePhpClass(): void`
- `writeApiRoute(): void`
- `writeApiController(): void`
- `writeAdminRoute(): void`
- `writeAdminController(): void`
- `writeFrontendIndex(): void`
- `writeFrontendPackageJson(): void`
- `writeFrontendTsConfig(): void`
- `writeFrontendWebpackConfig(): void`
- `writeMigration(): void`
- `writeAdminView(): void`
- `writeComposerJson(): void`
- `writePhpunitXml(): void`
- `writePhpunitTest(): void`
- `writeReadme(string $packageManager): void`
- `writeGitignore(): void`

**Private helpers that move with it:**

- `yamlString(string $value): string`
- `toClassName(string $name): string`
- `toNamespace(string $vendor): string`
- `toDisplayName(string $name): string`
- `toViewNamespace(string $vendor, string $name): string`
- `renderStub(string $filename, array $variables): ?string`

**What stays in `NewCommand`:**

- `handle()` — argument parsing, context building, delegates to `ScaffoldGenerator`, prints "next steps"
- `resolveFeatures()` / `resolveMetadata()` — interactive prompts (command-specific)
- `featuresForPreset()` / `hasFeatureOverrides()` / `applyFeatureOverrides()` — feature flag logic
- `detectPackageManager()` / `frontendInstallCommand()` / `frontendRunScriptCommand()` — "next steps" output helpers

**Design notes:**
- `toStudly()` in `EntrypointResolver` and `toClassName()` in `ScaffoldGenerator` are similar but serve different purposes — keeping both is intentional, not duplication.
- `$basePath` and `$context` are constructor args to avoid passing them to every method.

## Testing Strategy

Each extraction should maintain existing test coverage and add targeted unit tests:

1. **Exceptions** — No dedicated test file needed. Verified through existing tests that exercise the throw sites (update assertions to check for specific exception types).
2. **ManagesFilesystem** — Tested indirectly through command tests. Optionally add a small unit test for `deleteDirectory` edge cases (empty dir, nested symlinks).
3. **ExtensionLifecycleCommand** — Tested indirectly through `InstallCommand` and `RemoveCommand` integration tests.
4. **EntrypointResolver** — Extract and expand tests from `ExtensionManagerTest` that cover entrypoint discovery. Add test cases for: explicit entrypoint, composer.json entrypoint, default convention, filesystem discovery, missing entrypoint.
5. **ScaffoldGenerator** — Add unit tests for individual `write*()` methods using a temp directory. Test `generate()` end-to-end with different feature flag combinations.

## File Summary

**New files (9):**
- `src/Exceptions/NoturException.php`
- `src/Exceptions/ExtensionNotFoundException.php`
- `src/Exceptions/ManifestException.php`
- `src/Exceptions/DependencyResolutionException.php`
- `src/Exceptions/ExtensionBootException.php`
- `src/Console/Concerns/ManagesFilesystem.php`
- `src/Console/Commands/ExtensionLifecycleCommand.php`
- `src/Support/EntrypointResolver.php`
- `src/Support/ScaffoldGenerator.php`

**Modified files (6):**
- `src/ExtensionManager.php` — Remove ~300 lines, add EntrypointResolver dependency, use new exceptions
- `src/ExtensionManifest.php` — Throw ManifestException instead of RuntimeException
- `src/DependencyResolver.php` — Throw DependencyResolutionException instead of RuntimeException
- `src/Console/Commands/InstallCommand.php` — Extend ExtensionLifecycleCommand, remove duplicated methods
- `src/Console/Commands/RemoveCommand.php` — Extend ExtensionLifecycleCommand, remove duplicated methods
- `src/Console/Commands/UninstallCommand.php` — Use ManagesFilesystem trait, remove duplicated method

**Net effect:**
- `ExtensionManager`: 803 → ~500 lines
- `NewCommand`: 1,031 → ~340 lines
- 3x duplicated `deleteDirectory()` → 1 trait
- Generic `RuntimeException` → 4 typed exceptions
