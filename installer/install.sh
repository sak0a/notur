#!/usr/bin/env bash
set -euo pipefail

# Notur Extension Framework Installer
# Usage: curl -sSL https://docs.notur.site/install.sh | bash
#   or:  bash install.sh [/path/to/pterodactyl]

NOTUR_VERSION="1.1.1"
PANEL_DIR="${1:-/var/www/pterodactyl}"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
WHITE='\033[38;2;255;255;255m'
PURPLE_BG='\033[48;2;124;58;237m'
NC='\033[0m'

info()  { echo -e "${BLUE}[Notur]${NC} $1"; }
ok()    { echo -e "${GREEN}[Notur]${NC} $1"; }
warn()  { echo -e "${YELLOW}[Notur]${NC} $1"; }
error() { echo -e "${RED}[Notur]${NC} $1" >&2; }
die()   { error "$1"; exit 1; }

banner() {
    if [ -t 1 ] && [ -z "${NO_COLOR:-}" ]; then
        echo -e "  ${PURPLE_BG}      ${NC}"
        echo -e "  ${PURPLE_BG}  ${WHITE}N${PURPLE_BG}   ${NC}  ${WHITE}Notur${NC}"
        echo -e "  ${PURPLE_BG}      ${NC}"
    else
        echo "  +------+  Notur"
        echo "  |  N  |"
        echo "  +------+"
    fi
}

# ── Helper: Detect system package manager ────────────────────────────────

detect_sys_pkg_manager() {
    if command -v apk &> /dev/null; then echo "apk"
    elif command -v apt-get &> /dev/null; then echo "apt"
    elif command -v dnf &> /dev/null; then echo "dnf"
    elif command -v yum &> /dev/null; then echo "yum"
    elif command -v pacman &> /dev/null; then echo "pacman"
    else echo ""
    fi
}

# Helper: Prompt user for confirmation
confirm() {
    local prompt="$1"
    local response
    echo -en "${YELLOW}[Notur]${NC} ${prompt} [y/N]: "
    read -r response
    case "$response" in
        [yY][eE][sS]|[yY]) return 0 ;;
        *) return 1 ;;
    esac
}

# Helper: Install a system package
install_sys_package() {
    local pkg_name="$1"
    local sys_pkg_mgr
    sys_pkg_mgr=$(detect_sys_pkg_manager)

    case "$sys_pkg_mgr" in
        apk)
            apk add --no-cache $pkg_name
            ;;
        apt)
            apt-get update && apt-get install -y $pkg_name
            ;;
        dnf)
            dnf install -y $pkg_name
            ;;
        yum)
            yum install -y $pkg_name
            ;;
        pacman)
            pacman -S --noconfirm $pkg_name
            ;;
        *)
            return 1
            ;;
    esac
}

# Helper: Get package names for different package managers
get_node_packages() {
    local sys_pkg_mgr
    sys_pkg_mgr=$(detect_sys_pkg_manager)

    case "$sys_pkg_mgr" in
        apk)     echo "nodejs npm" ;;
        apt)     echo "nodejs npm" ;;
        dnf|yum) echo "nodejs npm" ;;
        pacman)  echo "nodejs npm" ;;
        *)       echo "" ;;
    esac
}

# ── Pre-flight checks ────────────────────────────────────────────────────

banner
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

# Check Node.js
if ! command -v node &> /dev/null; then
    warn "Node.js is not installed."
    node_pkgs=$(get_node_packages)
    if [ -n "$node_pkgs" ]; then
        if confirm "Would you like to install Node.js automatically?"; then
            info "Installing Node.js..."
            if install_sys_package "$node_pkgs"; then
                ok "Node.js installed successfully."
            else
                die "Failed to install Node.js. Please install it manually and re-run the installer."
            fi
        else
            die "Node.js is required. Please install it manually and re-run the installer."
        fi
    else
        die "Node.js is not installed and automatic installation is not supported on this system. Please install Node.js manually."
    fi
fi

# Detect available package manager (prefer bun > pnpm > yarn > npm)
detect_pkg_manager() {
    if [ -n "${PKG_MANAGER:-}" ]; then
        # User specified via environment variable
        case "$PKG_MANAGER" in
            bun|pnpm|yarn|npm) echo "$PKG_MANAGER"; return ;;
            *) warn "Unknown PKG_MANAGER '$PKG_MANAGER', auto-detecting..." ;;
        esac
    fi

    if command -v bun &> /dev/null; then echo "bun"
    elif command -v pnpm &> /dev/null; then echo "pnpm"
    elif command -v yarn &> /dev/null; then echo "yarn"
    elif command -v npm &> /dev/null; then echo "npm"
    else echo ""
    fi
}

PKG_MGR=$(detect_pkg_manager)
if [ -z "$PKG_MGR" ]; then
    die "No package manager found. Install one of: bun, pnpm, yarn, or npm"
fi

info "Using package manager: ${PKG_MGR}"

# Package manager command helpers
pkg_install() {
    case "$PKG_MGR" in
        bun)  bun install ;;
        pnpm) pnpm install ;;
        yarn) yarn install ;;
        npm)  npm install ;;
    esac
}

pkg_run() {
    local script="$1"
    case "$PKG_MGR" in
        bun)  bun run "$script" ;;
        pnpm) pnpm run "$script" ;;
        yarn) yarn run "$script" ;;
        npm)  npm run "$script" ;;
    esac
}

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

# Detect panel version
detect_panel_version() {
    local version=""
    if [ -f "${PANEL_DIR}/composer.lock" ]; then
        version=$(grep -A1 '"name": "pterodactyl/panel"' "${PANEL_DIR}/composer.lock" | grep '"version"' | head -1 | sed 's/.*"version": "\([^"]*\)".*/\1/' || echo "")
    fi
    if [ -z "$version" ] && [ -f "${PANEL_DIR}/config/app.php" ]; then
        version=$(grep "'version'" "${PANEL_DIR}/config/app.php" | head -1 | sed "s/.*'version'.*'\([^']*\)'.*/\1/" || echo "")
    fi
    echo "$version"
}

PANEL_VERSION=$(detect_panel_version)
info "Detected panel version: ${PANEL_VERSION:-unknown}"

# Map to patch directory (1.11.x → v1.11, 1.12.x → v1.12)
case "$PANEL_VERSION" in
    1.12.*) PATCH_VERSION="v1.12" ;;
    1.11.*) PATCH_VERSION="v1.11" ;;
    *)      PATCH_VERSION="v1.11" ; warn "Unknown version, defaulting to v1.11 patches" ;;
esac

info "Using patch set: ${PATCH_VERSION}"

# Resolve script directory (realpath may not exist on Alpine)
resolve_path() {
    if command -v realpath &> /dev/null; then
        realpath "$1" 2>/dev/null || echo "$1"
    elif command -v readlink &> /dev/null; then
        readlink -f "$1" 2>/dev/null || echo "$1"
    else
        echo "$1"
    fi
}

SCRIPT_DIR="$(dirname "$(resolve_path "$0")")"
PATCH_DIR="${SCRIPT_DIR}/patches/${PATCH_VERSION}"

if [ ! -d "${PATCH_DIR}" ]; then
    # Patches may be in the Composer vendor directory
    PATCH_DIR="${PANEL_DIR}/vendor/notur/notur/installer/patches/${PATCH_VERSION}"
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

# Enable legacy OpenSSL provider for Node.js 17+ compatibility with older webpack configs
export NODE_OPTIONS="${NODE_OPTIONS:-} --openssl-legacy-provider"

# Try normal install first, fall back to --legacy-peer-deps for dependency conflicts
build_frontend() {
    if pkg_install && pkg_run build:production; then
        return 0
    fi

    # Check if this is npm with a peer dependency conflict
    if [ "$PKG_MGR" = "npm" ]; then
        warn "Standard npm install failed. Retrying with --legacy-peer-deps..."
        npm install --legacy-peer-deps && npm run build:production || return 1
        return 0
    fi

    return 1
}

build_frontend || die "Frontend build failed."

ok "Frontend rebuilt."

# ── Step 5: Create directories and copy bridge ───────────────────────────

info "Step 5/6: Setting up Notur directories..."

mkdir -p "${PANEL_DIR}/notur/extensions"
mkdir -p "${PANEL_DIR}/public/notur/extensions"

# Copy bridge.js to public (build it if missing)
BRIDGE_JS="${PANEL_DIR}/vendor/notur/notur/bridge/dist/bridge.js"
if [ ! -f "${BRIDGE_JS}" ]; then
    warn "Bridge runtime not found. Building it now..."
    NOTUR_DIR="${PANEL_DIR}/vendor/notur/notur"
    if [ -d "${NOTUR_DIR}" ]; then
        cd "${NOTUR_DIR}"
        # Install dependencies and build bridge
        if [ "$PKG_MGR" = "npm" ]; then
            npm install --legacy-peer-deps && npm run build:bridge
        else
            pkg_install && pkg_run build:bridge
        fi
        cd "${PANEL_DIR}"
    fi
fi

if [ -f "${BRIDGE_JS}" ]; then
    cp "${BRIDGE_JS}" "${PANEL_DIR}/public/notur/bridge.js"
    ok "Bridge runtime installed."
else
    die "Bridge runtime could not be built. Please build it manually: cd vendor/notur/notur && npm install && npm run build:bridge"
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
