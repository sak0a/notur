#!/usr/bin/env bash
# test-patch-roundtrip.sh — verify patches/v1.12/ apply cleanly forward and reverse.
#
# Usage: test-patch-roundtrip.sh <tag>      (e.g. v1.12.0, v1.12.1, v1.12.2)
#
# Exit 0 if forward + reverse application leaves the source tree pristine,
# with no warnings, no .orig files, no offset/fuzz notices.
# Exit non-zero otherwise, with a per-patch diagnostic.

set -euo pipefail

TAG="${1:?Usage: $0 <tag-like-v1.12.2>}"
if [[ ! "$TAG" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "Invalid tag format: $TAG (expected vX.Y.Z)" >&2
    exit 64
fi

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PATCH_DIR="$(cd "${SCRIPT_DIR}/../patches/v1.12" && pwd)"
WORK_ROOT="${TMPDIR:-/tmp}/notur-roundtrip"
PANEL_REPO="${WORK_ROOT}/panel"

mkdir -p "$WORK_ROOT"

# Cache the panel repo (blobless clone for speed).
if [ ! -d "$PANEL_REPO/.git" ]; then
    echo "Cloning Pterodactyl panel into $PANEL_REPO ..."
    git clone --filter=blob:none https://github.com/pterodactyl/panel.git "$PANEL_REPO"
fi

# Make sure the requested tag is fetched.
if ! git -C "$PANEL_REPO" rev-parse "$TAG" >/dev/null 2>&1; then
    echo "Fetching tag $TAG ..."
    git -C "$PANEL_REPO" fetch --depth=1 origin "refs/tags/$TAG:refs/tags/$TAG" >/dev/null 2>&1 || \
        git -C "$PANEL_REPO" fetch --tags origin >/dev/null 2>&1
fi

PRISTINE="$WORK_ROOT/pristine-$TAG"
WORKING="$WORK_ROOT/work-$TAG"
rm -rf "$PRISTINE" "$WORKING"
mkdir -p "$PRISTINE"

# Extract pristine source for the tag.
git -C "$PANEL_REPO" archive --format=tar "$TAG" | tar -x -C "$PRISTINE"

cp -R "$PRISTINE" "$WORKING"

# Sanity-check: catch a regression where patches accidentally got deleted.
# Without this, an empty PATCH_DIR would make the loop apply nothing, leaving
# WORKING == PRISTINE, and the test would falsely report a clean round-trip.
forward_count=$(find "$PATCH_DIR" -maxdepth 1 -name '*.patch' ! -name '*.reverse.patch' | wc -l | tr -d ' ')
reverse_count=$(find "$PATCH_DIR" -maxdepth 1 -name '*.reverse.patch' | wc -l | tr -d ' ')
if [ "$forward_count" -eq 0 ]; then
    echo "BAD: no forward patches found in $PATCH_DIR" >&2
    exit 1
fi
if [ "$reverse_count" -eq 0 ]; then
    echo "BAD: no reverse patches found in $PATCH_DIR" >&2
    exit 1
fi

fail=0
warned=0
offset_count=0
applied_forward=0
applied_reverse=0

# NOTE: GNU patch (Linux/CI) emits "succeeded at N (offset Y lines)" when a
# hunk's context matched but at a different line number than the patch
# header. This is benign and inherent to maintaining a *single shared*
# v1.12 patch set across v1.12.0/v1.12.1/v1.12.2, because two upstream
# files (Console.tsx, DashboardContainer.tsx) shifted line numbers between
# minor releases. We still gate on `diff -rq` against pristine source as
# the authoritative correctness check, so offsets are reported as INFO
# only — they don't fail the test. Genuine signals of trouble (fuzz,
# reject, "No such line", or .orig files) still mark the run as failed.
# macOS BSD patch silently suppresses these notices entirely; CI's GNU
# patch is the platform that surfaces them.
apply_pass() {
    local label="$1" pattern="$2"
    local saved_dir
    saved_dir="$(pwd)"
    cd "$WORKING"
    for patch in "$PATCH_DIR"/$pattern; do
        [ -f "$patch" ] || continue
        local name
        name=$(basename "$patch")
        # Skip reverse patches when applying forward pass.
        if [ "$pattern" = "*.patch" ] && [[ "$name" == *.reverse.patch ]]; then
            continue
        fi
        local out
        out=$(patch -p1 -f --no-backup-if-mismatch < "$patch" 2>&1) || {
            echo "$label FAIL: $name"
            echo "$out" | sed 's/^/    /'
            fail=1
            continue
        }
        if [ "$label" = "FORWARD" ]; then
            applied_forward=$((applied_forward + 1))
        else
            applied_reverse=$((applied_reverse + 1))
        fi
        # Real warning signals: fuzz / reject / malformed hunk header.
        if echo "$out" | grep -qE 'fuzz|reject|No such line'; then
            echo "$label WARN: $name"
            echo "$out" | grep -E 'fuzz|reject|No such line' | sed 's/^/    /'
            warned=1
        fi
        # Offset is informational — the hunk applied at a shifted line
        # because v1.12.{0,1,2} differ in line counts above the hunk.
        if echo "$out" | grep -q 'succeeded at .* offset'; then
            echo "$label INFO: $name applied with line offset"
            echo "$out" | grep 'succeeded at .* offset' | sed 's/^/    /'
            offset_count=$((offset_count + 1))
        fi
    done
    cd "$saved_dir"
}

apply_pass "FORWARD" "*.patch"
apply_pass "REVERSE" "*.reverse.patch"

# Confirm we actually applied every patch — defends against silent skips.
if [ "$applied_forward" -ne "$forward_count" ]; then
    echo "BAD: applied $applied_forward forward patches but expected $forward_count" >&2
    fail=1
fi
if [ "$applied_reverse" -ne "$reverse_count" ]; then
    echo "BAD: applied $applied_reverse reverse patches but expected $reverse_count" >&2
    fail=1
fi

# Detect any .orig files that patch left behind.
orig_files=$(find "$WORKING" -name '*.orig' 2>/dev/null || true)
if [ -n "$orig_files" ]; then
    echo "BAD: .orig files left behind:"
    echo "$orig_files" | sed 's/^/    /'
    fail=1
fi

# Compare round-tripped tree against pristine.
diff_out=$(diff -rq "$WORKING" "$PRISTINE" 2>&1 || true)
if [ -n "$diff_out" ]; then
    echo "ROUND-TRIP NOT CLEAN ($TAG):"
    echo "$diff_out" | sed 's/^/    /'
    fail=1
fi

if [ $fail -eq 0 ] && [ $warned -eq 0 ]; then
    if [ $offset_count -gt 0 ]; then
        echo "OK: round-trip clean for $TAG (no warnings, no .orig, diff empty; $offset_count hunk(s) applied with line offset — informational only)"
    else
        echo "OK: round-trip clean for $TAG (no warnings, no .orig, diff empty)"
    fi
    exit 0
fi

if [ $fail -eq 0 ] && [ $warned -ne 0 ]; then
    echo "FAIL: round-trip emitted warnings for $TAG"
    exit 2
fi

exit 1
