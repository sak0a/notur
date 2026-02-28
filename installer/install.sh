#!/usr/bin/env bash
set -euo pipefail

#  ┌─────────┐   ███╗   ██╗ ██████╗ ████████╗██╗   ██╗██████╗
#  │         │   ████╗  ██║██╔═══██╗╚══██╔══╝██║   ██║██╔══██╗
#  │    N    │   ██╔██╗ ██║██║   ██║   ██║   ██║   ██║██████╔╝
#  │         │   ██║╚██╗██║██║   ██║   ██║   ██║   ██║██╔══██╗
#  └─────────┘   ██║ ╚████║╚██████╔╝   ██║   ╚██████╔╝██║  ██║
#                ╚═╝  ╚═══╝ ╚═════╝    ╚═╝    ╚═════╝ ╚═╝  ╚═╝
#
#  Extension Framework for Pterodactyl Panel
#  https://github.com/sak0a/notur
#
# ─────────────────────────────────────────────────────────────────────────────
#
# Usage: curl -sSL https://docs.notur.site/install.sh | bash
#   or:  bash install.sh [/path/to/pterodactyl]
#
# Supports:
#   - Standard bare-metal installations (Debian/Ubuntu, CentOS/RHEL, Alpine)
#   - Docker installations (official Pterodactyl Docker image, Coolify, etc.)
#
# ─────────────────────────────────────────────────────────────────────────────

NOTUR_VERSION="1.2.7"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
WHITE='\033[38;2;255;255;255m'
PURPLE='\033[38;2;124;58;237m'
PURPLE_BG='\033[48;2;124;58;237m'
NC='\033[0m'

info()  { echo -e "${BLUE}[Notur]${NC} $1"; }
ok()    { echo -e "${GREEN}[Notur]${NC} $1"; }
warn()  { echo -e "${YELLOW}[Notur]${NC} $1"; }
error() { echo -e "${RED}[Notur]${NC} $1" >&2; }
die()   { error "$1"; exit 1; }

step() {
    local num="$1"
    local msg="$2"
    if [ -t 1 ] && [ -z "${NO_COLOR:-}" ]; then
        echo -e "${PURPLE_BG} STEP ${num} ${NC} ${WHITE}${msg}${NC}"
    else
        echo "==> Step ${num}: ${msg}"
    fi
}

# ── Environment Detection ─────────────────────────────────────────────────

is_docker() {
    # Check for Docker container markers
    [ -f /.dockerenv ] || grep -q docker /proc/1/cgroup 2>/dev/null || \
        grep -q docker /proc/self/cgroup 2>/dev/null || \
        [ -f /run/.containerenv ]
}

is_alpine() {
    [ -f /etc/alpine-release ]
}

is_pterodactyl_docker() {
    # Official Pterodactyl Docker image has panel at /app with specific structure
    [ -f /app/artisan ] && [ -d /app/vendor ] && [ -f /etc/nginx/http.d/default.conf ] 2>/dev/null
}

# Validate that a directory contains Pterodactyl Panel (not just any Laravel app)
is_pterodactyl_panel() {
    local dir="$1"

    # Check 1: Pterodactyl-specific config file
    if [ -f "${dir}/config/pterodactyl.php" ]; then
        return 0
    fi

    # Check 2: pterodactyl/panel in composer.lock
    if [ -f "${dir}/composer.lock" ]; then
        if grep -q '"name": "pterodactyl/panel"' "${dir}/composer.lock" 2>/dev/null; then
            return 0
        fi
    fi

    # Check 3: Pterodactyl-specific models (Server, Node, Nest)
    if [ -f "${dir}/app/Models/Server.php" ] && [ -f "${dir}/app/Models/Node.php" ] && [ -f "${dir}/app/Models/Nest.php" ]; then
        return 0
    fi

    return 1
}

detect_panel_dir() {
    # Check common locations in order of specificity
    # Only return a path if it's actually Pterodactyl Panel
    local candidates=("/app" "/var/www/pterodactyl" "/var/www/html")

    for dir in "${candidates[@]}"; do
        if [ -f "${dir}/artisan" ] && is_pterodactyl_panel "${dir}"; then
            echo "${dir}"
            return
        fi
    done

    echo ""
}

detect_web_user() {
    # Determine the web server user based on environment
    if is_pterodactyl_docker || (is_alpine && is_docker); then
        # Prefer nginx in Docker environments if it exists, otherwise fall back
        if id -u nginx >/dev/null 2>&1; then
            echo "nginx"
        elif id -u www-data >/dev/null 2>&1; then
            echo "www-data"
        elif id -u apache >/dev/null 2>&1; then
            echo "apache"
        else
            echo "$(whoami)"
        fi
    elif is_alpine; then
        # Alpine bare-metal typically uses nginx
        if id -u nginx >/dev/null 2>&1; then
            echo "nginx"
        else
            echo "www-data"
        fi
    elif id -u www-data >/dev/null 2>&1; then
        echo "www-data"
    elif id -u nginx >/dev/null 2>&1; then
        echo "nginx"
    elif id -u apache >/dev/null 2>&1; then
        echo "apache"
    else
        echo "$(whoami)"
    fi
}

detect_environment() {
    if is_docker; then
        if is_alpine; then
            echo "docker-alpine"
        else
            echo "docker"
        fi
    elif is_alpine; then
        echo "alpine"
    else
        echo "bare-metal"
    fi
}

# Check if running in a Docker-like environment (centralized check for Docker-specific behavior)
is_docker_env() {
    case "$ENVIRONMENT" in
        docker*) return 0 ;;
        *) return 1 ;;
    esac
}

# Set environment variables
ENVIRONMENT=$(detect_environment)
WEB_USER=$(detect_web_user)
AUTO_PANEL_DIR=$(detect_panel_dir)
PANEL_DIR="${1:-${AUTO_PANEL_DIR:-/var/www/pterodactyl}}"

banner() {
    if [ -t 1 ] && [ -z "${NO_COLOR:-}" ]; then
        echo -e "${PURPLE}  ┌─────────┐   ███╗   ██╗ ██████╗ ████████╗██╗   ██╗██████╗${NC}"
        echo -e "${PURPLE}  │         │   ████╗  ██║██╔═══██╗╚══██╔══╝██║   ██║██╔══██╗${NC}"
        echo -e "${PURPLE}  │${WHITE}    N    ${PURPLE}│   ██╔██╗ ██║██║   ██║   ██║   ██║   ██║██████╔╝${NC}"
        echo -e "${PURPLE}  │         │   ██║╚██╗██║██║   ██║   ██║   ██║   ██║██╔══██╗${NC}"
        echo -e "${PURPLE}  └─────────┘   ██║ ╚████║╚██████╔╝   ██║   ╚██████╔╝██║  ██║${NC}"
        echo -e "${PURPLE}                ╚═╝  ╚═══╝ ╚═════╝    ╚═╝    ╚═════╝ ╚═╝  ╚═╝${NC}"
    else
        echo "  ┌─────────┐   ███╗   ██╗ ██████╗ ████████╗██╗   ██╗██████╗"
        echo "  │         │   ████╗  ██║██╔═══██╗╚══██╔══╝██║   ██║██╔══██╗"
        echo "  │    N    │   ██╔██╗ ██║██║   ██║   ██║   ██║   ██║██████╔╝"
        echo "  │         │   ██║╚██╗██║██║   ██║   ██║   ██║   ██║██╔══██╗"
        echo "  └─────────┘   ██║ ╚████║╚██████╔╝   ██║   ╚██████╔╝██║  ██║"
        echo "                ╚═╝  ╚═══╝ ╚═════╝    ╚═╝    ╚═════╝ ╚═╝  ╚═╝"
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

    # Read confirmations from the controlling terminal so piped/scripted stdin
    # (e.g. curl ... | bash) does not auto-decline prompts.
    if [ -r /dev/tty ]; then
        echo -en "${YELLOW}[Notur]${NC} ${prompt} [y/N]: " > /dev/tty
        read -r response < /dev/tty
    else
        warn "No interactive terminal available for prompt: ${prompt}"
        return 1
    fi

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

# Helper: Print manual Node.js install hints
print_node_install_hint() {
    local sys_pkg_mgr
    sys_pkg_mgr=$(detect_sys_pkg_manager)

    info "Install Node.js manually, then re-run this installer."
    case "$sys_pkg_mgr" in
        apk)
            info "Example: apk add --no-cache nodejs npm"
            ;;
        apt)
            info "Example: apt-get update && apt-get install -y nodejs npm"
            ;;
        dnf)
            info "Example: dnf install -y nodejs npm"
            ;;
        yum)
            info "Example: yum install -y nodejs npm"
            ;;
        pacman)
            info "Example: pacman -S --noconfirm nodejs npm"
            ;;
        *)
            info "Install Node.js from: https://nodejs.org/"
            ;;
    esac
}

# Helper: Install Alpine-specific requirements for Notur
install_alpine_requirements() {
    if ! is_alpine; then
        return 0
    fi

    info "Detected Alpine Linux. Checking required packages..."

    # Packages needed for Notur installation and frontend building
    # - bash: Script compatibility (Alpine uses ash by default)
    # - nodejs/npm: Frontend build
    # - git: Required by composer
    # - coreutils: GNU utilities (realpath, etc.)
    # - patch: For applying React patches
    # - build-base: For native node module compilation
    local required_packages=""

    # Check each package and add to install list if missing
    if ! command -v bash >/dev/null 2>&1; then
        required_packages="$required_packages bash"
    fi
    if ! command -v git >/dev/null 2>&1; then
        required_packages="$required_packages git"
    fi
    if ! command -v patch >/dev/null 2>&1; then
        required_packages="$required_packages patch"
    fi
    if ! command -v realpath >/dev/null 2>&1; then
        required_packages="$required_packages coreutils"
    fi
    # build-base is needed for node-gyp native modules
    if ! command -v make >/dev/null 2>&1; then
        required_packages="$required_packages build-base"
    fi

    if [ -n "$required_packages" ]; then
        info "Installing missing Alpine packages:$required_packages"
        if command -v apk >/dev/null 2>&1; then
            apk add --no-cache $required_packages || {
                warn "Failed to install some Alpine packages. Installation may fail."
                return 1
            }
            ok "Alpine packages installed."
        else
            warn "apk not found. Cannot install packages automatically."
            return 1
        fi
    else
        ok "All required Alpine packages are present."
    fi

    return 0
}

# Helper: Fix permissions for web server user
fix_permissions() {
    local dir="$1"
    if [ -d "$dir" ] && [ -n "$WEB_USER" ]; then
        # Only change ownership if running as root
        if [ "$(id -u)" = "0" ]; then
            chown -R "${WEB_USER}:${WEB_USER}" "$dir" 2>/dev/null || true
        fi
    fi
}

# ── Pre-flight checks ────────────────────────────────────────────────────

banner
info "Notur Extension Framework Installer v${NOTUR_VERSION}"
echo ""

# Display detected environment
info "Environment: ${ENVIRONMENT}"
info "Web user: ${WEB_USER}"
if [ -n "$AUTO_PANEL_DIR" ]; then
    info "Auto-detected panel at: ${AUTO_PANEL_DIR}"
fi

# Docker-specific warnings
if is_docker_env; then
    echo ""
    warn "Docker installation detected."
    warn "Ensure your docker-compose.yml includes volume mounts for Notur data:"
    warn "  volumes:"
    warn "    - 'notur-data:/app/notur/'"
    warn "    - 'notur-public:/app/public/notur/'"
    echo ""
fi

# Install Alpine requirements first (before other checks)
# Use || true to continue even if package installation fails - the script will
# fail later at a more specific point if required tools are missing
if is_alpine; then
    install_alpine_requirements || true
fi

echo ""

# Check panel directory
if [ ! -f "${PANEL_DIR}/artisan" ]; then
    die "Pterodactyl Panel not found at ${PANEL_DIR}. Pass the path as an argument: bash install.sh /path/to/pterodactyl"
fi

if [ ! -f "${PANEL_DIR}/composer.json" ]; then
    die "Invalid Pterodactyl installation: composer.json not found."
fi

# Validate this is actually Pterodactyl Panel, not just any Laravel app
if ! is_pterodactyl_panel "${PANEL_DIR}"; then
    error "Directory ${PANEL_DIR} appears to be a Laravel application, but not Pterodactyl Panel."
    error "Could not find Pterodactyl-specific markers (config/pterodactyl.php, pterodactyl/panel in composer.lock)."
    die "Please specify the correct Pterodactyl Panel path: bash install.sh /path/to/pterodactyl"
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
                print_node_install_hint
                die "Failed to install Node.js. Please install it manually and re-run the installer."
            fi
        else
            print_node_install_hint
            die "Node.js is required. Please install it manually and re-run the installer."
        fi
    else
        print_node_install_hint
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

pkg_exec() {
    case "$PKG_MGR" in
        bun)  bunx "$@" ;;
        pnpm) pnpm dlx "$@" ;;
        yarn) yarn dlx "$@" ;;
        npm)  npx "$@" ;;
    esac
}

run_tailwind_cli() {
    # Tailwind v4 ships CLI as @tailwindcss/cli; try legacy tailwindcss binary as fallback.
    pkg_exec @tailwindcss/cli -i resources/tailwind/notur.css -o bridge/dist/tailwind.css || \
        pkg_exec tailwindcss -i resources/tailwind/notur.css -o bridge/dist/tailwind.css
}

# Check whether a package.json declares a specific script.
has_pkg_script() {
    local package_json="$1"
    local script="$2"

    [ -f "$package_json" ] || return 1
    grep -q "\"${script}\"[[:space:]]*:" "$package_json"
}

# Check whether a package.json script command references yarn directly.
script_uses_yarn() {
    local package_json="$1"
    local script="$2"

    [ -f "$package_json" ] || return 1
    tr -d '\n' < "$package_json" | grep -q "\"${script}\"[[:space:]]*:[[:space:]]*\"[^\"]*yarn[[:space:]]"
}

# Check sodium extension
if ! php -m | grep -q sodium; then
    warn "PHP sodium extension not found. Signature verification will be unavailable."
fi

ok "Pre-flight checks passed."
echo ""

# ── Step 1: Install Composer package ─────────────────────────────────────

step "1/6" "Installing notur/notur via Composer..."
cd "${PANEL_DIR}"
composer require notur/notur --no-interaction || die "Composer install failed."
ok "Composer package installed."

# ── Step 2: Patch Blade layout ───────────────────────────────────────────

step "2/6" "Patching Blade layout..."

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

step "3/6" "Applying React source patches..."

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
            if [[ "${PATCH_NAME}" == *.reverse.patch ]]; then
                continue
            fi
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

step "4/6" "Rebuilding frontend assets..."
cd "${PANEL_DIR}"

# Enable legacy OpenSSL provider for Node.js 17+ compatibility with older webpack configs
export NODE_OPTIONS="${NODE_OPTIONS:-} --openssl-legacy-provider"

# Try normal install first, fall back to --legacy-peer-deps for dependency conflicts
build_frontend() {
    if pkg_install && pkg_run build:production; then
        return 0
    fi

    # Some panel builds hardcode yarn in package.json scripts
    # (e.g. "build:production": "yarn run clean && ...").
    # If yarn is missing, try a package-manager-agnostic fallback.
    if ! command -v yarn &> /dev/null && script_uses_yarn "${PANEL_DIR}/package.json" "build:production"; then
        warn "build:production references yarn, but yarn is not installed. Trying fallback build path..."

        # Run clean script with any available manager.
        if has_pkg_script "${PANEL_DIR}/package.json" "clean"; then
            if command -v bun &> /dev/null; then
                bun run clean || return 1
            elif command -v pnpm &> /dev/null; then
                pnpm run clean || return 1
            elif command -v npm &> /dev/null; then
                npm run clean || return 1
            else
                return 1
            fi
        fi

        # Run webpack directly in production mode.
        if [ -x "${PANEL_DIR}/node_modules/.bin/webpack" ]; then
            NODE_ENV=production "${PANEL_DIR}/node_modules/.bin/webpack" --mode production || return 1
            return 0
        fi

        pkg_exec webpack --mode production || return 1
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

step "5/6" "Setting up Notur directories..."

mkdir -p "${PANEL_DIR}/notur/extensions"
mkdir -p "${PANEL_DIR}/public/notur/extensions"
mkdir -p "${PANEL_DIR}/storage/notur"

NOTUR_DIR="${PANEL_DIR}/vendor/notur/notur"

# Copy bridge.js to public (build it if missing)
BRIDGE_JS="${PANEL_DIR}/vendor/notur/notur/bridge/dist/bridge.js"
if [ ! -f "${BRIDGE_JS}" ]; then
    warn "Bridge runtime not found. Building it now..."
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

# Copy Tailwind CSS to public (build it if missing), but only for package versions that use it
TAILWIND_CSS="${PANEL_DIR}/vendor/notur/notur/bridge/dist/tailwind.css"
NOTUR_SCRIPTS_BLADE="${NOTUR_DIR}/resources/views/scripts.blade.php"
TAILWIND_REQUIRED=0
if [ -f "${NOTUR_SCRIPTS_BLADE}" ] && grep -q "/notur/tailwind.css" "${NOTUR_SCRIPTS_BLADE}"; then
    TAILWIND_REQUIRED=1
fi

if [ "${TAILWIND_REQUIRED}" -eq 1 ]; then
    if [ ! -f "${TAILWIND_CSS}" ]; then
        warn "Tailwind CSS not found. Building it now..."
        if [ -d "${NOTUR_DIR}" ]; then
            cd "${NOTUR_DIR}"
            if has_pkg_script "${NOTUR_DIR}/package.json" "build:tailwind"; then
                if [ "$PKG_MGR" = "npm" ]; then
                    npm install --legacy-peer-deps && npm run build:tailwind
                else
                    pkg_install && pkg_run build:tailwind
                fi
            elif [ -f "${NOTUR_DIR}/resources/tailwind/notur.css" ]; then
                warn "build:tailwind script not found. Using direct Tailwind CLI fallback..."
                if [ "$PKG_MGR" = "npm" ]; then
                    npm install --legacy-peer-deps && run_tailwind_cli
                else
                    pkg_install && run_tailwind_cli
                fi
            else
                warn "Installed Notur package does not include Tailwind build assets. Skipping Tailwind CSS build."
            fi
            cd "${PANEL_DIR}"
        fi
    fi

    if [ -f "${TAILWIND_CSS}" ]; then
        cp "${TAILWIND_CSS}" "${PANEL_DIR}/public/notur/tailwind.css"
        ok "Tailwind CSS installed."
    else
        warn "Tailwind CSS could not be built. Please build it manually: cd vendor/notur/notur && npm install && npm run build:tailwind"
    fi
else
    info "Installed Notur package does not require shared Tailwind CSS. Skipping Tailwind CSS install."
fi

# Initialize extensions.json
if [ ! -f "${PANEL_DIR}/notur/extensions.json" ]; then
    echo '{"extensions":{}}' > "${PANEL_DIR}/notur/extensions.json"
fi

# Fix permissions for web server user
info "Setting permissions for ${WEB_USER}..."
fix_permissions "${PANEL_DIR}/notur"
fix_permissions "${PANEL_DIR}/public/notur"
fix_permissions "${PANEL_DIR}/storage/notur"
ok "Directory permissions set."

# ── Step 6: Run migrations ───────────────────────────────────────────────

step "6/6" "Running database migrations..."
cd "${PANEL_DIR}"

run_migrations() {
    if php artisan migrate --force; then
        return 0
    fi

    if php artisan tinker --execute="echo \\Illuminate\\Support\\Facades\\Schema::hasTable('notur_activity_logs') ? '1' : '0';" 2>/dev/null | grep -q '^1$'; then
        warn "Detected existing notur_activity_logs table. Marking migration as applied and retrying..."
        php artisan tinker --execute="
            if (\Illuminate\Support\Facades\Schema::hasTable('migrations')) {
                \$migration = '2026_02_03_000004_create_notur_activity_logs_table';
                \$exists = \Illuminate\Support\Facades\DB::table('migrations')->where('migration', \$migration)->exists();
                if (!\$exists) {
                    \$batch = ((int) (\Illuminate\Support\Facades\DB::table('migrations')->max('batch') ?? 0)) + 1;
                    \Illuminate\Support\Facades\DB::table('migrations')->insert(['migration' => \$migration, 'batch' => \$batch]);
                }
            }
        " >/dev/null 2>&1 || true
        php artisan migrate --force --isolated && return 0
    fi

    return 1
}

run_migrations || die "Migration failed."
ok "Migrations complete."

# ── Store checksums ──────────────────────────────────────────────────────

info "Storing file checksums..."
CHECKSUM_FILE="${PANEL_DIR}/notur/.checksums"

# Verify previously stored checksums before overwriting.
if [ -f "${CHECKSUM_FILE}" ] && [ -f "${TARGET_BLADE}" ]; then
    PREV_LAYOUT_HASH=$(grep '^layout:' "${CHECKSUM_FILE}" 2>/dev/null | awk '{print $2}')
    if [ -n "${PREV_LAYOUT_HASH:-}" ]; then
        if command -v sha256sum &>/dev/null; then
            CUR_LAYOUT_HASH=$(sha256sum "${TARGET_BLADE}" | cut -d' ' -f1)
        elif command -v shasum &>/dev/null; then
            CUR_LAYOUT_HASH=$(shasum -a 256 "${TARGET_BLADE}" | cut -d' ' -f1)
        else
            CUR_LAYOUT_HASH=""
        fi

        if [ -n "${CUR_LAYOUT_HASH}" ] && [ "${PREV_LAYOUT_HASH}" != "${CUR_LAYOUT_HASH}" ]; then
            warn "Previously tracked Blade layout checksum changed since last install."
            warn "This can be expected after panel updates, but review local template modifications."
        fi
    fi
fi

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
info "Environment: ${ENVIRONMENT}"
info "Panel directory: ${PANEL_DIR}"
info "Web user: ${WEB_USER}"
echo ""
info "Next steps:"
if is_docker_env; then
    info "  Install an extension:  docker exec -it <container> php artisan notur:install vendor/name"
    info "  List extensions:       docker exec -it <container> php artisan notur:list"
else
    info "  Install an extension:  php artisan notur:install vendor/name"
    info "  List extensions:       php artisan notur:list"
fi
info "  Manage extensions:     Browse to /admin/notur/extensions"
echo ""

# Docker-specific final notes
if is_docker_env; then
    warn "IMPORTANT: For Docker installations:"
    warn "  1. Add volume mounts to persist Notur data across container restarts:"
    warn "       - 'notur-data:/app/notur'"
    warn "       - 'notur-public:/app/public/notur'"
    warn "     Or use bind mounts: './notur:/app/notur' (host path on left side)"
    warn "  2. If using Coolify or similar, configure persistent storage for these paths."
    warn "  3. After updating the panel image, you may need to re-run this installer."
    echo ""
fi
