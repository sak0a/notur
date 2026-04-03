# Frontend Improvements — Design Spec

**Date:** 2026-04-02
**Group:** 3 of 4 (Frontend Improvements)
**Scope:** Slot validation, duplicate guard, MutationObserver cleanup, bridge version compatibility

## Overview

Four targeted frontend improvements. No breaking API changes.

**Dropped from original scope:**
- Route system improvements — re-examined and found route matching already uses segment-based logic (`startsWith(routePath + '/')` on line 79 of RouteRenderer.tsx)
- Modernize class components — `useRoutes` hook already provides the functional alternative; converting classes is cosmetic with high risk

## 1. Slot Registration Validation

**Problem:** Registering to a non-existent slot ID goes unnoticed (typos silently fail).

**Change:** In `PluginRegistry.registerSlot()`, after building the registration, check if the slot ID exists in SLOT_IDS values and warn if not:

```typescript
const knownSlotIds = new Set(Object.values(SLOT_IDS));
// ... in registerSlot():
if (!knownSlotIds.has(reg.slot)) {
    console.warn(`[Notur] Slot "${reg.slot}" is not a known slot ID. Check for typos.`);
}
```

Build the `knownSlotIds` set once at module level (not per call). Import `SLOT_IDS` into PluginRegistry.

## 2. Duplicate Registration Guard

**Problem:** Same extensionId + slotId can be registered twice with no warning.

**Change:** In `PluginRegistry.registerSlot()`, before pushing, check if the same extensionId already has a registration for this slot:

```typescript
const isDuplicate = existing.some(r => r.extensionId === reg.extensionId);
if (isDuplicate) {
    console.warn(`[Notur] Extension "${reg.extensionId}" already registered in slot "${reg.slot}". Skipping duplicate.`);
    return;
}
```

Return early — don't register the duplicate. This prevents double-rendering.

## 3. MutationObserver Cleanup

**Problem:** `mountSlot()` creates MutationObservers with 30s timeouts. If the bridge is torn down before timeout, observers leak.

**Change:** Track active observers and provide cleanup:

1. Add a module-level set: `const activeObservers = new Set<MutationObserver>()`
2. In `mountSlot()`, add observer to set when created, remove on disconnect
3. Add a `cleanup()` function that disconnects all active observers and unmounts all slots
4. Export cleanup and optionally expose on `window.__NOTUR__`

## 4. Bridge Version Compatibility Check

**Problem:** No mechanism to detect SDK/bridge version mismatch.

**Change:** In `createExtension()`, after calling `getNoturApi()`, check the bridge version:

```typescript
const bridgeVersion = api.version;
if (bridgeVersion && bridgeVersion !== 'dev') {
    const bridgeMajor = parseInt(bridgeVersion.split('.')[0], 10);
    const sdkMajor = SDK_VERSION_MAJOR;  // constant in SDK
    if (!isNaN(bridgeMajor) && bridgeMajor !== sdkMajor) {
        console.warn(
            `[Notur SDK] Bridge version ${bridgeVersion} may be incompatible with SDK major version ${sdkMajor}.`
        );
    }
}
```

Read the SDK version from `package.json` at build time or define as a constant. Only check major version — minor/patch mismatches are expected.

## File Summary

**Modified files (3):**
- `bridge/src/PluginRegistry.ts` — Add slot validation + duplicate guard
- `bridge/src/index.ts` — Track MutationObservers, add cleanup function
- `sdk/src/createExtension.ts` — Add bridge version compatibility check
