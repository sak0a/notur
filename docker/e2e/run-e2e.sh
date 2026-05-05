#!/usr/bin/env bash
set -euo pipefail

# Notur E2E Test Runner
# Orchestrates the Docker-based shell, browser, and destructive lifecycle end-to-end suites.
#
# Usage: bash docker/e2e/run-e2e.sh [--no-build] [--no-cache] [--rebuild-base] [--keep] [--suite all|shell|browser|install-uninstall]
#   --no-build           Skip rebuilding Docker images
#   --no-cache           Rebuild Docker images without cache
#   --rebuild-base       Rebuild the reusable E2E base image before app images
#   --keep               Keep containers running after tests
#   --suite <suite>      Select which test suite to run (default: all)

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
COMPOSE_FILE="${SCRIPT_DIR}/docker-compose.yml"
BASE_IMAGE="${E2E_BASE_IMAGE:-notur/e2e-base:php8.2-node22-panel1.12.2}"
APP_IMAGE="${E2E_APP_IMAGE:-notur/e2e-app:local}"
TEST_RUNNER_IMAGE="${E2E_TEST_RUNNER_IMAGE:-notur/e2e-test-runner:local}"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

info()  { echo -e "${BLUE}[E2E]${NC} $1"; }
ok()    { echo -e "${GREEN}[E2E]${NC} $1"; }
warn()  { echo -e "${YELLOW}[E2E]${NC} $1"; }
error() { echo -e "${RED}[E2E]${NC} $1" >&2; }

NO_BUILD=false
NO_CACHE=false
REBUILD_BASE=false
KEEP=false
SUITE=all

while [ "$#" -gt 0 ]; do
    case "$1" in
        --no-build)
            NO_BUILD=true
            shift
            ;;
        --no-cache)
            NO_CACHE=true
            shift
            ;;
        --rebuild-base)
            REBUILD_BASE=true
            shift
            ;;
        --keep)
            KEEP=true
            shift
            ;;
        --suite)
            shift
            if [ "$#" -eq 0 ]; then
                error "--suite requires a value: all, shell, browser, or install-uninstall"
                exit 1
            fi
            SUITE="$1"
            shift
            ;;
        *)
            error "Unknown argument: $1"
            exit 1
            ;;
    esac
done

case "$SUITE" in
    all|shell|browser|install-uninstall) ;;
    *)
        error "Invalid suite '$SUITE'. Expected: all, shell, browser, or install-uninstall"
        exit 1
        ;;
esac

cleanup() {
    if [ "$KEEP" = false ]; then
        info "Cleaning up containers..."
        docker compose -f "$COMPOSE_FILE" down -v --remove-orphans 2>/dev/null || true
    else
        warn "Containers kept running (--keep). Stop with: docker compose -f $COMPOSE_FILE down -v"
    fi
}

trap cleanup EXIT

compose() {
    E2E_BASE_IMAGE="$BASE_IMAGE" \
    E2E_APP_IMAGE="$APP_IMAGE" \
    E2E_TEST_RUNNER_IMAGE="$TEST_RUNNER_IMAGE" \
        docker compose -f "$COMPOSE_FILE" "$@"
}

check_panel_available() {
    local retries=0
    local max_retries=60

    until compose exec -T app bash -lc 'curl -fsS --max-time 10 http://127.0.0.1/auth/login >/dev/null || curl -fsS --max-time 10 http://127.0.0.1/ >/dev/null'; do
        retries=$((retries + 1))
        if [ "$retries" -ge "$max_retries" ]; then
            error "Panel did not become available within ${max_retries} attempts"
            return 1
        fi
        sleep 2
    done
}

info "Starting Notur E2E test suite"
echo ""

if [ "$NO_BUILD" = true ]; then
    REQUIRED_IMAGES=("$APP_IMAGE")
    if [ "$SUITE" != "install-uninstall" ]; then
        REQUIRED_IMAGES+=("$TEST_RUNNER_IMAGE")
    fi

    for image in "${REQUIRED_IMAGES[@]}"; do
        if ! docker image inspect "$image" >/dev/null 2>&1; then
            error "Required E2E image is missing: ${image}"
            echo ""
            echo "Build images first or run without --no-build."
            exit 1
        fi
    done
fi

# Step 1: Build images
if [ "$NO_BUILD" = false ]; then
    if [ "$REBUILD_BASE" = true ]; then
        info "Building reusable E2E base image: ${BASE_IMAGE}"
        "${SCRIPT_DIR}/build-base.sh"
        echo ""
    elif docker image inspect "$BASE_IMAGE" >/dev/null 2>&1; then
        ok "Reusable E2E base image is available: ${BASE_IMAGE}"
        echo ""
    else
        error "Reusable E2E base image is missing: ${BASE_IMAGE}"
        echo ""
        echo "Build it once with:"
        echo "  bash docker/e2e/build-base.sh"
        echo ""
        echo "Or let this runner rebuild it explicitly with:"
        echo "  bash docker/e2e/run-e2e.sh --rebuild-base --suite ${SUITE}"
        exit 1
    fi

    info "Building Docker images..."
    BUILD_SERVICES=(app)
    if [ "$SUITE" != "install-uninstall" ]; then
        BUILD_SERVICES+=(test-runner)
    fi

    if [ "$NO_CACHE" = true ]; then
        compose build --no-cache "${BUILD_SERVICES[@]}"
    else
        compose build "${BUILD_SERVICES[@]}"
    fi
    echo ""
fi

# Step 2: Start database and app services
info "Starting database and app services..."
compose up -d db app
echo ""

# Step 3: Wait for MySQL to be ready
info "Waiting for MySQL to be ready..."
RETRIES=0
MAX_RETRIES=60
until compose exec -T db mysqladmin ping -h 127.0.0.1 -u root -pnotur_e2e --silent 2>/dev/null; do
    RETRIES=$((RETRIES + 1))
    if [ "$RETRIES" -ge "$MAX_RETRIES" ]; then
        error "MySQL did not become ready within ${MAX_RETRIES} attempts"
        exit 1
    fi
    sleep 1
done
ok "MySQL is ready"
echo ""

if [ "$SUITE" = "install-uninstall" ]; then
    info "Checking panel availability before Notur installation..."
    check_panel_available
    ok "Panel is available before Notur installation"
    echo ""
fi

# Step 4: Bootstrap a usable panel with Notur and deterministic E2E data
info "Bootstrapping the Pterodactyl + Notur E2E environment..."
compose exec -T app bash /opt/notur/docker/e2e/setup-panel.sh
ok "Panel bootstrap complete"
echo ""

if [ "$SUITE" = "install-uninstall" ]; then
    info "Running destructive install/uninstall lifecycle suite..."
    compose exec -T app bash /opt/notur/docker/e2e/install-uninstall.sh
    ok "Install/uninstall lifecycle suite passed!"
    exit 0
fi

# Step 5: Run the requested test suite
info "Running E2E suite: ${SUITE}"
echo ""
compose run --rm -e E2E_SUITE="$SUITE" test-runner
EXIT_CODE=$?

echo ""
if [ "$EXIT_CODE" -eq 0 ]; then
    ok "All E2E tests passed!"
else
    error "E2E tests failed with exit code ${EXIT_CODE}"
fi

exit $EXIT_CODE
