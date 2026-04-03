# Error Handling & Observability Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add lifecycle logging to PHP backend and improve frontend error observability with bounded diagnostics.

**Architecture:** Four small targeted changes — PHP logging via Laravel Log facade, frontend diagnostics utility, useUserContext error reporting, ErrorBoundary refactor.

**Tech Stack:** PHP 8.2+ (Laravel Log), TypeScript (React hooks), Jest

**Spec:** `docs/superpowers/specs/2026-04-02-error-handling-observability-design.md`

---

### Task 1: Add Lifecycle Logging to ExtensionManager

**Files:**
- Modify: `src/ExtensionManager.php`

- [ ] **Step 1: Add Log facade import**

Add at the top of `src/ExtensionManager.php`:
```php
use Illuminate\Support\Facades\Log;
```

- [ ] **Step 2: Log manifest failures in boot()**

Replace the catch block (around line 98):
```php
} catch (ManifestException) {
    continue;
}
```
With:
```php
} catch (ManifestException $e) {
    Log::warning("[Notur] Failed to load manifest for extension '{$id}': {$e->getMessage()}");
    continue;
}
```

- [ ] **Step 3: Log boot summary**

After the boot loop completes (after `$this->booted = true;`, around line 124), add:
```php
$this->booted = true;

if ($this->extensions !== []) {
    Log::info('[Notur] Booted ' . count($this->extensions) . ' extension(s)');
}
```

- [ ] **Step 4: Log when entrypoint is not found**

In `bootExtension()`, after `$entrypoint = $this->entrypointResolver->resolve(...)`, the existing code returns if null. Update:
```php
$entrypoint = $this->entrypointResolver->resolve($manifest, $extPath, $psr4);
if (!$entrypoint) {
    Log::warning("[Notur] No entrypoint found for extension '{$id}', skipping");
    return;
}
```

- [ ] **Step 5: Log enable/disable**

In `setExtensionEnabled()`, after the file_put_contents and DB update, add:
```php
Log::info("[Notur] Extension '{$id}' " . ($enabled ? 'enabled' : 'disabled'));
```

- [ ] **Step 6: Run tests**

Run: `./vendor/bin/phpunit`
Expected: All tests pass

- [ ] **Step 7: Commit**

```bash
git add src/ExtensionManager.php
git commit -m "feat: add lifecycle logging to ExtensionManager

Log manifest failures, boot summary, missing entrypoints,
and enable/disable state changes via Laravel Log facade."
```

---

### Task 2: Create Diagnostics Utility and Bound Error Array

**Files:**
- Create: `bridge/src/diagnostics.ts`
- Modify: `bridge/src/ErrorBoundary.tsx`
- Modify: `bridge/src/index.ts`

- [ ] **Step 1: Create diagnostics.ts**

Create `bridge/src/diagnostics.ts`:
```typescript
export const MAX_DIAGNOSTIC_ERRORS = 100;

export interface DiagnosticError {
    extensionId: string;
    message: string;
    stack?: string;
    componentStack?: string;
    time: string;
}

/**
 * Record a diagnostic error to the Notur diagnostics array.
 * Keeps the array bounded to MAX_DIAGNOSTIC_ERRORS entries,
 * dropping the oldest when full.
 */
export function recordDiagnosticError(error: DiagnosticError): void {
    const notur = (window as any).__NOTUR__;
    if (!notur?.diagnostics?.errors) return;

    notur.diagnostics.errors.push(error);

    if (notur.diagnostics.errors.length > MAX_DIAGNOSTIC_ERRORS) {
        notur.diagnostics.errors.splice(0, notur.diagnostics.errors.length - MAX_DIAGNOSTIC_ERRORS);
    }
}
```

- [ ] **Step 2: Update ErrorBoundary to use recordDiagnosticError**

In `bridge/src/ErrorBoundary.tsx`, replace the direct push in `componentDidCatch`:

Replace:
```typescript
const notur = (window as any).__NOTUR__;
if (notur?.diagnostics?.errors) {
    notur.diagnostics.errors.push({
        extensionId: this.props.extensionId,
        message: error.message || String(error),
        stack: error.stack,
        componentStack: errorInfo.componentStack,
        time: new Date().toISOString(),
    });
}
```
With:
```typescript
import { recordDiagnosticError } from './diagnostics';
```
(add at top of file)

And in componentDidCatch:
```typescript
recordDiagnosticError({
    extensionId: this.props.extensionId,
    message: error.message || String(error),
    stack: error.stack,
    componentStack: errorInfo.componentStack ?? undefined,
    time: new Date().toISOString(),
});
```

- [ ] **Step 3: Export recordDiagnosticError on window.__NOTUR__**

In `bridge/src/index.ts`, add import:
```typescript
import { recordDiagnosticError } from './diagnostics';
```

Add `recordDiagnosticError` to the `window.__NOTUR__` object alongside other exports (in the initialization section where the global is assigned).

- [ ] **Step 4: Run frontend tests**

Run: `npm run test:frontend`
Expected: All tests pass

- [ ] **Step 5: Commit**

```bash
git add bridge/src/diagnostics.ts bridge/src/ErrorBoundary.tsx bridge/src/index.ts
git commit -m "feat: add bounded diagnostics utility

Extract recordDiagnosticError helper with 100-entry cap.
Update ErrorBoundary to use it. Export on window.__NOTUR__."
```

---

### Task 3: Add Error Reporting to useUserContext

**Files:**
- Modify: `sdk/src/hooks/useUserContext.ts`

- [ ] **Step 1: Add error handling to the catch block**

In `sdk/src/hooks/useUserContext.ts`, replace the empty catch (line 43):
```typescript
.catch(() => {});
```
With:
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
        // Trim to 100 entries
        if (notur.diagnostics.errors.length > 100) {
            notur.diagnostics.errors.splice(0, notur.diagnostics.errors.length - 100);
        }
    }
});
```

Note: We inline the trim logic here rather than importing from bridge (SDK can't depend on bridge). The constant 100 matches MAX_DIAGNOSTIC_ERRORS.

- [ ] **Step 2: Run frontend tests**

Run: `npm run test:frontend`
Expected: All tests pass

- [ ] **Step 3: Commit**

```bash
git add sdk/src/hooks/useUserContext.ts
git commit -m "feat: add error reporting to useUserContext

Log console.warn and record diagnostics on fetch failure
instead of silently swallowing errors."
```

---

### Task 4: Final Verification

- [ ] **Step 1: Run PHP tests**

Run: `./vendor/bin/phpunit`
Expected: All tests pass

- [ ] **Step 2: Run frontend tests**

Run: `npm run test:frontend`
Expected: All tests pass

- [ ] **Step 3: Build bridge**

Run: `npm run build:bridge`
Expected: Build succeeds with no errors

- [ ] **Step 4: Build SDK**

Run: `npm run build:sdk`
Expected: Build succeeds with no errors
