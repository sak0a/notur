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

NOTUR_VERSION="1.5.2"
MIN_NODE_MAJOR="${MIN_NODE_MAJOR:-22}"

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
    if ( : < /dev/tty ) 2>/dev/null; then
        print_prompt_line "${YELLOW}[Notur]${NC} ${prompt} [y/N]: "
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

is_interactive_shell() {
    ( : < /dev/tty ) 2>/dev/null
}

print_prompt_line() {
    local text="$1"

    if ( : < /dev/tty ) 2>/dev/null && [ -w /dev/tty ]; then
        printf '%b\n' "$text" > /dev/tty
    else
        printf '%b\n' "$text" >&2
    fi
}

prompt_for_number() {
    local prompt="$1"
    local response

    if ! ( : < /dev/tty ) 2>/dev/null; then
        warn "No interactive terminal available for prompt: ${prompt}"
        return 1
    fi

    print_prompt_line "${YELLOW}[Notur]${NC} ${prompt}: "
    read -r response < /dev/tty
    printf '%s\n' "$response"
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

# Helper: Install distro-specific build requirements for Notur.
#
# Logical dependencies map to per-distro package names. Each dep is skipped
# when its `command -v` probe already succeeds (libstdc++ uses apk's pkg db
# since it's a runtime library, not a command).
#
#   Logical    | apk         | apt              | dnf/yum          | pacman
#   -----------+-------------+------------------+------------------+------------
#   bash       | bash        | bash             | bash             | bash
#   git        | git         | git              | git              | git
#   patch      | patch       | patch            | patch            | patch
#   make       | build-base  | build-essential  | make gcc-c++     | base-devel
#   perl       | perl        | perl             | perl             | perl
#   python3    | python3     | python3          | python3          | python
#   coreutils  | coreutils   | (built-in)       | (built-in)       | (built-in)
#   libstdc++  | libstdc++   | (built-in)       | (built-in)       | (built-in)
#
# coreutils + libstdc++ rows are Alpine-only (musl + busybox quirks). All
# other distros ship these in their base install.
install_distro_requirements() {
    local sys_pkg_mgr
    sys_pkg_mgr=$(detect_sys_pkg_manager)

    if [ -z "$sys_pkg_mgr" ]; then
        warn "No supported system package manager detected. Skipping requirements bootstrap."
        return 0
    fi

    info "Detected ${sys_pkg_mgr}. Checking required packages..."

    local required_packages=""

    if ! command -v bash >/dev/null 2>&1; then
        required_packages="$required_packages bash"
    fi
    if ! command -v git >/dev/null 2>&1; then
        required_packages="$required_packages git"
    fi
    if ! command -v patch >/dev/null 2>&1; then
        required_packages="$required_packages patch"
    fi
    if ! command -v perl >/dev/null 2>&1; then
        required_packages="$required_packages perl"
    fi

    for tool in tar gzip; do
        if ! command -v "$tool" >/dev/null 2>&1; then
            required_packages="$required_packages $tool"
        fi
    done

    # make + C++ compiler — pkg name varies wildly per distro (build-base on
    # alpine, build-essential on debian/ubuntu, etc.). node-gyp needs both
    # make AND a C++ compiler to build native modules, so probe for `g++`
    # in addition to `make` — a system can have one without the other on
    # stripped images, and missing g++ would only surface mid-npm-install.
    if ! command -v make >/dev/null 2>&1 || ! command -v g++ >/dev/null 2>&1; then
        case "$sys_pkg_mgr" in
            apk)     required_packages="$required_packages build-base" ;;
            apt)     required_packages="$required_packages build-essential" ;;
            dnf|yum) required_packages="$required_packages make gcc-c++" ;;
            pacman)  required_packages="$required_packages base-devel" ;;
        esac
    fi

    # python3 — Arch's package is just `python` (which is python 3.x).
    if ! command -v python3 >/dev/null 2>&1; then
        case "$sys_pkg_mgr" in
            pacman) required_packages="$required_packages python" ;;
            *)      required_packages="$required_packages python3" ;;
        esac
    fi

    # coreutils — only Alpine needs it; everyone else ships GNU coreutils
    # (realpath, etc.) in their base install.
    if [ "$sys_pkg_mgr" = "apk" ] && ! command -v realpath >/dev/null 2>&1; then
        required_packages="$required_packages coreutils"
    fi

    # libstdc++ — only Alpine/musl needs it for prebuilt node binaries
    # (esbuild, swc, …). Probe via apk's pkg db since there's no command.
    if [ "$sys_pkg_mgr" = "apk" ] && ! apk info -e libstdc++ >/dev/null 2>&1; then
        required_packages="$required_packages libstdc++"
    fi

    if [ -z "$required_packages" ]; then
        ok "All required packages are present."
        return 0
    fi

    info "Installing missing packages:$required_packages"
    if install_sys_package "$required_packages"; then
        ok "Packages installed."
        return 0
    else
        warn "Failed to install some packages. Installation may fail later."
        return 1
    fi
}

# Helper: Fix permissions for web server user
fix_permissions() {
    local dir="$1"
    if [ -d "$dir" ] && [ -n "$WEB_USER" ]; then
        # Only change ownership if running as root
        if [ "$(id -u)" = "0" ]; then
            local web_group
            web_group=$(id -gn "$WEB_USER") || return 1
            chown -R "${WEB_USER}:${web_group}" "$dir" || return 1
        fi
    fi
}

# Artisan can create state locks as root, including during the final cache clear.
# Run on failure too, so a partially completed install does not lock out PHP.
finalize_permissions() {
    local status="$1" dir
    for dir in "${PANEL_DIR}/notur" "${PANEL_DIR}/public/notur" "${PANEL_DIR}/storage/notur"; do
        if ! fix_permissions "$dir"; then
            error "Could not set ownership of ${dir} for ${WEB_USER}. Repair permissions before using Notur."
            if [ "$status" -eq 0 ]; then status=1; fi
        fi
    done
    return "$status"
}

# Helper: trust the panel directory in Git so Composer can inspect VCS
# metadata even when the container user differs from the bind-mounted owner.
ensure_git_safe_directory() {
    local dir="$1"
    local canonical_dir

    if ! command -v git >/dev/null 2>&1; then
        return 0
    fi

    canonical_dir="$(cd "$dir" 2>/dev/null && pwd -P)" || return 0

    if git config --global --get-all safe.directory 2>/dev/null | grep -Fx -- "$canonical_dir" >/dev/null 2>&1; then
        return 0
    fi

    info "Marking ${canonical_dir} as a Git safe.directory for Composer..."
    if ! git config --global --add safe.directory "$canonical_dir" >/dev/null 2>&1; then
        warn "Could not update Git safe.directory for ${canonical_dir}. Composer may emit ownership warnings."
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
    warn "    - 'notur-data:${PANEL_DIR}/notur/'"
    warn "    - 'notur-public:${PANEL_DIR}/public/notur/'"
    echo ""
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

# Bootstrap only after confirming the requested panel and PHP tooling exist.
install_distro_requirements || die "Could not install required system build tools."
for tool in git patch perl tar gzip make g++ python3; do
    command -v "$tool" >/dev/null 2>&1 || die "Required build tool is missing: $tool"
done

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

# === node-version-check-start ===
# Verify Node.js meets the minimum major version (Pterodactyl v1.12 requires Node 22+).
# Read raw output, strip leading "v", split major. Use POSIX [ ] tests, not [[ ]].

# Validate MIN_NODE_MAJOR is a positive integer before using it in -lt below;
# otherwise `[ N -lt foo ]` errors with "integer expression expected" and
# aborts the whole installer under set -e.
case "$MIN_NODE_MAJOR" in
    ''|0|*[!0-9]*)
        die "Invalid MIN_NODE_MAJOR='${MIN_NODE_MAJOR}': must be a positive integer."
        ;;
esac

if ! node_version_raw=$(node --version 2>/dev/null); then
    error "Failed to read Node.js version (node --version exited non-zero)."
    print_node_install_hint
    die "Reinstall Node.js and re-run the installer."
fi

node_version="${node_version_raw#v}"
node_major="${node_version%%.*}"

# Reject empty or non-numeric majors.
case "$node_major" in
    ''|*[!0-9]*)
        error "Could not parse Node.js major version from: '${node_version_raw}'"
        print_node_install_hint
        die "Reinstall Node.js and re-run the installer."
        ;;
esac

if [ "$node_major" -lt "$MIN_NODE_MAJOR" ]; then
    error "Node.js ${node_major}.x is too old. Pterodactyl Panel v1.12 requires Node.js ${MIN_NODE_MAJOR}+."
    # Don't call print_node_install_hint here — it suggests `apt install nodejs`
    # which on Ubuntu 24.04 / Debian 12 still gives Node 18, sending the user
    # into a fail-loop. Instead, give per-distro upgrade guidance that actually
    # produces Node ${MIN_NODE_MAJOR}+.
    sys_pkg_mgr=$(detect_sys_pkg_manager)
    info "Upgrade options:"
    case "$sys_pkg_mgr" in
        apt)
            info "  NodeSource (Ubuntu/Debian — distro nodejs is usually too old):"
            info "    curl -fsSL https://deb.nodesource.com/setup_${MIN_NODE_MAJOR}.x | bash -"
            info "    apt-get install -y nodejs"
            ;;
        dnf|yum)
            info "  NodeSource (RHEL/CentOS/Fedora — distro nodejs may be too old):"
            info "    curl -fsSL https://rpm.nodesource.com/setup_${MIN_NODE_MAJOR}.x | bash -"
            info "    ${sys_pkg_mgr} install -y nodejs"
            ;;
        apk)
            info "  Alpine 3.21+: apk add --no-cache nodejs npm"
            info "  Older Alpine: install via nvm or upgrade Alpine."
            ;;
        pacman)
            info "  Arch (rolling release, current Node): pacman -S --noconfirm nodejs npm"
            ;;
        *)
            info "  Install Node.js ${MIN_NODE_MAJOR}+ from https://nodejs.org/"
            ;;
    esac
    info "  Cross-platform: nvm — https://github.com/nvm-sh/nvm"
    die "Upgrade Node.js and re-run the installer."
fi

info "Node.js version: ${node_version}"
# === node-version-check-end ===

detect_lockfile_pkg_managers() {
    local found=""

    if [ -f "yarn.lock" ]; then
        found="${found} yarn"
    fi
    if [ -f "pnpm-lock.yaml" ]; then
        found="${found} pnpm"
    fi
    if [ -f "package-lock.json" ]; then
        found="${found} npm"
    fi
    if [ -f "bun.lock" ] || [ -f "bun.lockb" ]; then
        found="${found} bun"
    fi

    printf '%s\n' "${found# }"
}

detect_lockfile_pkg_manager() {
    local found
    found="$(detect_lockfile_pkg_managers)"

    case " ${found} " in
        *" yarn "*) echo "yarn" ;;
        *" pnpm "*) echo "pnpm" ;;
        *" npm "*) echo "npm" ;;
        *" bun "*) echo "bun" ;;
        *) echo "" ;;
    esac
}

count_words() {
    local count=0
    local item
    for item in $1; do
        count=$((count + 1))
    done
    echo "$count"
}

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
    command -v "$1" >/dev/null 2>&1
}

package_manager_lockfile_status() {
    local manager="$1"
    local lockfile_managers="$2"

    case " ${lockfile_managers} " in
        *" ${manager} "*) echo "lockfile found" ;;
        *) echo "no lockfile found" ;;
    esac
}

log_lockfile_detection_warning() {
    local lockfile_managers="$1"
    local count

    [ -n "$lockfile_managers" ] || return 0

    count=$(count_words "$lockfile_managers")
    if [ "$count" -gt 1 ]; then
        warn "Multiple frontend lockfiles detected in ${PANEL_DIR}: ${lockfile_managers}. Using priority order: yarn > pnpm > npm > bun."
    fi
}

# Detect available package manager.
#
# Default preference: bun > pnpm > yarn > npm (bun is fastest when available).
#
# On Alpine Linux, demote bun to last place: bun is not in the apk repo and
# requires a curl-pipe install, which complicates reproducible/minimal Alpine
# containers. pnpm/yarn/npm are all `apk add`-able and just as compatible with
# the Notur + Pterodactyl build pipeline. Set PKG_MANAGER=bun to override.
detect_pkg_manager() {
    local lockfile_pkg_mgr

    if [ -n "${PKG_MANAGER:-}" ]; then
        # User specified via environment variable
        case "$PKG_MANAGER" in
            bun|pnpm|yarn|npm) echo "$PKG_MANAGER"; return ;;
            *) warn "Unknown PKG_MANAGER '$PKG_MANAGER', auto-detecting..." >&2 ;;
        esac
    fi

    lockfile_pkg_mgr=$(detect_lockfile_pkg_manager)
    if [ -n "$lockfile_pkg_mgr" ]; then
        echo "$lockfile_pkg_mgr"
        return
    fi

    if is_alpine; then
        # Alpine: prefer apk-native managers; bun only as last resort.
        if command -v pnpm &> /dev/null; then echo "pnpm"
        elif command -v yarn &> /dev/null; then echo "yarn"
        elif command -v npm &> /dev/null; then echo "npm"
        elif command -v bun &> /dev/null; then echo "bun"
        else echo ""
        fi
        return
    fi

    if command -v bun &> /dev/null; then echo "bun"
    elif command -v pnpm &> /dev/null; then echo "pnpm"
    elif command -v yarn &> /dev/null; then echo "yarn"
    elif command -v npm &> /dev/null; then echo "npm"
    else echo ""
    fi
}

detect_fallback_pkg_manager() {
    local skip="${1:-}"

    if is_alpine; then
        if [ "$skip" != "pnpm" ] && command -v pnpm &> /dev/null; then echo "pnpm"
        elif [ "$skip" != "yarn" ] && command -v yarn &> /dev/null; then echo "yarn"
        elif [ "$skip" != "npm" ] && command -v npm &> /dev/null; then echo "npm"
        elif [ "$skip" != "bun" ] && command -v bun &> /dev/null; then echo "bun"
        else echo ""
        fi
        return
    fi

    if [ "$skip" != "bun" ] && command -v bun &> /dev/null; then echo "bun"
    elif [ "$skip" != "pnpm" ] && command -v pnpm &> /dev/null; then echo "pnpm"
    elif [ "$skip" != "yarn" ] && command -v yarn &> /dev/null; then echo "yarn"
    elif [ "$skip" != "npm" ] && command -v npm &> /dev/null; then echo "npm"
    else echo ""
    fi
}

bootstrap_yarn() {
    local node_pkgs

    if command -v yarn >/dev/null 2>&1; then
        return 0
    fi

    if ! command -v npm >/dev/null 2>&1; then
        node_pkgs=$(get_node_packages)
        [ -n "$node_pkgs" ] || return 1

        info "npm is required to install yarn. Installing Node.js tooling first..."
        install_sys_package "$node_pkgs" || return 1
    fi

    info "Installing yarn to match yarn.lock..."
    npm install -g yarn@1.22.22 || return 1
}

prompt_for_package_manager_selection() {
    local recommended_mgr="$1"
    local lockfile_managers="$2"
    local managers="yarn bun pnpm npm"
    local idx=1
    local manager
    local choice

    while true; do
        idx=1
        if [ -n "$lockfile_managers" ] && [ "$(count_words "$lockfile_managers")" -gt 1 ]; then
            print_prompt_line "${YELLOW}[Notur]${NC} Detected multiple frontend package-manager signals for this panel."
        else
            print_prompt_line "${YELLOW}[Notur]${NC} Detected ${recommended_mgr}.lock-style workflow, but ${recommended_mgr} is not ready to use."
        fi

        print_prompt_line "${YELLOW}[Notur]${NC} Choose how to continue:"

        for manager in $managers; do
            local label status lockfile_status suffix
            label=$(package_manager_display_name "$manager")
            if package_manager_is_installed "$manager"; then
                status="installed"
            else
                status="not installed"
            fi
            lockfile_status=$(package_manager_lockfile_status "$manager" "$lockfile_managers")
            suffix=""
            if [ "$manager" = "$recommended_mgr" ]; then
                suffix=" (Recommended)"
            fi

            print_prompt_line "  ${idx}. ${label} (${status}, ${lockfile_status})${suffix}"
            idx=$((idx + 1))
        done

        print_prompt_line "  5. Cancel installer"
        choice=$(prompt_for_number "Enter your choice [1-5]") || return 1

        case "$choice" in
            1) echo "yarn"; return 0 ;;
            2) echo "bun"; return 0 ;;
            3) echo "pnpm"; return 0 ;;
            4) echo "npm"; return 0 ;;
            5) return 1 ;;
            *) print_prompt_line "${YELLOW}[Notur]${NC} Invalid selection '${choice}'. Please choose a number from 1 to 5." ;;
        esac
    done
}

activate_package_manager_selection() {
    local selected_mgr="$1"
    local recommended_mgr="$2"
    local lockfile_managers="$3"

    if package_manager_is_installed "$selected_mgr"; then
        PKG_MGR="$selected_mgr"
        if [ "$selected_mgr" != "$recommended_mgr" ]; then
            warn "Proceeding with ${selected_mgr} even though ${recommended_mgr} is recommended from the detected lockfile state. This may ignore the panel's lockfile and produce different dependency versions."
        fi
        return 0
    fi

    if [ "$selected_mgr" = "yarn" ]; then
        bootstrap_yarn || die "Failed to install yarn automatically."
        PKG_MGR="yarn"
        return 0
    fi

    warn "$(package_manager_display_name "$selected_mgr") is not installed and this installer only supports automatic bootstrap for Yarn right now."
    if [ -n "$lockfile_managers" ]; then
        warn "Detected lockfile managers: ${lockfile_managers}"
    fi
    return 1
}

ensure_selected_pkg_manager() {
    local lockfile_pkg_mgr="$1"
    local lockfile_managers="$2"
    local selected_mgr
    local multiple_lockfiles=0

    if [ -n "$lockfile_managers" ] && [ "$(count_words "$lockfile_managers")" -gt 1 ]; then
        multiple_lockfiles=1
    fi

    if package_manager_is_installed "$PKG_MGR" && [ "$multiple_lockfiles" -eq 0 ]; then
        return 0
    fi

    if is_interactive_shell && { [ "$multiple_lockfiles" -eq 1 ] || ! package_manager_is_installed "$PKG_MGR"; }; then
        while true; do
            selected_mgr=$(prompt_for_package_manager_selection "$lockfile_pkg_mgr" "$lockfile_managers") || die "Package manager selection cancelled."
            activate_package_manager_selection "$selected_mgr" "$lockfile_pkg_mgr" "$lockfile_managers" && return 0
        done
    fi

    if package_manager_is_installed "$PKG_MGR"; then
        return 0
    fi

    if [ "$PKG_MGR" = "yarn" ]; then
        bootstrap_yarn || die "Could not bootstrap Yarn. Install the panel's package manager and retry."
        return 0
    fi

    die "Selected package manager '${PKG_MGR}' is not installed."
}

cd "${PANEL_DIR}"
PANEL_DIR="$(pwd -P)"
LOCKFILE_PKG_MANAGERS=$(detect_lockfile_pkg_managers)
LOCKFILE_PKG_MGR=$(detect_lockfile_pkg_manager)
PKG_MGR=$(detect_pkg_manager)
if [ -z "$PKG_MGR" ]; then
    die "No package manager found. Install one of: bun, pnpm, yarn, or npm"
fi

log_lockfile_detection_warning "$LOCKFILE_PKG_MANAGERS"
ensure_selected_pkg_manager "$LOCKFILE_PKG_MGR" "$LOCKFILE_PKG_MANAGERS"
info "Using package manager: ${PKG_MGR}"

# Package manager command helpers
pkg_install() {
    # Build tools are devDependencies, including in production containers.
    local NODE_ENV=development
    local npm_config_production=false
    local npm_config_omit=""
    export NODE_ENV npm_config_production npm_config_omit
    case "$PKG_MGR" in
        bun)
            # Prefer lockfile reproducibility when available.
            if [ -f "bun.lockb" ] || [ -f "bun.lock" ]; then
                bun install --frozen-lockfile
            else
                bun install
            fi
            ;;
        pnpm)
            if [ -f "pnpm-lock.yaml" ]; then
                pnpm install --frozen-lockfile
            else
                pnpm install
            fi
            ;;
        yarn)
            if [ -f "yarn.lock" ]; then
                yarn install --frozen-lockfile
            else
                yarn install
            fi
            ;;
        npm)
            # Use npm ci when lockfile is present to avoid version drift.
            if [ -f "package-lock.json" ]; then
                npm ci
            else
                npm install
            fi
            ;;
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
    if [ -x "node_modules/.bin/${1##*/}" ]; then
        local executable="$1"
        shift
        "./node_modules/.bin/${executable##*/}" "$@"
        return
    fi
    case "$PKG_MGR" in
        bun)  bunx "$@" ;;
        pnpm) pnpm dlx "$@" ;;
        yarn) yarn exec "$@" ;;
        npm)  npx "$@" ;;
    esac
}

resolve_pkg_install_fallback() {
    local current_mgr="$1"

    case "$current_mgr" in
        yarn)
            if [ -f "package-lock.json" ] && command -v npm >/dev/null 2>&1; then
                echo "npm"
                return
            fi
            if [ -f "pnpm-lock.yaml" ] && command -v pnpm >/dev/null 2>&1; then
                echo "pnpm"
                return
            fi
            if [ -f "bun.lock" ] || [ -f "bun.lockb" ]; then
                if command -v bun >/dev/null 2>&1; then
                    echo "bun"
                    return
                fi
            fi
            ;;
        pnpm)
            if [ -f "package-lock.json" ] && command -v npm >/dev/null 2>&1; then
                echo "npm"
                return
            fi
            if [ -f "yarn.lock" ] && command -v yarn >/dev/null 2>&1; then
                echo "yarn"
                return
            fi
            ;;
        bun)
            if [ -f "package-lock.json" ] && command -v npm >/dev/null 2>&1; then
                echo "npm"
                return
            fi
            if [ -f "yarn.lock" ] && command -v yarn >/dev/null 2>&1; then
                echo "yarn"
                return
            fi
            ;;
    esac

    echo ""
}

run_tailwind_cli() {
    # Use the release-local CLI, never download an unrelated latest Tailwind.
    [ -x node_modules/.bin/tailwindcss ] || return 1
    node_modules/.bin/tailwindcss -i resources/tailwind/notur.css -o bridge/dist/tailwind.css
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

# Map to patch directory for verified panel branches.
# Accept both "1.12.0" and "v1.12.0" forms — Composer may surface either
# depending on whether the version was sourced from composer.json or a git tag.
case "$PANEL_VERSION" in
    1.12.*|v1.12.*)
        PATCH_VERSION="v1.12"
        ;;
    1.15.0|v1.15.0|1.15.1|v1.15.1)
        PATCH_VERSION="v1.15"
        ;;
    1.11.*|v1.11.*)
        die "Pterodactyl v1.11.x is no longer supported by Notur. Please upgrade to v1.12.x or v1.15.0–v1.15.1."
        ;;
    "")
        die "Could not detect Pterodactyl panel version. Notur requires v1.12.x or v1.15.0–v1.15.1."
        ;;
    *)
        die "Unsupported Pterodactyl version: ${PANEL_VERSION}. Notur supports v1.12.x and v1.15.0–v1.15.1."
        ;;
esac

info "Using patch set: ${PATCH_VERSION}"

# Snapshot files before Composer, patches or builds modify the installation.
create_install_backup() {
    local backup_root="${PANEL_DIR}/storage/notur/backups"
    local entry
    local entries=()
    mkdir -p "$backup_root" || return 1
    BACKUP_DIR=$(mktemp -d "$backup_root/install-XXXXXXXX") || return 1
    chmod 700 "$BACKUP_DIR" || return 1
    for entry in composer.json composer.lock package.json package-lock.json yarn.lock pnpm-lock.yaml bun.lock bun.lockb resources public/assets public/notur notur config/notur.php; do
        [ ! -e "${PANEL_DIR}/$entry" ] || entries+=("$entry")
    done
    tar -czf "$BACKUP_DIR/files.tar.gz" -C "$PANEL_DIR" "${entries[@]}" || return 1
    info "File backup: $BACKUP_DIR/files.tar.gz (database backup must be managed separately)."
}

create_install_backup || die "Could not create the pre-install file backup. No panel changes made."
if [ -f "${PANEL_DIR}/vendor/notur/notur/composer.json" ]; then
    info "Existing Notur installation detected; upgrading/reconciling files and preserving extension state."
fi
trap 'finalize_permissions "$?"' EXIT
trap 'error "Installation interrupted. File backup retained at ${BACKUP_DIR}; database changes are not automatically rolled back."' ERR

# ── Step 1: Install Composer package ─────────────────────────────────────

step "1/6" "Installing notur/notur via Composer..."
cd "${PANEL_DIR}"
ensure_git_safe_directory "${PANEL_DIR}"
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
            if patch --batch --forward --dry-run -p1 < "${patch}" &>/dev/null; then
                patch --batch --forward -p1 < "${patch}" || die "Failed to apply: ${PATCH_NAME}. Backup: ${BACKUP_DIR}"
            elif patch --batch --reverse --dry-run -p1 < "${patch}" &>/dev/null; then
                info "  Patch already applied: ${PATCH_NAME}"
            else
                die "Patch conflicts with panel source: ${PATCH_NAME}. Backup: ${BACKUP_DIR}"
            fi
        fi
    done
    ok "React patches applied."
else
    die "Required patch directory not found: ${PATCH_DIR}"
fi

# ── Step 4: Rebuild frontend ─────────────────────────────────────────────

step "4/6" "Rebuilding frontend assets..."
cd "${PANEL_DIR}"

# Enable legacy OpenSSL provider for Node.js 17+ compatibility with older webpack configs
export NODE_OPTIONS="${NODE_OPTIONS:-} --openssl-legacy-provider"

# Fix webpack-cli version incompatibility if detected.
# Pterodactyl ships webpack-cli 4.x but some setups end up with 5.x+
# which breaks with "Cannot read properties of undefined (reading 'getArguments')".
fix_webpack_cli_compat() {
    if [ ! -f "${PANEL_DIR}/node_modules/.bin/webpack" ]; then
        return
    fi

    # Test if webpack-cli works
    if "${PANEL_DIR}/node_modules/.bin/webpack" --version &> /dev/null; then
        return
    fi

    warn "webpack-cli appears broken. Attempting to fix by pinning webpack-cli@4..."
    case "$PKG_MGR" in
        yarn) yarn add --dev webpack-cli@4 2>/dev/null ;;
        npm)  npm install --save-dev webpack-cli@4 --legacy-peer-deps 2>/dev/null ;;
        pnpm) pnpm add -D webpack-cli@4 2>/dev/null ;;
        bun)  bun add -d webpack-cli@4 2>/dev/null ;;
    esac
}

# Install panel frontend dependencies. Keep dependency recovery separate from
# the build step so install failures are not masked as script/tooling failures.
install_frontend_dependencies() {
    if pkg_install; then
        return 0
    fi

    if [ "$PKG_MGR" = "yarn" ]; then
        local fallback_mgr
        fallback_mgr=$(resolve_pkg_install_fallback "yarn")
        if [ -n "$fallback_mgr" ]; then
            warn "Yarn dependency install failed. Falling back to ${fallback_mgr} based on the other detected lockfile state."
            PKG_MGR="$fallback_mgr"
            pkg_install && return 0
        fi
    fi

    if [ "$PKG_MGR" = "npm" ]; then
        warn "Standard npm install failed. Retrying with --legacy-peer-deps..."
        if [ -f "package-lock.json" ]; then
            npm ci --include=dev --legacy-peer-deps && return 0
        else
            npm install --include=dev --legacy-peer-deps && return 0
        fi
    fi

    return 1
}

build_frontend() {
    install_frontend_dependencies || return 1
    fix_webpack_cli_compat || return 1

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

    pkg_run build:production
}

build_frontend || die "Frontend build failed."

ok "Frontend rebuilt."

# ── Step 5: Create directories and copy bridge ───────────────────────────

step "5/6" "Setting up Notur directories..."

mkdir -p "${PANEL_DIR}/notur/extensions"
mkdir -p "${PANEL_DIR}/public/notur/extensions"
mkdir -p "${PANEL_DIR}/storage/notur"

NOTUR_DIR="${PANEL_DIR}/vendor/notur/notur"

# Resolve dependencies from the Notur package, independently of the panel.
build_notur_assets() (
    cd "${NOTUR_DIR}" || exit 1
    PKG_MGR=$(detect_pkg_manager)
    [ -n "$PKG_MGR" ] && package_manager_is_installed "$PKG_MGR" || exit 1
    # The installed release is the source of truth for its Node engine range.
    php -r '
        require $argv[1] . "/vendor/autoload.php";
        $package = json_decode(file_get_contents("package.json"), true, 512, JSON_THROW_ON_ERROR);
        $range = $package["engines"]["node"] ?? "*";
        if (!\Composer\Semver\Semver::satisfies($argv[2], $range)) {
            fwrite(STDERR, "Notur assets require Node " . $range . "; found " . $argv[2] . PHP_EOL);
            exit(1);
        }
    ' "$PANEL_DIR" "$node_version" || exit 1
    install_frontend_dependencies || exit 1
    if [ ! -s bridge/dist/bridge.js ]; then
        pkg_run build:bridge || exit 1
    fi
    if [ "$TAILWIND_REQUIRED" -eq 1 ] && [ ! -s bridge/dist/tailwind.css ]; then
        if has_pkg_script package.json build:tailwind; then
            pkg_run build:tailwind || exit 1
        else
            run_tailwind_cli || exit 1
        fi
    fi
)

BRIDGE_JS="${NOTUR_DIR}/bridge/dist/bridge.js"
TAILWIND_CSS="${NOTUR_DIR}/bridge/dist/tailwind.css"
NOTUR_SCRIPTS_BLADE="${NOTUR_DIR}/resources/views/scripts.blade.php"
TAILWIND_REQUIRED=0
if [ -f "${NOTUR_SCRIPTS_BLADE}" ] && grep -q "/notur/tailwind.css" "${NOTUR_SCRIPTS_BLADE}"; then
    TAILWIND_REQUIRED=1
fi
if [ ! -s "$BRIDGE_JS" ] || { [ "$TAILWIND_REQUIRED" -eq 1 ] && [ ! -s "$TAILWIND_CSS" ]; }; then
    build_notur_assets || die "Could not build required Notur runtime assets."
fi
[ -s "$BRIDGE_JS" ] || die "Bridge runtime is missing or empty."
cp "$BRIDGE_JS" "${PANEL_DIR}/public/notur/bridge.js"
if [ "$TAILWIND_REQUIRED" -eq 1 ]; then
    [ -s "$TAILWIND_CSS" ] || die "Required Tailwind CSS is missing or empty."
    cp "$TAILWIND_CSS" "${PANEL_DIR}/public/notur/tailwind.css"
fi
ok "Notur runtime assets installed."

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
    # Never mark a migration complete merely because an unrelated table exists.
    php artisan migrate --force
}

run_migrations || die "Migration failed."
ok "Migrations complete."

# ── Store checksums ──────────────────────────────────────────────────────

info "Storing file checksums..."
CHECKSUM_FILE="${PANEL_DIR}/notur/.checksums"

# Verify previously stored checksums before overwriting.
if [ -f "${CHECKSUM_FILE}" ] && [ -f "${TARGET_BLADE}" ]; then
    PREV_LAYOUT_HASH=$(grep '^layout:' "${CHECKSUM_FILE}" 2>/dev/null | awk '{print $2}' || true)
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
    if [ -f "${TARGET_BLADE}" ]; then
        if command -v sha256sum &>/dev/null; then
            echo "layout: $(sha256sum "${TARGET_BLADE}" | cut -d' ' -f1)"
        elif command -v shasum &>/dev/null; then
            echo "layout: $(shasum -a 256 "${TARGET_BLADE}" | cut -d' ' -f1)"
        fi
    fi
} > "${CHECKSUM_FILE}"

php artisan optimize:clear || die "Could not clear Laravel caches."

# Final ownership pass must follow every root-run Artisan command.
finalize_permissions 0 || die "Could not finalize Notur permissions."
trap - EXIT

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
    info "  Install an extension:  docker exec -it <container> php artisan notur:add vendor/name"
    info "  List extensions:       docker exec -it <container> php artisan notur:list"
else
    info "  Install an extension:  php artisan notur:add vendor/name"
    info "  List extensions:       php artisan notur:list"
fi
info "  Manage extensions:     Browse to /admin/notur/extensions"
echo ""

# Docker-specific final notes
if is_docker_env; then
    warn "IMPORTANT: For Docker installations:"
    warn "  1. Add volume mounts to persist Notur data across container restarts:"
    warn "       - 'notur-data:${PANEL_DIR}/notur'"
    warn "       - 'notur-public:${PANEL_DIR}/public/notur'"
    warn "     Or use bind mounts: './notur:${PANEL_DIR}/notur' (host path on left side)"
    warn "  2. If using Coolify or similar, configure persistent storage for these paths."
    warn "  3. Persist ${PANEL_DIR}/storage/notur for backups. Back up the database separately."
    warn "  4. Container changes to vendor, patched sources and built assets do NOT survive image replacement."
    warn "     Bake these into your custom image or rerun installation before serving traffic after every redeploy."
    echo ""
fi
