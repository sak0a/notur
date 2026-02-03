#!/usr/bin/env bash
set -euo pipefail

# Notur Extension Framework Installer
# Usage: curl -sSL https://docs.notur.site/install.sh | bash
#   or:  bash install.sh [/path/to/pterodactyl]

NOTUR_VERSION="1.0.1"
PANEL_DIR="${1:-/var/www/pterodactyl}"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

info()  { echo -e "${BLUE}[Notur]${NC} $1"; }
ok()    { echo -e "${GREEN}[Notur]${NC} $1"; }
warn()  { echo -e "${YELLOW}[Notur]${NC} $1"; }
error() { echo -e "${RED}[Notur]${NC} $1" >&2; }
die()   { error "$1"; exit 1; }

# ── Pre-flight checks ────────────────────────────────────────────────────

info "Notur Extension Framework Installer v${NOTUR_VERSION}"
echo ""

# Check panel directory
if [ ! -f "${PANEL_DIR}/artisan" ]; then
    die "Pterodactyl Panel not found at ${PANEL_DIR}. Pass the path as an argument: bash install.sh /path/to/pterodactyl"
fi

if [ ! -f "${PANEL_DIR}/composer.json" ]; then
    die "Invalid Pterodactyl installation: composer.json not found."
fi

info "Panel directory: ${PANEL_DIR}"

# Check PHP
if ! command -v php &> /dev/null; then
    die "PHP is not installed."
fi

PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
info "PHP version: ${PHP_VERSION}"

if ! php -r "exit(version_compare(PHP_VERSION, '8.2.0', '>=') ? 0 : 1);"; then
    die "PHP 8.2+ is required. Found: $(php -v | head -1)"
fi

# Check Composer
if ! command -v composer &> /dev/null; then
    die "Composer is not installed."
fi

# Check Node/Bun
if ! command -v node &> /dev/null; then
    die "Node.js is not installed."
fi

if ! command -v bun &> /dev/null; then
    die "Bun is not installed. Install it from https://bun.sh"
fi

# Check sodium extension
if ! php -m | grep -q sodium; then
    warn "PHP sodium extension not found. Signature verification will be unavailable."
fi

ok "Pre-flight checks passed."
echo ""

# ── Step 1: Install Composer package ─────────────────────────────────────

info "Step 1/6: Installing notur/notur via Composer..."
cd "${PANEL_DIR}"
composer require notur/notur --no-interaction || die "Composer install failed."
ok "Composer package installed."

# ── Step 2: Patch Blade layout ───────────────────────────────────────────

info "Step 2/6: Patching Blade layout..."

# The panel's wrapper template contains <body> and </body>
# scripts.blade.php is a minimal binder — we inject into wrapper
WRAPPER_BLADE="${PANEL_DIR}/resources/views/templates/wrapper.blade.php"
SCRIPTS_BLADE="${PANEL_DIR}/resources/views/layouts/scripts.blade.php"

# Prefer injecting into the scripts binder (cleanest approach)
if [ -f "${SCRIPTS_BLADE}" ]; then
    TARGET_BLADE="${SCRIPTS_BLADE}"
elif [ -f "${WRAPPER_BLADE}" ]; then
    TARGET_BLADE="${WRAPPER_BLADE}"
else
    die "Could not find the panel's Blade layout template."
fi

if grep -q "notur::scripts" "${TARGET_BLADE}"; then
    warn "Notur scripts already included in Blade template."
else
    cp "${TARGET_BLADE}" "${TARGET_BLADE}.notur-backup"

    if [ "${TARGET_BLADE}" = "${SCRIPTS_BLADE}" ]; then
        # scripts.blade.php is a minimal file — append the include
        echo "" >> "${TARGET_BLADE}"
        echo "@include('notur::scripts')" >> "${TARGET_BLADE}"
    elif grep -q "</body>" "${TARGET_BLADE}"; then
        # Inject before </body> in wrapper.blade.php
        # Use perl for cross-platform compatibility (macOS sed differs from GNU sed)
        perl -i -pe 's|</body>|    \@include("notur::scripts")\n    </body>|' "${TARGET_BLADE}"
    else
        echo "" >> "${TARGET_BLADE}"
        echo "@include('notur::scripts')" >> "${TARGET_BLADE}"
    fi
    ok "Blade layout patched (${TARGET_BLADE##*/})."
fi

# ── Step 3: Apply React patches ──────────────────────────────────────────

info "Step 3/6: Applying React source patches..."

PATCH_DIR="$(dirname "$(realpath "$0")")/patches/v1.11"

if [ ! -d "${PATCH_DIR}" ]; then
    # Patches may be in the Composer vendor directory
    PATCH_DIR="${PANEL_DIR}/vendor/notur/notur/installer/patches/v1.11"
fi

if [ -d "${PATCH_DIR}" ]; then
    for patch in "${PATCH_DIR}"/*.patch; do
        if [ -f "${patch}" ]; then
            PATCH_NAME=$(basename "${patch}")
            info "  Applying: ${PATCH_NAME}"
            cd "${PANEL_DIR}"
            if patch --dry-run -p1 < "${patch}" &>/dev/null; then
                patch -p1 < "${patch}" || warn "  Failed to apply: ${PATCH_NAME}"
            else
                warn "  Patch already applied or cannot be applied: ${PATCH_NAME}"
            fi
        fi
    done
    ok "React patches applied."
else
    warn "Patch directory not found. Skipping React patches."
    warn "You may need to manually add slot containers to your React files."
fi

# ── Step 4: Rebuild frontend ─────────────────────────────────────────────

info "Step 4/6: Rebuilding frontend assets..."
cd "${PANEL_DIR}"

bun install && bun run build:production || die "Frontend build failed."

ok "Frontend rebuilt."

# ── Step 5: Create directories and copy bridge ───────────────────────────

info "Step 5/6: Setting up Notur directories..."

mkdir -p "${PANEL_DIR}/notur/extensions"
mkdir -p "${PANEL_DIR}/public/notur/extensions"

# Copy bridge.js to public
BRIDGE_JS="${PANEL_DIR}/vendor/notur/notur/bridge/dist/bridge.js"
if [ -f "${BRIDGE_JS}" ]; then
    cp "${BRIDGE_JS}" "${PANEL_DIR}/public/notur/bridge.js"
    ok "Bridge runtime installed."
else
    warn "Bridge runtime not found at ${BRIDGE_JS}. Build it with: cd vendor/notur/notur/bridge && bun run build"
fi

# Initialize extensions.json
if [ ! -f "${PANEL_DIR}/notur/extensions.json" ]; then
    echo '{"extensions":{}}' > "${PANEL_DIR}/notur/extensions.json"
fi

# ── Step 6: Run migrations ───────────────────────────────────────────────

info "Step 6/6: Running database migrations..."
cd "${PANEL_DIR}"
php artisan migrate --force || die "Migration failed."
ok "Migrations complete."

# ── Store checksums ──────────────────────────────────────────────────────

info "Storing file checksums..."
CHECKSUM_FILE="${PANEL_DIR}/notur/.checksums"
{
    echo "# Notur file checksums — generated $(date -u +%Y-%m-%dT%H:%M:%SZ)"
    if [ -f "${SCRIPTS_BLADE}" ]; then
        if command -v sha256sum &>/dev/null; then
            echo "layout: $(sha256sum "${TARGET_BLADE}" | cut -d' ' -f1)"
        elif command -v shasum &>/dev/null; then
            echo "layout: $(shasum -a 256 "${TARGET_BLADE}" | cut -d' ' -f1)"
        fi
    fi
} > "${CHECKSUM_FILE}"

# ── Done ─────────────────────────────────────────────────────────────────

echo ""
ok "============================================"
ok "  Notur v${NOTUR_VERSION} installed!"
ok "============================================"
echo ""
info "Next steps:"
info "  Install an extension:  php artisan notur:install vendor/name"
info "  List extensions:       php artisan notur:list"
info "  Manage extensions:     Browse to /admin/notur/extensions"
echo ""
