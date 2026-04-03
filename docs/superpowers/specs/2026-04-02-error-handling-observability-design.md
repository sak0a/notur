# Error Handling & Observability — Design Spec

**Date:** 2026-04-02
**Group:** 2 of 4 (Error Handling & Observability)
**Scope:** PHP lifecycle logging, frontend error observability, diagnostics array bounding

## Overview

Four targeted improvements to make debugging easier in production. No breaking API changes.

## 1. Log Manifest Failures in ExtensionManager::boot()

**Current:** `catch (ManifestException) { continue; }` — silently skips broken extensions.

**Change:** Bind the exception and log a warning:
```php
catch (ManifestException $e) {
    Log::warning("[Notur] Failed to load manifest for extension '{$id}': {$e->getMessage()}");
    continue;
}
```

One line, uses Laravel's `Log` facade.

## 2. Add Lifecycle Logging to ExtensionManager

Add `Log::info` / `Log::warning` calls at key lifecycle points:

| Location | Level | Message |
|----------|-------|---------|
| `boot()` after loop completes | info | `[Notur] Booted {N} extension(s)` |
| `bootExtension()` when entrypoint is null | warning | `[Notur] No entrypoint found for extension '{$id}', skipping` |
| `bootExtension()` when class doesn't implement interface | — | Already throws `ExtensionBootException` (sufficient) |
| `enable()` | info | `[Notur] Extension '{$id}' enabled` |
| `disable()` | info | `[Notur] Extension '{$id}' disabled` |

Total: 4 log statements added. Minimal, only logs things useful for production debugging.

## 3. Frontend Error Observability

**Problem:** `useServerContext` and `useUserContext` silently swallow errors. Extensions have no way to detect failures.

**Approach:** Non-breaking. Keep current return types (`ServerContext | null`, `UserContext | null`). Add:
- `console.warn` on failures so developers see them in browser console
- Record failures in `window.__NOTUR__.diagnostics.errors` so they're observable via DevTools

**useServerContext:** No change needed. It reads synchronously from DOM — returning `null` when not on a server page is correct behavior, not an error.

**useUserContext:** Add error handling to the fetch fallback:
```typescript
.catch((err) => {
    console.warn('[Notur] Failed to load user context:', err);
    const notur = (window as any).__NOTUR__;
    if (notur?.diagnostics?.errors) {
        notur.diagnostics.errors.push({
            extensionId: 'notur:bridge',
            message: `Failed to load user context: ${err?.message || String(err)}`,
            time: new Date().toISOString(),
        });
    }
})
```

## 4. Bound the Diagnostics Error Array

**Problem:** `window.__NOTUR__.diagnostics.errors` grows unbounded. In a long-running SPA session with misbehaving extensions, this leaks memory.

**Change:** Add a helper function used by both `ErrorBoundary` and `useUserContext` (and any future error reporters):

```typescript
const MAX_DIAGNOSTIC_ERRORS = 100;

export function recordDiagnosticError(error: {
    extensionId: string;
    message: string;
    stack?: string;
    componentStack?: string;
    time: string;
}): void {
    const notur = (window as any).__NOTUR__;
    if (!notur?.diagnostics?.errors) return;

    notur.diagnostics.errors.push(error);

    if (notur.diagnostics.errors.length > MAX_DIAGNOSTIC_ERRORS) {
        notur.diagnostics.errors.splice(0, notur.diagnostics.errors.length - MAX_DIAGNOSTIC_ERRORS);
    }
}
```

Place this in a new file `bridge/src/diagnostics.ts`. Update `ErrorBoundary` and the `useUserContext` error handler to use it instead of pushing directly.

## Testing Strategy

1. **PHP logging** — No dedicated tests. Verified by running the test suite (no regressions). Logging is side-effect-only and tested implicitly.
2. **useUserContext error handling** — Update existing `useUserContext` tests (if any) or add a test that verifies console.warn is called on fetch failure.
3. **recordDiagnosticError** — Add a small unit test verifying the 100-entry cap and splice behavior.
4. **ErrorBoundary** — Update to use `recordDiagnosticError`; existing tests cover the boundary behavior.

## File Summary

**New files (1):**
- `bridge/src/diagnostics.ts`

**Modified files (4):**
- `src/ExtensionManager.php` — Add 5 `Log::` calls
- `sdk/src/hooks/useUserContext.ts` — Add console.warn + diagnostics recording on fetch failure
- `bridge/src/ErrorBoundary.tsx` — Use `recordDiagnosticError` instead of direct push
- `bridge/src/index.ts` — Export `recordDiagnosticError` on `window.__NOTUR__`
