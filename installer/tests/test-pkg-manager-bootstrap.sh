#!/usr/bin/env bash
# test-pkg-manager-bootstrap.sh — verify interactive menu handling for missing
# or ambiguous package-manager selection, including Yarn bootstrap and fallback.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
INSTALL_SH="$SCRIPT_DIR/../install.sh"

if [ ! -f "$INSTALL_SH" ]; then
    echo "FAIL: install.sh not found at $INSTALL_SH"
    exit 1
fi

pass=0
fail=0

run_prompt_case() {
    local case_name="$1"

    INSTALL_SH="$INSTALL_SH" \
    T_CASE="$case_name" \
    bash <<'INNER' 2>&1
        set +e

        extract_func() {
            local name="$1"
            sed -n "/^${name}()/,/^}$/p" "$INSTALL_SH"
        }

        display_block="$(extract_func package_manager_display_name)"
        status_block="$(extract_func package_manager_lockfile_status)"
        prompt_block="$(extract_func prompt_for_package_manager_selection)"
        if [ -z "$display_block" ] || [ -z "$status_block" ] || [ -z "$prompt_block" ]; then
            echo "ERR: could not extract prompt helpers from install.sh"
            exit 99
        fi

        eval "$display_block"
        eval "$status_block"
        eval "$prompt_block"

        LOG="$(mktemp)"
        trap 'rm -f "$LOG"' EXIT
        record() { printf '%s\n' "$*" >> "$LOG"; }

        warn() { record "warn:$*"; }
        print_prompt_line() { record "prompt-line:$*"; }
        package_manager_is_installed() {
            case "$1:$T_CASE" in
                yarn:missing_yarn_menu|yarn:multi_lockfile_menu) return 1 ;;
                bun:missing_yarn_menu|bun:multi_lockfile_menu) return 0 ;;
                pnpm:missing_yarn_menu|pnpm:multi_lockfile_menu) return 0 ;;
                npm:missing_yarn_menu|npm:multi_lockfile_menu) return 0 ;;
                *) return 1 ;;
            esac
        }
        count_words() {
            local count=0
            local item
            for item in $1; do
                count=$((count + 1))
            done
            printf '%s\n' "$count"
        }
        prompt_for_number() {
            record "prompt:$*"
            case "$T_CASE" in
                missing_yarn_menu) echo "1" ;;
                multi_lockfile_menu) echo "2" ;;
            esac
        }

        manager="$(prompt_for_package_manager_selection "yarn" "yarn npm")"
        rc=$?

        echo "RC=$rc"
        echo "MANAGER=$manager"
        echo "LOG_START"
        cat "$LOG"
        echo "LOG_END"
INNER
}

run_ensure_case() {
    local case_name="$1"

    INSTALL_SH="$INSTALL_SH" \
    T_CASE="$case_name" \
    bash <<'INNER' 2>&1
        set +e

        extract_func() {
            local name="$1"
            sed -n "/^${name}()/,/^}$/p" "$INSTALL_SH"
        }

        bootstrap_block="$(extract_func bootstrap_yarn)"
        fallback_block="$(extract_func detect_fallback_pkg_manager)"
        activate_block="$(extract_func activate_package_manager_selection)"
        ensure_block="$(extract_func ensure_selected_pkg_manager)"
        if [ -z "$bootstrap_block" ] || [ -z "$fallback_block" ] || [ -z "$activate_block" ] || [ -z "$ensure_block" ]; then
            echo "ERR: could not extract package-manager selection helpers from install.sh"
            exit 99
        fi

        eval "$bootstrap_block"
        eval "$fallback_block"
        eval "$activate_block"
        eval "$ensure_block"

        LOG="$(mktemp)"
        trap 'rm -f "$LOG"' EXIT
        record() { printf '%s\n' "$*" >> "$LOG"; }

        warn() { record "warn:$*"; }
        info() { record "info:$*"; }
        die() { record "die:$*"; echo "die:$*"; exit 1; }
        package_manager_display_name() {
            case "$1" in
                yarn) echo "Yarn" ;;
                pnpm) echo "PNPM" ;;
                npm) echo "NPM" ;;
                bun) echo "Bun" ;;
                *) echo "$1" ;;
            esac
        }
        package_manager_is_installed() {
            case "$1:$T_CASE" in
                yarn:single_lockfile_installed) return 0 ;;
                bun:multiple_lockfiles_choose_bun|bun:noninteractive_missing_yarn) return 0 ;;
                pnpm:unsupported_missing_choice) return 0 ;;
                npm:multiple_lockfiles_choose_bun|npm:noninteractive_missing_yarn) return 0 ;;
                *) return 1 ;;
            esac
        }
        count_words() {
            local count=0
            local item
            for item in $1; do
                count=$((count + 1))
            done
            printf '%s\n' "$count"
        }
        is_alpine() { return 1; }
        is_interactive_shell() {
            case "$T_CASE" in
                noninteractive_missing_yarn) return 1 ;;
                *) return 0 ;;
            esac
        }
        get_node_packages() { record "get-node-packages"; echo "nodejs npm"; }
        install_sys_package() { record "install-sys:$*"; return 0; }
        npm() { record "npm:$*"; return 0; }
        command() {
            if [ "${1:-}" = "-v" ]; then
                case "$2:$T_CASE" in
                    yarn:single_lockfile_installed) return 0 ;;
                    bun:multiple_lockfiles_choose_bun|bun:noninteractive_missing_yarn) return 0 ;;
                    npm:multiple_lockfiles_choose_bun|npm:noninteractive_missing_yarn|npm:missing_yarn_choose_yarn) return 0 ;;
                    *) return 1 ;;
                esac
            fi
            builtin command "$@"
        }
        prompt_for_package_manager_selection() {
            record "menu:$1|$2"
            case "$T_CASE" in
                multiple_lockfiles_choose_bun) echo "bun" ;;
                missing_yarn_choose_yarn) echo "yarn" ;;
                cancel_selection) return 1 ;;
            esac
        }

        PKG_MGR="yarn"
        case "$T_CASE" in
            multiple_lockfiles_choose_bun)
                ensure_selected_pkg_manager "yarn" "yarn npm"
                ;;
            missing_yarn_choose_yarn)
                ensure_selected_pkg_manager "yarn" "yarn"
                ;;
            noninteractive_missing_yarn)
                ensure_selected_pkg_manager "yarn" "yarn"
                ;;
            single_lockfile_installed)
                ensure_selected_pkg_manager "yarn" "yarn"
                ;;
            unsupported_missing_choice)
                ensure_selected_pkg_manager "yarn" "yarn"
                ;;
            cancel_selection)
                ensure_selected_pkg_manager "yarn" "yarn npm"
                ;;
        esac
        rc=$?

        echo "RC=$rc"
        echo "PKG_MGR=$PKG_MGR"
        echo "LOG_START"
        cat "$LOG"
        echo "LOG_END"
INNER
}

assert_case_contains() {
    local label="$1" mode="$2" case_name="$3"
    shift 3
    local out
    if [ "$mode" = "prompt" ]; then
        out=$(run_prompt_case "$case_name" || true)
    else
        out=$(run_ensure_case "$case_name" || true)
    fi

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
    local label="$1" mode="$2" case_name="$3"
    shift 3
    local out
    if [ "$mode" = "prompt" ]; then
        out=$(run_prompt_case "$case_name" || true)
    else
        out=$(run_ensure_case "$case_name" || true)
    fi

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
    out=$(run_ensure_case "$case_name" || true)

    if echo "$out" | grep -q '^RC=0$' || ! echo "$out" | grep -qF "$needle"; then
        echo "  FAIL: $label — expected failure and '$needle', got:"
        echo "$out" | sed 's/^/      /'
        fail=$((fail+1))
        return
    fi

    echo "  PASS: $label"
    pass=$((pass+1))
}

echo "=== interactive menu output ==="
assert_case_contains \
    "menu lists statuses and recommends yarn when yarn.lock exists" \
    "prompt" \
    "missing_yarn_menu" \
    "MANAGER=yarn" \
    "prompt-line:[Notur] Detected multiple frontend package-manager signals for this panel." \
    "prompt-line:[Notur] Choose how to continue:" \
    "prompt-line:  1. Yarn (not installed, lockfile found) (Recommended)" \
    "prompt-line:  2. Bun (installed, no lockfile found)" \
    "prompt-line:  3. PNPM (installed, no lockfile found)" \
    "prompt-line:  4. NPM (installed, lockfile found)" \
    "prompt-line:  5. Cancel installer"

echo ""
echo "=== interactive selection flow ==="
assert_case_contains \
    "multiple lockfiles trigger menu even when yarn is already not preferred by user" \
    "ensure" \
    "multiple_lockfiles_choose_bun" \
    "PKG_MGR=bun" \
    "menu:yarn|yarn npm" \
    "warn:Proceeding with bun even though yarn is recommended from the detected lockfile state. This may ignore the panel's lockfile and produce different dependency versions."
assert_case_contains \
    "choosing yarn bootstraps it and keeps yarn selected" \
    "ensure" \
    "missing_yarn_choose_yarn" \
    "PKG_MGR=yarn" \
    "menu:yarn|yarn" \
    "info:Installing yarn to match yarn.lock..." \
    "npm:install -g yarn"
assert_case_not_contains \
    "single installed lockfile manager does not open the menu" \
    "ensure" \
    "single_lockfile_installed" \
    "menu:"

echo ""
echo "=== non-interactive and unsupported choices ==="
assert_case_contains \
    "non-interactive mode still falls back deterministically" \
    "ensure" \
    "noninteractive_missing_yarn" \
    "PKG_MGR=bun" \
    "warn:yarn.lock was detected, but yarn is not installed and no interactive prompt is available. Falling back to bun; this may ignore the panel's lockfile."
assert_case_fails \
    "cancelling the interactive selection exits clearly" \
    "cancel_selection" \
    "die:Package manager selection cancelled."

echo ""
echo "Results: $pass passed, $fail failed"
[ "$fail" -eq 0 ] || exit 1
