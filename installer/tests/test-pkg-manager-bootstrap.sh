#!/usr/bin/env bash
# test-pkg-manager-bootstrap.sh — verify interactive handling for a missing
# lockfile-selected package manager, including Yarn bootstrap and fallback.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
INSTALL_SH="$SCRIPT_DIR/../install.sh"

if [ ! -f "$INSTALL_SH" ]; then
    echo "FAIL: install.sh not found at $INSTALL_SH"
    exit 1
fi

pass=0
fail=0

run_case() {
    local case_name="$1"

    INSTALL_SH="$INSTALL_SH" \
    T_CASE="$case_name" \
    bash <<'INNER' 2>&1
        set +e

        extract_func() {
            local name="$1"
            sed -n "/^${name}()/,/^}/p" "$INSTALL_SH"
        }

        bootstrap_block="$(extract_func bootstrap_yarn)"
        fallback_block="$(extract_func detect_fallback_pkg_manager)"
        ensure_block="$(extract_func ensure_selected_pkg_manager)"
        if [ -z "$bootstrap_block" ] || [ -z "$fallback_block" ] || [ -z "$ensure_block" ]; then
            echo "ERR: could not extract package-manager bootstrap helpers from install.sh"
            exit 99
        fi

        eval "$bootstrap_block"
        eval "$fallback_block"
        eval "$ensure_block"

        LOG="$(mktemp)"
        trap 'rm -f "$LOG"' EXIT
        record() { printf '%s\n' "$*" >> "$LOG"; }

        warn() { record "warn:$*"; }
        info() { record "info:$*"; }
        die() { record "die:$*"; return 1; }
        get_node_packages() { record "get-node-packages"; echo "nodejs npm"; }
        install_sys_package() { record "install-sys:$*"; return 0; }
        is_alpine() { return 1; }
        is_interactive_shell() {
            case "$T_CASE" in
                noninteractive_fallback) return 1 ;;
                *) return 0 ;;
            esac
        }
        confirm() {
            record "confirm:$*"
            case "$T_CASE" in
                accept_install) return 0 ;;
                decline_install|decline_no_fallback) return 1 ;;
                *) return 1 ;;
            esac
        }

        command() {
            if [ "${1:-}" = "-v" ]; then
                case "$2" in
                    yarn)
                        case "$T_CASE" in
                            accept_install_after_bootstrap) return 0 ;;
                            *) return 1 ;;
                        esac
                        ;;
                    npm)
                        case "$T_CASE" in
                            accept_install|decline_install|noninteractive_fallback) return 0 ;;
                            *) return 1 ;;
                        esac
                        ;;
                    pnpm)
                        case "$T_CASE" in
                            decline_install|noninteractive_fallback) return 0 ;;
                            *) return 1 ;;
                        esac
                        ;;
                    bun)
                        return 1
                        ;;
                esac
            fi
            builtin command "$@"
        }

        npm() {
            record "npm:$*"
            case "$T_CASE:$*" in
                accept_install:install\ -g\ yarn) return 0 ;;
                *) return 0 ;;
            esac
        }

        PKG_MGR="yarn"
        ensure_selected_pkg_manager "yarn"
        rc=$?

        echo "RC=$rc"
        echo "PKG_MGR=$PKG_MGR"
        echo "LOG_START"
        cat "$LOG"
        echo "LOG_END"
INNER
}

assert_case_contains() {
    local label="$1" case_name="$2"
    shift 2
    local out
    out=$(run_case "$case_name" || true)

    if ! echo "$out" | grep -q '^RC=0$'; then
        echo "  FAIL: $label — expected RC=0, got:"
        echo "$out" | sed 's/^/      /'
        fail=$((fail+1))
        return
    fi

    local needle
    for needle in "$@"; do
        if ! echo "$out" | grep -qF "$needle"; then
            echo "  FAIL: $label — missing '$needle', got:"
            echo "$out" | sed 's/^/      /'
            fail=$((fail+1))
            return
        fi
    done

    echo "  PASS: $label"
    pass=$((pass+1))
}

assert_case_not_contains() {
    local label="$1" case_name="$2"
    shift 2
    local out
    out=$(run_case "$case_name" || true)

    if ! echo "$out" | grep -q '^RC=0$'; then
        echo "  FAIL: $label — expected RC=0, got:"
        echo "$out" | sed 's/^/      /'
        fail=$((fail+1))
        return
    fi

    local needle
    for needle in "$@"; do
        if echo "$out" | grep -qF "$needle"; then
            echo "  FAIL: $label — unexpectedly found '$needle', got:"
            echo "$out" | sed 's/^/      /'
            fail=$((fail+1))
            return
        fi
    done

    echo "  PASS: $label"
    pass=$((pass+1))
}

assert_case_fails() {
    local label="$1" case_name="$2" needle="$3"
    local out
    out=$(run_case "$case_name" || true)

    if ! echo "$out" | grep -q '^RC=1$' || ! echo "$out" | grep -qF "$needle"; then
        echo "  FAIL: $label — expected RC=1 and '$needle', got:"
        echo "$out" | sed 's/^/      /'
        fail=$((fail+1))
        return
    fi

    echo "  PASS: $label"
    pass=$((pass+1))
}

echo "=== accepting the prompt bootstraps yarn ==="
assert_case_contains \
    "accepting installs yarn via npm and keeps yarn selected" \
    "accept_install" \
    "PKG_MGR=yarn" \
    "confirm:Detected yarn.lock, but yarn is not installed. Install yarn and continue with the panel's original package manager?" \
    "info:Installing yarn to match yarn.lock..." \
    "npm:install -g yarn"

echo ""
echo "=== declining the prompt falls back when possible ==="
assert_case_contains \
    "declining falls back to another manager with a warning" \
    "decline_install" \
    "PKG_MGR=pnpm" \
    "warn:Proceeding with pnpm even though yarn.lock was detected. This may ignore the panel's lockfile and produce different dependency versions."
assert_case_not_contains \
    "declining does not install yarn" \
    "decline_install" \
    "npm:install -g yarn"

echo ""
echo "=== no prompt in non-interactive mode ==="
assert_case_contains \
    "non-interactive mode falls back automatically" \
    "noninteractive_fallback" \
    "PKG_MGR=pnpm" \
    "warn:yarn.lock was detected, but yarn is not installed and no interactive prompt is available. Falling back to pnpm; this may ignore the panel's lockfile."
assert_case_not_contains \
    "non-interactive mode does not prompt" \
    "noninteractive_fallback" \
    "confirm:Detected yarn.lock"

echo ""
echo "=== decline with no fallback fails clearly ==="
assert_case_fails \
    "declining with no alternative manager exits" \
    "decline_no_fallback" \
    "die:yarn.lock was detected, yarn is not installed, and no fallback package manager is available."

echo ""
echo "Results: $pass passed, $fail failed"
[ "$fail" -eq 0 ] || exit 1
