#!/usr/bin/env bash
# test-node-version-check.sh — verify the Node.js version check block in
# install.sh accepts modern Node, rejects too-old Node with a NodeSource
# hint, handles parse failures, and honors MIN_NODE_MAJOR override.
#
# Strategy: extract the block between # === node-version-check-{start,end} ===
# markers, stub `node`, `print_node_install_hint`, info/warn/error/die so the
# block runs in isolation. Inputs (T_NODE_OUTPUT, T_NODE_RC, T_MIN_MAJOR) are
# passed via env vars and consumed inside a single-quoted heredoc — no
# outer-vs-inner quoting dance. Mirrors the pattern in
# test-pkg-manager-detection.sh.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
INSTALL_SH="$SCRIPT_DIR/../install.sh"

if [ ! -f "$INSTALL_SH" ]; then
    echo "FAIL: install.sh not found at $INSTALL_SH"
    exit 1
fi

pass=0
fail=0

# Run the version-check block in a controlled subshell.
#   T_NODE_OUTPUT — what `node --version` should print (empty = no output)
#   T_NODE_RC     — exit code `node --version` should return (default 0)
#   T_MIN_MAJOR   — value for MIN_NODE_MAJOR (default 22)
#
# Captures combined stdout/stderr and inspects substrings. If the block
# completes cleanly (passing version), the inner shell echoes "EXIT: 0".
# If the block dies (`die` calls `exit 1`), that line is absent.
run_check() {
    local node_output="$1"
    local node_rc="${2:-0}"
    local min_major="${3:-22}"

    INSTALL_SH="$INSTALL_SH" \
    T_NODE_OUTPUT="$node_output" \
    T_NODE_RC="$node_rc" \
    T_MIN_MAJOR="$min_major" \
    bash <<'INNER' 2>&1
        set +e

        # Extract the marked block from install.sh.
        block=$(sed -n '/=== node-version-check-start ===/,/=== node-version-check-end ===/p' "$INSTALL_SH")
        if [ -z "$block" ]; then
            echo "ERR: could not extract node-version-check block from install.sh"
            exit 99
        fi

        # Stubs: replace install.sh helpers with versions that echo to stdout
        # so the assertions can grep for substrings.
        info()  { echo "INFO: $*"; }
        warn()  { echo "WARN: $*"; }
        error() { echo "ERROR: $*"; }
        die()   { echo "DIE: $*"; exit 1; }
        print_node_install_hint() { echo "HINT: print_node_install_hint called"; }

        # Stub `node` so `node --version` returns T_NODE_OUTPUT and T_NODE_RC.
        # printf instead of echo so empty T_NODE_OUTPUT prints nothing (vs.
        # echo's bare newline).
        node() {
            if [ "${1:-}" = "--version" ]; then
                if [ -n "$T_NODE_OUTPUT" ]; then
                    printf '%s\n' "$T_NODE_OUTPUT"
                fi
                return "$T_NODE_RC"
            fi
            return 0
        }

        MIN_NODE_MAJOR="$T_MIN_MAJOR"

        eval "$block"
        echo "EXIT: 0"
INNER
}

# Assert: block completed (exit 0) and output contains expected substring.
#   $1=label, $2=node_output, $3=expect_substr, $4=optional T_MIN_MAJOR (default 22)
#
# `|| true` on the substitution: when run_check's inner block calls `die`
# (exit 1), the subshell's nonzero status would otherwise trigger this
# script's `set -e` and abort the whole test run.
assert_pass() {
    local label="$1" node_output="$2" expect_substr="$3" min_major="${4:-22}"
    local out
    out=$(run_check "$node_output" 0 "$min_major" || true)
    if echo "$out" | grep -q "EXIT: 0" && echo "$out" | grep -qF "$expect_substr"; then
        echo "  PASS: $label"
        pass=$((pass+1))
    else
        echo "  FAIL: $label — expected EXIT: 0 + '$expect_substr', got:"
        echo "$out" | sed 's/^/      /'
        fail=$((fail+1))
    fi
}

# Assert: block died (no EXIT: 0 line) and output contains expected substring.
#   $1=label, $2=node_output, $3=node_rc, $4=expect_substr, $5=optional T_MIN_MAJOR (default 22)
assert_fail() {
    local label="$1" node_output="$2" node_rc="$3" expect_substr="$4" min_major="${5:-22}"
    local out
    out=$(run_check "$node_output" "$node_rc" "$min_major" || true)
    if ! echo "$out" | grep -q "EXIT: 0" && echo "$out" | grep -qF "$expect_substr"; then
        echo "  PASS: $label"
        pass=$((pass+1))
    else
        echo "  FAIL: $label — expected die + '$expect_substr', got:"
        echo "$out" | sed 's/^/      /'
        fail=$((fail+1))
    fi
}

echo "=== Acceptable Node versions (>= MIN_NODE_MAJOR) ==="
assert_pass "Node 22.0.0 passes"   "v22.0.0"  "Node.js version: 22.0.0"
assert_pass "Node 23.5.0 passes"   "v23.5.0"  "Node.js version: 23.5.0"
assert_pass "Node 22.11.0 passes"  "v22.11.0" "Node.js version: 22.11.0"

echo ""
echo "=== Too-old Node versions are rejected with NodeSource hint ==="
assert_fail "Node 18.19.0 rejected"        "v18.19.0" 0 "too old"
assert_fail "Node 18 -> NodeSource hint"   "v18.19.0" 0 "deb.nodesource.com/setup_22.x"
assert_fail "Node 12.22.0 rejected"        "v12.22.0" 0 "too old"
assert_fail "Node 0.10.48 rejected"        "v0.10.48" 0 "too old"

echo ""
echo "=== Unparseable / failed node --version ==="
assert_fail "node --version returns empty" ""    0 "Could not parse"
assert_fail "node --version returns 'lol'" "lol" 0 "Could not parse"
assert_fail "node --version exits nonzero" ""    1 "Failed to read Node.js version"

echo ""
echo "=== MIN_NODE_MAJOR override is honored ==="
assert_pass "MIN=18 + Node 18 passes"   "v18.19.0" "Node.js version: 18.19.0" 18
assert_fail "MIN=18 + Node 16 rejected" "v16.20.0" 0 "too old"                18

echo ""
echo "Results: $pass passed, $fail failed"
[ "$fail" -eq 0 ] || exit 1
