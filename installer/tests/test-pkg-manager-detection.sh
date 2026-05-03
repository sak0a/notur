#!/usr/bin/env bash
# test-pkg-manager-detection.sh — verify detect_pkg_manager picks the right
# tool on Alpine vs non-Alpine, and honors PKG_MANAGER overrides.
#
# Strategy: extract the detect_pkg_manager() function from install.sh, stub
# is_alpine and `command -v` to simulate different environments, then assert
# the chosen manager.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
INSTALL_SH="$SCRIPT_DIR/../install.sh"

if [ ! -f "$INSTALL_SH" ]; then
    echo "FAIL: install.sh not found at $INSTALL_SH"
    exit 1
fi

pass=0
fail=0

# Run detect_pkg_manager in a controlled subshell.
#   $1 = "yes" or "no"  (whether is_alpine returns true)
#   $2 = space-separated list of "available" pkg managers (e.g. "bun npm")
#   $3 = optional PKG_MANAGER env override
#
# Inputs are passed via the environment (not interpolated into the bash -c
# string), and the script body is a single-quoted heredoc so $vars inside it
# are resolved by the inner shell — not by the outer one. This keeps the test
# robust even if inputs ever contain shell-special characters.
run_detect() {
    local alpine="$1"
    local available="$2"
    local pkg_env="${3:-}"

    INSTALL_SH="$INSTALL_SH" \
    T_ALPINE="$alpine" \
    T_AVAILABLE="$available" \
    T_PKG_ENV="$pkg_env" \
    bash <<'INNER'
        set -eu
        # Extract the detect_pkg_manager function definition.
        func_block=$(sed -n "/^detect_pkg_manager()/,/^}$/p" "$INSTALL_SH")
        if [ -z "$func_block" ]; then
            echo "ERR: could not extract detect_pkg_manager from install.sh" >&2
            exit 2
        fi
        eval "$func_block"

        # Stubs.
        warn() { :; }
        if [ "$T_ALPINE" = "yes" ]; then
            is_alpine() { return 0; }
        else
            is_alpine() { return 1; }
        fi

        # Wrap with surrounding spaces so the case glob below sees " $name "
        # for every entry — including the first and last in the list.
        AVAILABLE=" $T_AVAILABLE "
        # Override `command` so `command -v <name>` only succeeds for stubbed
        # entries. Other forms (command <prog>) fall back to the builtin.
        command() {
            if [ "${1:-}" = "-v" ]; then
                case "$AVAILABLE" in
                    *" $2 "*) return 0 ;;
                    *) return 1 ;;
                esac
            fi
            builtin command "$@"
        }

        if [ -n "$T_PKG_ENV" ]; then
            export PKG_MANAGER="$T_PKG_ENV"
        else
            unset PKG_MANAGER 2>/dev/null || true
        fi

        detect_pkg_manager
INNER
}

assert_eq() {
    local label="$1" expected="$2" actual="$3"
    if [ "$expected" = "$actual" ]; then
        echo "  PASS: $label -> $actual"
        pass=$((pass+1))
    else
        echo "  FAIL: $label -> expected '$expected', got '$actual'"
        fail=$((fail+1))
    fi
}

echo "=== Non-Alpine: default order is bun > pnpm > yarn > npm ==="
assert_eq "all four available -> bun"        "bun"   "$(run_detect no 'bun pnpm yarn npm')"
assert_eq "no bun -> pnpm"                   "pnpm"  "$(run_detect no 'pnpm yarn npm')"
assert_eq "only yarn + npm -> yarn"          "yarn"  "$(run_detect no 'yarn npm')"
assert_eq "only npm -> npm"                  "npm"   "$(run_detect no 'npm')"
assert_eq "none available -> empty"          ""      "$(run_detect no '')"

echo ""
echo "=== Alpine: bun is demoted to last resort ==="
assert_eq "all four on Alpine -> pnpm"       "pnpm"  "$(run_detect yes 'bun pnpm yarn npm')"
assert_eq "bun + yarn on Alpine -> yarn"     "yarn"  "$(run_detect yes 'bun yarn')"
assert_eq "bun + npm on Alpine -> npm"       "npm"   "$(run_detect yes 'bun npm')"
assert_eq "only bun on Alpine -> bun"        "bun"   "$(run_detect yes 'bun')"
assert_eq "none on Alpine -> empty"          ""      "$(run_detect yes '')"

echo ""
echo "=== PKG_MANAGER env override is respected on both ==="
assert_eq "PKG_MANAGER=bun on Alpine -> bun" "bun"   "$(run_detect yes 'pnpm npm' bun)"
assert_eq "PKG_MANAGER=npm non-alpine -> npm" "npm"  "$(run_detect no 'bun pnpm yarn npm' npm)"
# Unknown override falls back to auto-detection.
assert_eq "PKG_MANAGER=garbage on Alpine -> pnpm" "pnpm" "$(run_detect yes 'pnpm npm' garbage)"

echo ""
echo "Results: $pass passed, $fail failed"
[ "$fail" -eq 0 ] || exit 1
