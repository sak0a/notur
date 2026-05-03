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
    git -C "$PANEL_REPO" fetch --depth=1 origin "tag $TAG" >/dev/null 2>&1 || \
        git -C "$PANEL_REPO" fetch --tags origin >/dev/null 2>&1
fi

PRISTINE="$WORK_ROOT/pristine-$TAG"
WORKING="$WORK_ROOT/work-$TAG"
rm -rf "$PRISTINE" "$WORKING"
mkdir -p "$PRISTINE"

# Extract pristine source for the tag.
git -C "$PANEL_REPO" archive --format=tar "$TAG" | tar -x -C "$PRISTINE"

cp -R "$PRISTINE" "$WORKING"

fail=0
warned=0

apply_pass() {
    local label="$1" pattern="$2"
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
        if echo "$out" | grep -qE 'offset|fuzz|FAIL|reject|No such line'; then
            echo "$label WARN: $name"
            echo "$out" | grep -E 'offset|fuzz|FAIL|reject|No such line' | sed 's/^/    /'
            warned=1
        fi
    done
    cd - >/dev/null
}

apply_pass "FORWARD" "*.patch"
apply_pass "REVERSE" "*.reverse.patch"

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
    echo "OK: round-trip clean for $TAG (no warnings, no .orig, diff empty)"
    exit 0
fi

if [ $fail -eq 0 ] && [ $warned -ne 0 ]; then
    echo "FAIL: round-trip emitted warnings for $TAG"
    exit 2
fi

exit 1
