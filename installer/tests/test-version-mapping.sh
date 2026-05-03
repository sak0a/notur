#!/usr/bin/env bash
# test-version-mapping.sh — verify install.sh maps panel versions to patch dirs correctly.
#
# Strategy: extract the case statement from install.sh into a callable function,
# stub `detect_panel_version` and the helpers, then assert PATCH_VERSION + exit
# behavior across a table of inputs.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
INSTALL_SH="$SCRIPT_DIR/../install.sh"

if [ ! -f "$INSTALL_SH" ]; then
    echo "FAIL: install.sh not found at $INSTALL_SH"
    exit 1
fi

pass=0
fail=0

# Run install.sh's version-mapping in a subshell with a stubbed PANEL_VERSION,
# capture exit code and PATCH_VERSION.
# The case-block lives between the markers below; we extract it with sed.
run_mapping() {
    local input="$1"
    bash -c '
        set -e
        # Stub helpers used by the case block.
        info()  { :; }
        warn()  { :; }
        error() { echo "ERR: $1" >&2; }
        die()   { error "$1"; exit 1; }

        PANEL_VERSION="'"$input"'"
        PATCH_VERSION=""

        # Source just the case statement. We extract lines starting at
        # "# Map to patch directory" through the next "esac".
        case_block="$(sed -n "/^# Map to patch directory/,/^esac/p" "'"$INSTALL_SH"'")"
        if [ -z "$case_block" ]; then
            echo "ERR: could not extract case block from install.sh" >&2
            exit 2
        fi
        eval "$case_block"

        echo "PATCH_VERSION=$PATCH_VERSION"
    ' 2>&1
}

assert_supported() {
    local input="$1"
    local out rc
    out=$(run_mapping "$input") || rc=$? && rc=${rc:-0}
    if [ "${rc:-0}" -eq 0 ] && echo "$out" | grep -q "^PATCH_VERSION=v1.12$"; then
        echo "  PASS: $input -> v1.12"
        pass=$((pass+1))
    else
        echo "  FAIL: $input expected v1.12 (rc=0), got rc=${rc:-?}, output:"
        echo "$out" | sed 's/^/      /'
        fail=$((fail+1))
    fi
}

assert_unsupported() {
    local input="$1" must_match="$2"
    local out rc=0
    out=$(run_mapping "$input") || rc=$?
    if [ "$rc" -ne 0 ] && echo "$out" | grep -qF "$must_match"; then
        echo "  PASS: $input -> exit $rc, error contains '$must_match'"
        pass=$((pass+1))
    else
        echo "  FAIL: $input expected nonzero exit + error containing '$must_match', got rc=$rc, output:"
        echo "$out" | sed 's/^/      /'
        fail=$((fail+1))
    fi
}

echo "=== Supported versions (must map to v1.12, exit 0) ==="
assert_supported "1.12.0"
assert_supported "1.12.1"
assert_supported "1.12.2"
# Composer/git tags often surface a leading "v" — both forms must be accepted.
assert_supported "v1.12.0"
assert_supported "v1.12.1"
assert_supported "v1.12.2"

echo ""
echo "=== Unsupported v1.11 (must exit nonzero, mention v1.12.x) ==="
assert_unsupported "1.11.0" "v1.12.x"
assert_unsupported "1.11.11" "v1.12.x"
assert_unsupported "v1.11.11" "v1.12.x"

echo ""
echo "=== Other unsupported (must exit nonzero) ==="
assert_unsupported "1.10.5" "v1.12.x"
assert_unsupported "2.0.0" "v1.12.x"
assert_unsupported "" "v1.12.x"

echo ""
echo "Results: $pass passed, $fail failed"
[ "$fail" -eq 0 ] || exit 1
