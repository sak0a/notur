# Installer Lockfile Manager Detection Design

## Summary

Adjust the Notur installer so it respects the panel's original frontend package manager when that choice is implied by an existing lockfile, with an interactive prompt when the matching manager is missing.

The immediate target is `yarn.lock` because Pterodactyl panel installs can include a Yarn-based workflow while the current installer may proceed with `npm`, `pnpm`, or `bun` and only discover the mismatch later during `build:production`.

## Goals

- Prefer the panel's lockfile-defined package manager over generic command availability.
- Avoid mixing dependency installation under one manager with build scripts written for another.
- Prompt interactively before installing a missing lockfile-matched manager.
- Preserve deterministic behavior for non-interactive runs.

## Non-Goals

- Reworking the entire frontend build pipeline.
- Automatically installing missing package managers in non-interactive environments.
- Broad package-manager bootstrap support beyond what is needed for the current installer path.

## Current Problem

Today the installer auto-detects an available manager using command availability and platform heuristics. That can select `npm` even when the panel repository contains `yarn.lock`. The result is:

- dependency installation may ignore the repository's original lockfile
- `build:production` may hardcode `yarn`
- the installer recovers late with a direct webpack fallback instead of using the original toolchain end-to-end

This works in some cases, but it creates dependency-tree drift and noisy install logs.

## Proposed Approach

### 1. Lockfile-first package-manager selection

Update frontend package-manager detection in `installer/install.sh` to prefer lockfiles before command availability:

- `yarn.lock` -> `yarn`
- `pnpm-lock.yaml` -> `pnpm`
- `package-lock.json` -> `npm`
- `bun.lock` or `bun.lockb` -> `bun`

If no known lockfile exists, keep the current auto-detection logic.

When multiple lockfiles exist, use a stable priority order and warn that the repository has conflicting lockfiles. Initial priority:

- `yarn.lock`
- `pnpm-lock.yaml`
- `package-lock.json`
- `bun.lock` / `bun.lockb`

The warning should make clear that multiple lockfiles indicate a potentially inconsistent repository.

### 2. Interactive selection for missing or ambiguous lockfile-selected manager

If the selected manager is implied by a lockfile but the executable is missing, and the installer is running interactively, prompt the user instead of guessing.

If multiple lockfiles are detected, and the installer is running interactively, show a numbered selection menu even if one of the matching managers is already installed. Multiple lockfiles indicate an ambiguous repository state, so silent priority is too opaque for a manual install.

Example prompt:

`Detected yarn.lock, but yarn is not installed. Install yarn and continue with the panel's original package manager?`

Example interactive menu:

`Detected multiple frontend package-manager signals for this panel. Choose how to continue:`

`1. Yarn (not installed, yarn.lock found) (Recommended)`
`2. Bun (installed, no lockfile found)`
`3. PNPM (installed, pnpm-lock.yaml not found)`
`4. NPM (installed, package-lock.json found)`

If the user accepts:

- ensure the base runtime needed for installation exists
- install the missing manager
- continue using that manager for dependency install and build

If the user declines:

- fall back to another available manager
- emit a strong warning that this may ignore the lockfile and produce a different dependency tree

If no fallback manager is available, fail with a clear message.

### 3. Bootstrap behavior

For the `yarn.lock` case, bootstrap rules should be:

- if `yarn` is missing but `npm` exists, install `yarn` via `npm`
- if both `yarn` and `npm` are missing, install `npm` through the system package manager first, then install `yarn`

The implementation may remain focused on `yarn` for now even if the selection mechanism is generic.

### 4. Frontend build flow

Once a manager is selected from the lockfile, keep that manager for:

- dependency installation
- `build:production`
- related recovery steps that are manager-specific

The existing `yarn`-script webpack fallback should remain as a last-resort compatibility path, not the preferred path for a repository that already declares `yarn.lock`.

### 5. Interactive vs non-interactive behavior

Interactive runs:

- show the prompt when a lockfile-selected manager is missing
- show a numbered selection menu when multiple lockfiles are detected
- skip prompts entirely for the clean case of a single lockfile whose manager is already installed

Non-interactive runs:

- do not prompt
- preserve deterministic automatic behavior

This avoids hanging automation while still improving the manual server-install experience.

## Implementation Plan

Expected changes:

- update package-manager detection in `installer/install.sh` to inspect lockfiles before command availability
- add helper(s) to determine whether the session is interactive and to prompt for manager installation
- add helper(s) to render a numbered package-manager selection menu in ambiguous interactive cases
- add a small bootstrap path for Yarn installation when `yarn.lock` is present and the user approves
- keep the existing fallback build logic, but move it behind the lockfile-respecting path
- extend shell tests for selection, prompting, and fallback behavior

## Error Handling

- warn when multiple lockfiles are present
- fail clearly if the user declines installation and no fallback manager exists
- fail clearly if manager bootstrap is approved but installation fails
- warn explicitly when falling back away from the detected lockfile manager

## Testing

Add or update installer shell tests to cover:

- `yarn.lock` selects `yarn`
- interactive prompt appears when `yarn` is missing
- interactive menu appears when multiple lockfiles are detected
- accepting the prompt installs `yarn` and continues
- selecting an installed alternative manager from the menu proceeds with a warning
- selecting the recommended missing manager from the menu installs it and continues
- declining the prompt falls back with a warning
- non-interactive runs skip the prompt
- existing npm recovery behavior still works when npm is the selected manager

## Risks

- Multiple lockfiles may make intent ambiguous; the installer should warn rather than silently choose without context.
- Installing Yarn globally may behave differently across distributions and container images.
- Some environments may have restricted global package installation permissions even when `npm` is present.

## Recommendation

Implement the lockfile-first selection logic with an interactive install prompt for missing Yarn. This aligns the installer with the panel repository's existing dependency workflow while keeping manual installs understandable and automation deterministic.
