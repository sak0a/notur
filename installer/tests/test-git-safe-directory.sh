#!/usr/bin/env bash
# test-git-safe-directory.sh — verify install.sh trusts the panel directory
# in Git before Composer runs, without duplicating existing config entries.

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

        func_block=$(sed -n "/^ensure_git_safe_directory()/,/^}/p" "$INSTALL_SH")
        if [ -z "$func_block" ]; then
            echo "ERR: could not extract ensure_git_safe_directory from install.sh"
            exit 99
        fi

        eval "$func_block"

        WORKDIR="$(mktemp -d)"
        trap 'rm -rf "$WORKDIR"' EXIT
        PANEL_DIR="$WORKDIR/panel"
        mkdir -p "$PANEL_DIR"
        CANONICAL_DIR="$(cd "$PANEL_DIR" && pwd -P)"

        LOG="$WORKDIR/log"
        : > "$LOG"

        info() { printf 'info:%s\n' "$*" >> "$LOG"; }
        warn() { printf 'warn:%s\n' "$*" >> "$LOG"; }

        command() {
            if [ "${1:-}" = "-v" ] && [ "${2:-}" = "git" ]; then
                case "$T_CASE" in
                    no_git) return 1 ;;
                    *) return 0 ;;
                esac
            fi
            builtin command "$@"
        }

        git() {
            printf 'git:%s\n' "$*" >> "$LOG"
            case "$*" in
                "config --global --get-all safe.directory")
                    case "$T_CASE" in
                        already_safe)
                            printf '%s\n' "$CANONICAL_DIR"
                            ;;
                    esac
                    return 0
                    ;;
                "config --global --add safe.directory $CANONICAL_DIR")
                    case "$T_CASE" in
                        add_safe|add_safe_warn) return 0 ;;
                    esac
                    return 1
                    ;;
            esac
            return 0
        }

        ensure_git_safe_directory "$PANEL_DIR"
        rc=$?

        echo "RC=$rc"
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

echo "=== missing safe.directory is added once ==="
assert_case_contains \
    "helper adds safe.directory entry when git is available" \
    "add_safe" \
    "git:config --global --get-all safe.directory" \
    "info:Marking " \
    "git:config --global --add safe.directory "

echo ""
echo "=== existing safe.directory is left alone ==="
assert_case_contains \
    "helper checks existing entries first" \
    "already_safe" \
    "git:config --global --get-all safe.directory"
assert_case_not_contains \
    "helper does not duplicate safe.directory" \
    "already_safe" \
    "git:config --global --add safe.directory " \
    "info:Marking "

echo ""
echo "=== missing git is a no-op ==="
assert_case_not_contains \
    "helper exits quietly when git is unavailable" \
    "no_git" \
    "git:config" \
    "info:Marking " \
    "warn:"

echo ""
echo "Results: $pass passed, $fail failed"
[ "$fail" -eq 0 ] || exit 1
