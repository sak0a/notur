#!/usr/bin/env bash
set -euo pipefail

SUITE="${E2E_SUITE:-all}"
APP_URL="${APP_URL:-http://app}"

wait_for_service() {
    local url="$1"
    local max_retries="${2:-60}"
    local retries=0

    while [ "$retries" -lt "$max_retries" ]; do
        if curl -s -o /dev/null --max-time 5 "$url" 2>/dev/null; then
            return 0
        fi

        retries=$((retries + 1))
        sleep 2
    done

    return 1
}

wait_for_app() {
    echo "Waiting for app to be ready at ${APP_URL}..."
    if ! wait_for_service "${APP_URL}" 60; then
        echo "App did not become ready for browser tests." >&2
        exit 1
    fi
}

run_shell_suite() {
    bash /opt/notur/tests/E2E/e2e-test.sh
}

run_browser_suite() {
    wait_for_app
    cd /opt/notur
    npx playwright test --config=playwright.config.ts
}

case "${SUITE}" in
    all)
        run_shell_suite
        run_browser_suite
        ;;
    shell)
        run_shell_suite
        ;;
    browser)
        run_browser_suite
        ;;
    *)
        echo "Unknown E2E_SUITE '${SUITE}'. Expected one of: all, shell, browser." >&2
        exit 1
        ;;
esac
