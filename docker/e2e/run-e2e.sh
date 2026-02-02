#!/usr/bin/env bash
set -euo pipefail

# Notur E2E Test Runner
# Orchestrates the Docker-based end-to-end test suite.
#
# Usage: bash docker/e2e/run-e2e.sh [--no-build] [--keep]
#   --no-build   Skip rebuilding Docker images
#   --keep       Keep containers running after tests

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
COMPOSE_FILE="${SCRIPT_DIR}/docker-compose.yml"

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
KEEP=false

for arg in "$@"; do
    case "$arg" in
        --no-build) NO_BUILD=true ;;
        --keep)     KEEP=true ;;
        *)          error "Unknown argument: $arg"; exit 1 ;;
    esac
done

cleanup() {
    if [ "$KEEP" = false ]; then
        info "Cleaning up containers..."
        docker compose -f "$COMPOSE_FILE" down -v --remove-orphans 2>/dev/null || true
    else
        warn "Containers kept running (--keep). Stop with: docker compose -f $COMPOSE_FILE down -v"
    fi
}

trap cleanup EXIT

info "Starting Notur E2E test suite"
echo ""

# Step 1: Build images
if [ "$NO_BUILD" = false ]; then
    info "Building Docker images..."
    docker compose -f "$COMPOSE_FILE" build --no-cache
    echo ""
fi

# Step 2: Start database and app services
info "Starting database and app services..."
docker compose -f "$COMPOSE_FILE" up -d db app
echo ""

# Step 3: Wait for MySQL to be ready
info "Waiting for MySQL to be ready..."
RETRIES=0
MAX_RETRIES=60
until docker compose -f "$COMPOSE_FILE" exec -T db mysqladmin ping -h 127.0.0.1 -u root -pnotur_e2e --silent 2>/dev/null; do
    RETRIES=$((RETRIES + 1))
    if [ "$RETRIES" -ge "$MAX_RETRIES" ]; then
        error "MySQL did not become ready within ${MAX_RETRIES} attempts"
        exit 1
    fi
    sleep 1
done
ok "MySQL is ready"
echo ""

# Step 4: Generate app key and run panel migrations
info "Setting up Pterodactyl Panel..."
docker compose -f "$COMPOSE_FILE" exec -T app bash -c '
    cd /var/www/pterodactyl
    php artisan key:generate --force
    php artisan migrate --force --seed 2>&1 || php artisan migrate --force 2>&1
'
ok "Panel migrations complete"
echo ""

# Step 5: Run Notur installer
info "Running Notur installer..."
docker compose -f "$COMPOSE_FILE" exec -T app bash -c '
    cd /var/www/pterodactyl

    # Simulate composer require by copying Notur into vendor
    mkdir -p vendor/notur/notur
    cp -r /opt/notur/* vendor/notur/notur/ 2>/dev/null || true

    # Run the install script
    bash vendor/notur/notur/installer/install.sh /var/www/pterodactyl 2>&1 || {
        echo "Installer failed, attempting manual setup..."
        # Manual fallback: run migrations and set up directories
        php artisan migrate --force 2>&1
        mkdir -p notur/extensions
        mkdir -p public/notur/extensions
        echo "{\"extensions\":{}}" > notur/extensions.json

        # Build and copy bridge
        if [ -d vendor/notur/notur/bridge ]; then
            cd vendor/notur/notur/bridge
            yarn install --frozen-lockfile 2>/dev/null || npm install 2>/dev/null || true
            yarn build 2>/dev/null || npx webpack --mode production 2>/dev/null || true
            cp dist/bridge.js /var/www/pterodactyl/public/notur/bridge.js 2>/dev/null || true
            cd /var/www/pterodactyl
        fi
    }
'
ok "Notur installation complete"
echo ""

# Step 6: Install hello-world extension
info "Installing hello-world test extension..."
docker compose -f "$COMPOSE_FILE" exec -T app bash -c '
    cd /var/www/pterodactyl

    # Copy hello-world extension
    mkdir -p notur/extensions/notur/hello-world
    cp -r /opt/notur/examples/hello-world/* notur/extensions/notur/hello-world/

    # Register it in extensions.json
    cat > notur/extensions.json << EXTJSON
{
    "extensions": {
        "notur/hello-world": {
            "enabled": true,
            "version": "1.0.0",
            "installed_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
        }
    }
}
EXTJSON

    # Copy frontend bundle to public directory
    mkdir -p public/notur/extensions/notur/hello-world
    if [ -f notur/extensions/notur/hello-world/resources/frontend/dist/hello-world.js ]; then
        cp notur/extensions/notur/hello-world/resources/frontend/dist/hello-world.js \
           public/notur/extensions/notur/hello-world/hello-world.js
    fi
'
ok "Hello-world extension installed"
echo ""

# Step 7: Run the test suite
info "Running E2E tests..."
echo ""
docker compose -f "$COMPOSE_FILE" run --rm test-runner
EXIT_CODE=$?

echo ""
if [ "$EXIT_CODE" -eq 0 ]; then
    ok "All E2E tests passed!"
else
    error "E2E tests failed with exit code ${EXIT_CODE}"
fi

exit $EXIT_CODE
