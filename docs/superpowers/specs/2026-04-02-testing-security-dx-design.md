# Testing, Security & DX — Design Spec

**Date:** 2026-04-02
**Group:** 4 of 4 (Testing, Security & DX)
**Scope:** Server access verification middleware, diagnostics utility test, slot component prop types

## Overview

Three changes: a security middleware for server access verification, a test for the new diagnostics utility, and exported TypeScript prop types for slot components.

**Dropped from original scope:**
- Command integration tests — needs complex test infrastructure beyond current session
- Extension dev hot-reload — full feature, not a quick improvement
- SDK documentation — separate documentation project

## 1. Server Access Verification Middleware

**Problem:** Extension `api-client` routes authenticate the user but don't verify they have access to the server referenced in the request. Every extension must manually implement this.

**Solution:** A shared `VerifyServerAccess` middleware at `src/Http/Middleware/VerifyServerAccess.php`.

**Usage:**
```php
// In extension route file:
Route::get('/server/{server}/stats', [StatsController::class, 'show'])
    ->middleware('notur.server-access');

// With custom parameter name:
Route::get('/stats/{serverId}', [StatsController::class, 'show'])
    ->middleware('notur.server-access:serverId');
```

**Behavior:**
1. Reads server identifier from route parameter (default: `server`)
2. Handles both full UUID and short UUID (grouped `orWhere` as per project conventions)
3. Checks user is owner or has subuser access
4. Admin users bypass access check
5. Rejects suspended servers
6. Sets resolved server on `$request->attributes->set('server', $server)`
7. Guards against missing Pterodactyl models with `class_exists()` check

**Registration:** Middleware alias `notur.server-access` registered in `NoturServiceProvider::boot()`.

## 2. Diagnostics Utility Test

**Problem:** The new `recordDiagnosticError` function (Group 2) has no test coverage, including the 100-entry bounding logic.

**Test file:** `tests/Frontend/diagnostics.test.ts`

Tests:
- Records an error to the diagnostics array
- Bounds the array to 100 entries (splices oldest)
- Handles missing `window.__NOTUR__` gracefully (no throw)

## 3. Slot Component Prop Types

**Problem:** Slot components are typed as `React.ComponentType<any>` — extension developers get no type hints for the props their components receive.

**Solution:** Export a `SlotComponentProps` interface from the SDK that extension developers can use to type their components:

```typescript
// In sdk/src/types.ts
export interface SlotComponentProps {
    extensionId: string;
    [key: string]: any;
}
```

Non-breaking — the `any` in `SlotRegistration.component` stays, but developers can import and use the type:
```typescript
import { SlotComponentProps } from '@notur/sdk';
const MyWidget: React.FC<SlotComponentProps> = ({ extensionId }) => { ... };
```

## File Summary

**New files (2):**
- `src/Http/Middleware/VerifyServerAccess.php`
- `tests/Frontend/diagnostics.test.ts`

**Modified files (2):**
- `src/NoturServiceProvider.php` — Register middleware alias
- `sdk/src/types.ts` — Add `SlotComponentProps` export
