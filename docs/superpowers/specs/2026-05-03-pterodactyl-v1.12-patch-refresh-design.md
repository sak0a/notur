# Pterodactyl v1.12 Patch Refresh & v1.11 Drop — Design

**Date:** 2026-05-03
**Status:** Approved
**Owner:** Notur maintainers

## Summary

Refresh the React/Blade patch set in `installer/patches/v1.12/` so it cleanly covers Pterodactyl Panel **v1.12.0**, **v1.12.1**, and **v1.12.2** (the entire 1.12.x line as of this writing), drop support for v1.11.x entirely, and fix three pre-existing patch hygiene bugs uncovered during the investigation.

This is a **breaking change** for any existing v1.11.x deployment.

## Goals

1. A single shared `installer/patches/v1.12/` directory that round-trips cleanly (forward + reverse → pristine source) against every released v1.12.x tag.
2. Hard-fail the installer with a clear error when run against any non-v1.12.x panel — no silent fallbacks.
3. Fix three known patch defects:
   - Missing `index.tsx.reverse.patch`
   - Missing `AdminSidebar.blade.php.reverse.patch`
   - Malformed third hunk in `FileManager.tsx.patch` (references line 117 in a 116-line file → emits "No such line 117 in input file, ignoring")
4. Update project documentation to declare v1.12.x the supported range.

## Non-goals

- Adding per-patch-version directories (`v1.12.0/`, `v1.12.1/`, `v1.12.2/`). Investigation showed our 26 patched files differ in only two non-conflicting spots between v1.12.0 and v1.12.1, so a single shared directory suffices.
- Any slot/feature changes. This is pure version-support cleanup.
- Bumping `NOTUR_VERSION`. The version bump is a release-management decision; this spec only adds a `CHANGELOG.md` entry.
- Touching the user-private auto-memory `MEMORY.md` outside the repo.

## Investigation findings (evidence)

Source tags fetched from `github.com/pterodactyl/panel`:

| Tag | Released |
|---|---|
| v1.12.2 | 2026-03-26 |
| v1.12.1 | 2026-02-14 |
| v1.12.0 | 2026-01-06 |

**Diff across the 26 files we patch:**

| Comparison | Files differ |
|---|---|
| v1.12.1 ↔ v1.12.2 | 0 (byte-identical) |
| v1.12.0 → v1.12.1 | 2: `DashboardContainer.tsx`, `Console.tsx` |
| v1.12.0 → v1.12.2 | same 2 files |

The two upstream changes are far away from our patch hunks:
- `DashboardContainer.tsx`: upstream added a `useEffect` near line 30; our patch hits line 50+.
- `Console.tsx`: upstream added Unicode11Addon imports/usage near lines 4, 60, 130; our patch hits line 221.

**Dry-run of every existing patch against every tag:** 26/26 forward patches apply on each of v1.12.0, v1.12.1, v1.12.2.

**Round-trip findings:**
- 24/26 reverse patches restore pristine source.
- `index.tsx.reverse.patch` and `AdminSidebar.blade.php.reverse.patch` do not exist.
- `FileManager.tsx.patch`'s third hunk references `@@ -118,7 +121,8 @@` but the source file ends at line 116, producing the "No such line 117" warning. Hunk content matches, so the patch still applies — it's a hunk-header bug, not a content bug.

## Design

### Patch refresh process (per file)

For each of the 26 patches:

1. Start from a pristine v1.12.2 working tree extracted from the upstream tag.
2. Apply the existing forward patch.
3. Regenerate the forward patch with `diff -u <pristine> <patched>` — this rewrites timestamps, line numbers, and fixes the malformed `FileManager.tsx.patch` hunk.
4. Regenerate the reverse patch with `diff -u <patched> <pristine>`.

For the two missing reverse patches:

5. Generate `index.tsx.reverse.patch` by applying `index.tsx.patch` to a pristine v1.12.2 `index.tsx`, then `diff -u <patched> <pristine>`.
6. Same procedure for `AdminSidebar.blade.php.reverse.patch`.

After regeneration, the directory will contain 26 forward patches and 26 reverse patches (up from 26 + 24).

### `install.sh` version mapping

Replace lines 621–626:

```bash
case "$PANEL_VERSION" in
    1.12.*) PATCH_VERSION="v1.12" ;;
    1.11.*) PATCH_VERSION="v1.11" ;;
    *)      PATCH_VERSION="v1.11" ; warn "Unknown version, defaulting to v1.11 patches" ;;
esac
```

with:

```bash
case "$PANEL_VERSION" in
    1.12.*)
        PATCH_VERSION="v1.12"
        ;;
    1.11.*)
        error "Pterodactyl v1.11.x is no longer supported by Notur. Please upgrade to v1.12.x."
        exit 1
        ;;
    "")
        error "Could not detect Pterodactyl panel version. Notur requires v1.12.x."
        exit 1
        ;;
    *)
        error "Unsupported Pterodactyl version: ${PANEL_VERSION}. Notur supports v1.12.x only."
        exit 1
        ;;
esac
```

The `error` helper is defined at line 39 of `install.sh` (`error() { echo -e "${RED}[Notur]${NC} $1" >&2; }`).

The version detection happens before any panel files are touched, so a failed run leaves the panel intact.

### Artisan command parity

`notur:install` is mentioned in `CLAUDE.md` as one of 17 artisan commands. The implementor will check `src/Console/Commands/InstallCommand.php` (or equivalent) for an independent panel-version gate and apply the same v1.12.x-only policy if one exists. If `notur:install` simply shells out to `install.sh`, no separate change is needed.

### Files to delete

- Entire directory: `installer/patches/v1.11/` (all `.patch` and `.reverse.patch` files)

### Documentation updates

**`CLAUDE.md` (project root):**
- Line 78 is the only v1.11 reference (verified via `grep -n 'v1\.11\|1\.11\.' CLAUDE.md`). Replace "applying React patches to Pterodactyl Panel v1.11 (26 patches in `installer/patches/v1.11/`)" with "applying React patches to Pterodactyl Panel v1.12 (26 patches in `installer/patches/v1.12/`)".

**`README.md`:**
- No current v1.11 references found. If the implementor finds any (e.g., in tables of supported versions added since this spec was written), update them to v1.12.x.

**`CHANGELOG.md`:**
- Add a new entry above `[1.3.2]`. Version number is left as `[Unreleased]` for the implementor — the actual version-bump decision is out of scope. Format follows the existing conventions:

```markdown
## [Unreleased]

### Breaking

- **Dropped support for Pterodactyl Panel v1.11.x.** The installer now hard-fails on any non-v1.12.x panel. Existing v1.11 deployments must upgrade the panel before upgrading Notur.

### Added

- Verified Pterodactyl Panel v1.12.0, v1.12.1, and v1.12.2 support via a single shared patch set.

### Fixed

- Missing reverse patches for `index.tsx` and `admin.blade.php` so `notur:remove` now restores the panel to pristine source.
- Malformed hunk in `FileManager.tsx.patch` that emitted a "No such line 117" warning during install.
```

### Acceptance criteria

The implementor must demonstrate all of these before the work is considered complete:

1. **Round-trip on each v1.12.x tag.** For each of v1.12.0, v1.12.1, v1.12.2:
   - Extract pristine source from the upstream tag.
   - Apply all 26 forward patches: each invocation exits 0 with no warnings printed and no `.orig` files created.
   - Apply all 26 reverse patches: each exits 0 cleanly.
   - `diff -rq <round-tripped-tree> <pristine-tree>` produces empty output.

2. **`install.sh` version mapping.** A new self-contained shell test at `installer/tests/test-version-mapping.sh` (the `installer/tests/` directory is new — no existing shell-test infra in the repo). The test sources or extracts the case-mapping logic, stubs `detect_panel_version`, and asserts the following table. No external test framework — plain `bash` + assertion functions defined in the test file. Test exits 0 when all assertions pass.

   | Input panel version | Expected exit | Expected `PATCH_VERSION` |
   |---|---|---|
   | `1.12.0`, `1.12.1`, `1.12.2` | 0 | `v1.12` |
   | `1.11.11`, `1.11.0` | 1 | (error mentions "v1.12.x") |
   | `1.10.5`, `2.0.0`, `""` | 1 | (clear error) |

3. **No regressions.** `./vendor/bin/phpunit` and `npm run test:frontend` both green.

4. **Verification artifact.** PR description includes the per-version `patch` output (showing zero warnings) and the empty `diff -rq` result for each tag.

5. **Manual smoke test.** Implementor stands up a v1.12.2 panel via Docker (or available equivalent), runs the installer, confirms a Notur slot DOM container renders (e.g., `<div id="notur-slot-server.console.header">`), runs `notur:remove`, confirms panel returns to pristine source. v1.12.0 / v1.12.1 manual tests are nice-to-have; round-trip + dry-run on real source is the primary signal.

### Risks and mitigations

- **Risk:** Patches regenerated against v1.12.2 line numbers may produce small line-offset notices on v1.12.0 (where `Console.tsx` and `DashboardContainer.tsx` are slightly shorter). `patch` handles offsets silently when context matches, but warnings would defeat acceptance criterion 1.
  - **Mitigation:** Acceptance criterion 1 explicitly requires zero warnings on all three tags. If offsets emerge during verification, the implementor regenerates the affected patches against context that's stable across versions (i.e., picks hunks that don't include the upstream-changed regions in surrounding context lines).

- **Risk:** Removing v1.11 fallback breaks installs on panels whose `composer.lock` parsing fails to detect a version (returns empty string), which previously silently fell through to v1.11 patches.
  - **Mitigation:** New explicit "Could not detect Pterodactyl panel version" error gives users a clear, actionable message.

- **Risk:** A user runs the new installer against an existing v1.11 install where Notur was previously installed. The v1.11 patches stay applied to the panel; the new installer refuses to touch it.
  - **Mitigation:** Documented in CHANGELOG breaking-change note. v1.11 users are expected to upgrade the panel first.

## Implementation order

The plan agent will sequence this; a reasonable order is:

1. Regenerate v1.12 patches and add the two missing reverse patches.
2. Verify round-trip on all three v1.12.x tags.
3. Update `install.sh` version mapping; add shell test.
4. Delete `installer/patches/v1.11/`.
5. Update `CLAUDE.md`, `CHANGELOG.md`, and any other docs touched.
6. Run `phpunit` and `npm run test:frontend`.
7. Open PR with verification artifact in description.
