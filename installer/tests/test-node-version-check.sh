#!/usr/bin/env bash
# test-node-version-check.sh — verify the Node.js version check block in
# install.sh accepts modern Node, rejects too-old Node with per-distro
# upgrade guidance, validates MIN_NODE_MAJOR, handles parse failures,
# and honors MIN_NODE_MAJOR override.
#
# Strategy: extract the block between # === node-version-check-{start,end} ===
# markers, stub `node`, `detect_sys_pkg_manager`, `print_node_install_hint`,
# info/warn/error/die so the block runs in isolation. Inputs are passed via
# env vars and consumed inside a single-quoted heredoc — no outer-vs-inner
# quoting dance. Mirrors the pattern in test-pkg-manager-detection.sh.

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
#   T_MIN_MAJOR   — value for MIN_NODE_MAJOR (default 22; pass "" for empty)
#   T_PKG_MGR     — value detect_sys_pkg_manager should return (default apt)
#
# Captures combined stdout/stderr and inspects substrings. If the block
# completes cleanly (passing version), the inner shell echoes "EXIT: 0".
# If the block dies (`die` calls `exit 1`), that line is absent.
#
# Note: defaults use ${X-Y} (no colon) so callers can pass an explicit
# empty string and it is preserved (needed for MIN_NODE_MAJOR='' tests).
run_check() {
    local node_output="$1"
    local node_rc="${2-0}"
    local min_major="${3-22}"
    local pkg_mgr="${4-apt}"

    INSTALL_SH="$INSTALL_SH" \
    T_NODE_OUTPUT="$node_output" \
    T_NODE_RC="$node_rc" \
    T_MIN_MAJOR="$min_major" \
    T_PKG_MGR="$pkg_mgr" \
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
        # Stub detect_sys_pkg_manager so the per-distro upgrade hint branches
        # are deterministic across host environments (CI may not have any
        # supported pkg mgr; macOS dev machines have brew which we don't map).
        detect_sys_pkg_manager() { printf '%s\n' "$T_PKG_MGR"; }

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
#   $1=label, $2=node_output, $3=expect_substr,
#   $4=optional T_MIN_MAJOR (default 22), $5=optional T_PKG_MGR (default apt)
#
# `|| true` on the substitution: when run_check's inner block calls `die`
# (exit 1), the subshell's nonzero status would otherwise trigger this
# script's `set -e` and abort the whole test run.
assert_pass() {
    local label="$1" node_output="$2" expect_substr="$3"
    local min_major="${4-22}" pkg_mgr="${5-apt}"
    local out
    out=$(run_check "$node_output" 0 "$min_major" "$pkg_mgr" || true)
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
#   $1=label, $2=node_output, $3=node_rc, $4=expect_substr,
#   $5=optional T_MIN_MAJOR (default 22), $6=optional T_PKG_MGR (default apt)
assert_fail() {
    local label="$1" node_output="$2" node_rc="$3" expect_substr="$4"
    local min_major="${5-22}" pkg_mgr="${6-apt}"
    local out
    out=$(run_check "$node_output" "$node_rc" "$min_major" "$pkg_mgr" || true)
    if ! echo "$out" | grep -q "EXIT: 0" && echo "$out" | grep -qF "$expect_substr"; then
        echo "  PASS: $label"
        pass=$((pass+1))
    else
        echo "  FAIL: $label — expected die + '$expect_substr', got:"
        echo "$out" | sed 's/^/      /'
        fail=$((fail+1))
    fi
}

# Assert: block did NOT print expected substring (negative grep).
#   $1=label, $2=node_output, $3=node_rc, $4=forbidden_substr,
#   $5=optional T_MIN_MAJOR (default 22), $6=optional T_PKG_MGR (default apt)
assert_no_substr() {
    local label="$1" node_output="$2" node_rc="$3" forbidden="$4"
    local min_major="${5-22}" pkg_mgr="${6-apt}"
    local out
    out=$(run_check "$node_output" "$node_rc" "$min_major" "$pkg_mgr" || true)
    if echo "$out" | grep -qF "$forbidden"; then
        echo "  FAIL: $label — output unexpectedly contains '$forbidden':"
        echo "$out" | sed 's/^/      /'
        fail=$((fail+1))
    else
        echo "  PASS: $label"
        pass=$((pass+1))
    fi
}

echo "=== Acceptable Node versions (>= MIN_NODE_MAJOR) ==="
assert_pass "Node 22.0.0 passes"   "v22.0.0"  "Node.js version: 22.0.0"
assert_pass "Node 23.5.0 passes"   "v23.5.0"  "Node.js version: 23.5.0"
assert_pass "Node 22.11.0 passes"  "v22.11.0" "Node.js version: 22.11.0"

echo ""
echo "=== Too-old Node versions are rejected ==="
assert_fail "Node 18.19.0 rejected"  "v18.19.0" 0 "too old"
assert_fail "Node 12.22.0 rejected"  "v12.22.0" 0 "too old"
assert_fail "Node 0.10.48 rejected"  "v0.10.48" 0 "too old"

echo ""
echo "=== Per-distro upgrade hints (too-old path) ==="
assert_fail "apt -> deb.nodesource.com hint"   "v18.19.0" 0 "deb.nodesource.com/setup_22.x" 22 apt
assert_fail "dnf -> rpm.nodesource.com hint"   "v18.19.0" 0 "rpm.nodesource.com/setup_22.x" 22 dnf
assert_fail "yum -> rpm.nodesource.com hint"   "v18.19.0" 0 "rpm.nodesource.com/setup_22.x" 22 yum
assert_fail "apk -> Alpine hint"               "v18.19.0" 0 "Alpine 3.21+"                  22 apk
assert_fail "pacman -> Arch hint"              "v18.19.0" 0 "Arch (rolling release"         22 pacman
assert_fail "unknown pkg mgr -> nodejs.org"    "v18.19.0" 0 "https://nodejs.org/"           22 ""

echo ""
echo "=== Too-old path does NOT call print_node_install_hint (avoid fail-loop) ==="
assert_no_substr "apt: no misleading 'apt install nodejs' hint" "v18.19.0" 0 "HINT: print_node_install_hint" 22 apt

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
echo "=== MIN_NODE_MAJOR validation (must be a positive integer) ==="
assert_fail "MIN='foo' rejected"  "v22.0.0" 0 "Invalid MIN_NODE_MAJOR" foo
assert_fail "MIN='1.5' rejected"  "v22.0.0" 0 "Invalid MIN_NODE_MAJOR" 1.5
assert_fail "MIN='-5' rejected"   "v22.0.0" 0 "Invalid MIN_NODE_MAJOR" -5
assert_fail "MIN='' rejected"     "v22.0.0" 0 "Invalid MIN_NODE_MAJOR" ""
assert_fail "MIN='22a' rejected"  "v22.0.0" 0 "Invalid MIN_NODE_MAJOR" 22a

echo ""
echo "Results: $pass passed, $fail failed"
[ "$fail" -eq 0 ] || exit 1
