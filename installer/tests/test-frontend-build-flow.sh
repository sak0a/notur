#!/usr/bin/env bash
# test-frontend-build-flow.sh — verify frontend dependency install and build
# recovery logic in install.sh.
#
# Strategy: extract install_frontend_dependencies() and build_frontend() from
# install.sh, stub helper functions/commands, and assert the call flow for
# npm recovery, yarn-script fallback, and hard failure when dependencies never
# install.

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
            sed -n "/^${name}() {/,/^}/p" "$INSTALL_SH"
        }

        install_block="$(extract_func install_frontend_dependencies)"
        build_block="$(extract_func build_frontend)"
        if [ -z "$install_block" ] || [ -z "$build_block" ]; then
            echo "ERR: could not extract build helpers from install.sh"
            exit 99
        fi

        eval "$install_block"
        eval "$build_block"

        WORKDIR="$(mktemp -d)"
        trap 'rm -rf "$WORKDIR"' EXIT
        PANEL_DIR="$WORKDIR/panel"
        mkdir -p "$PANEL_DIR/node_modules/.bin"
        printf '{ "scripts": { "build:production": "yarn run clean && webpack", "clean": "cleanup" } }\n' > "$PANEL_DIR/package.json"
        : > "$PANEL_DIR/package-lock.json"

        LOG="$WORKDIR/log"
        : > "$LOG"

        record() { printf '%s\n' "$*" >> "$LOG"; }
        warn() { record "warn:$*"; }
        fix_webpack_cli_compat() { record "fix-webpack"; }
        script_uses_yarn() { record "script-uses-yarn:$2"; return 0; }
        has_pkg_script() { record "has-pkg-script:$2"; return 0; }
        pkg_exec() { record "pkg-exec:$*"; return 0; }

        command() {
            if [ "${1:-}" = "-v" ]; then
                case "$T_CASE:$2" in
                    npm_retry_then_build:npm|npm_retry_then_build_fail:npm|yarnless_script_fallback:npm) return 0 ;;
                    yarnless_script_fallback:pnpm) return 0 ;;
                    *) return 1 ;;
                esac
            fi
            builtin command "$@"
        }

        npm() {
            record "npm:$*"
            case "$T_CASE:$*" in
                npm_retry_then_build:ci\ --legacy-peer-deps) return 0 ;;
                npm_retry_then_build_fail:ci\ --legacy-peer-deps) return 1 ;;
                yarnless_script_fallback:run\ clean) return 0 ;;
                *) return 0 ;;
            esac
        }

        pnpm() {
            record "pnpm:$*"
            case "$T_CASE:$*" in
                yarnless_script_fallback:run\ clean) return 0 ;;
                *) return 0 ;;
            esac
        }

        pkg_install() {
            record "pkg-install"
            case "$T_CASE" in
                npm_retry_then_build|npm_retry_then_build_fail) return 1 ;;
                yarnless_script_fallback) return 0 ;;
            esac
        }

        pkg_run() {
            record "pkg-run:$1"
            case "$T_CASE" in
                npm_retry_then_build) return 0 ;;
                yarnless_script_fallback) return 1 ;;
                npm_retry_then_build_fail) return 0 ;;
            esac
        }

        case "$T_CASE" in
            yarnless_script_fallback)
                cat > "$PANEL_DIR/node_modules/.bin/webpack" <<'EOF'
#!/usr/bin/env sh
printf '%s\n' "webpack:$*" >> "$LOG"
exit 0
EOF
                chmod +x "$PANEL_DIR/node_modules/.bin/webpack"
                export LOG
                ;;
        esac

        PKG_MGR="npm"
        if [ "$T_CASE" = "yarnless_script_fallback" ]; then
            PKG_MGR="pnpm"
        fi

        build_frontend
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

assert_case_sequence() {
    local label="$1" case_name="$2"
    shift 2
    local out log
    out=$(run_case "$case_name" || true)

    if ! echo "$out" | grep -q '^RC=0$'; then
        echo "  FAIL: $label — expected RC=0, got:"
        echo "$out" | sed 's/^/      /'
        fail=$((fail+1))
        return
    fi

    log=$(printf '%s\n' "$out" | sed -n '/^LOG_START$/,/^LOG_END$/p')
    local prev_line=0 needle line
    for needle in "$@"; do
        line=$(printf '%s\n' "$log" | grep -nF "$needle" | head -n1 | cut -d: -f1)
        if [ -z "$line" ]; then
            echo "  FAIL: $label — missing '$needle', got:"
            echo "$out" | sed 's/^/      /'
            fail=$((fail+1))
            return
        fi
        if [ "$line" -le "$prev_line" ]; then
            echo "  FAIL: $label — '$needle' did not appear after the previous step, got:"
            echo "$out" | sed 's/^/      /'
            fail=$((fail+1))
            return
        fi
        prev_line="$line"
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

echo "=== npm recovery happens before any build fallback ==="
assert_case_contains \
    "npm retry with legacy-peer-deps builds successfully" \
    "npm_retry_then_build" \
    "pkg-install" \
    "warn:Standard npm install failed. Retrying with --legacy-peer-deps..." \
    "npm:ci --legacy-peer-deps" \
    "fix-webpack" \
    "pkg-run:build:production"
assert_case_not_contains \
    "npm retry path does not enter yarn fallback" \
    "npm_retry_then_build" \
    "script-uses-yarn:build:production" \
    "npm:run clean" \
    "pkg-exec:webpack --mode production"
assert_case_sequence \
    "npm retry path preserves install-then-build ordering" \
    "npm_retry_then_build" \
    "pkg-install" \
    "warn:Standard npm install failed. Retrying with --legacy-peer-deps..." \
    "npm:ci --legacy-peer-deps" \
    "fix-webpack" \
    "pkg-run:build:production"

echo ""
echo "=== yarn-script fallback only runs after deps are installed ==="
assert_case_contains \
    "fallback uses direct webpack when yarn is absent" \
    "yarnless_script_fallback" \
    "pkg-install" \
    "fix-webpack" \
    "pkg-run:build:production" \
    "script-uses-yarn:build:production" \
    "has-pkg-script:clean" \
    "pnpm:run clean" \
    "webpack:--mode production"

echo ""
echo "=== dependency install failure stops the build early ==="
assert_case_fails \
    "failed npm retry exits before build/fallback" \
    "npm_retry_then_build_fail" \
    "npm:ci --legacy-peer-deps"

echo ""
echo "Results: $pass passed, $fail failed"
[ "$fail" -eq 0 ] || exit 1
