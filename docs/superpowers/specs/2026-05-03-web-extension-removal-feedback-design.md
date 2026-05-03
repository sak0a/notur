# Web Extension Removal Feedback Design

## Summary

Fix the web extension removal flow so it reports the real result of `notur:remove` instead of always showing a success message after the request returns.

The immediate symptom is that removing an extension through the admin UI shows:

`Extension 'vendor/name' has been removed.`

while the extension remains installed. The same removal works correctly through the CLI command, which indicates the controller is not handling command failure correctly.

## Goals

- Make the web UI reflect the actual result of the extension removal command.
- Stop showing false success when `notur:remove` returns a nonzero exit code.
- Surface the artisan command output back to the admin UI when removal fails.
- Add regression coverage for the controller path.

## Non-Goals

- Refactoring all extension lifecycle logic out of console commands.
- Changing the CLI removal command behavior.
- Solving every possible underlying HTTP-vs-CLI runtime difference in the same change.

## Current Problem

`ExtensionAdminController::remove()` currently calls:

`Artisan::call('notur:remove', [...])`

and then immediately redirects with a success flash unless a PHP exception is thrown.

That is incorrect because Laravel artisan commands can fail by returning a nonzero exit code without throwing an exception. In that situation:

- the HTTP request completes
- the controller still flashes success
- the extension remains installed

This matches the observed behavior exactly.

## Proposed Approach

### 1. Treat artisan exit codes as the source of truth

Update `ExtensionAdminController::remove()` so it:

- captures the return value from `Artisan::call()`
- captures `Artisan::output()`
- only flashes success when the exit code is `0`
- flashes an error when the exit code is nonzero

If the command fails, the error message should include the trimmed command output when available.

### 2. Keep the fix narrow

The first change should stay inside the controller path. The CLI command already behaves correctly when run directly, so the immediate problem is user feedback and failure handling in the web flow.

This avoids a premature refactor and gives us a clean signal about whether there is a deeper HTTP-path runtime problem after the controller starts reporting failures honestly.

### 3. Optional lightweight diagnostics

If the controller receives a nonzero exit code, it may also log the failure with:

- extension ID
- exit code
- artisan output

This is optional but useful for diagnosing permission or environment mismatches that only appear under the web server user.

## Implementation Plan

- update `src/Http/Controllers/ExtensionAdminController.php`
- store the artisan exit code from `Artisan::call()`
- read `Artisan::output()` after the call
- return success only on exit code `0`
- return an error flash on nonzero exit code
- keep the current exception handling path for thrown exceptions

## Error Handling

- success path:
  - redirect with success flash only when exit code is `0`
- command failure path:
  - redirect with error flash
  - include command output if present
- exception path:
  - redirect with exception message as today

## Testing

Add regression coverage for the web removal path:

- successful remove command -> success flash
- failed remove command -> error flash
- failed remove command must not show the success message

If direct mocking of `Artisan::call()` is awkward in the current test setup, introduce a small seam around the controller’s command invocation and test that seam.

## Risks

- Once the controller starts reporting failures correctly, users may see the real underlying HTTP-path failure for the first time. That is expected and useful, but it may reveal a second bug such as filesystem permissions or web-user limitations.
- Command output may be empty in some failure cases, so the controller should still provide a fallback error message.

## Recommendation

Implement the narrow controller fix first. It is the fastest path to correct behavior, improves user trust immediately, and gives a clear signal about whether any deeper HTTP-only removal issue remains.
