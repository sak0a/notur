#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
INSTALL_SH="$SCRIPT_DIR/../install.sh"
WORKDIR=$(mktemp -d)
trap 'rm -rf "$WORKDIR"' EXIT
extract_func() { sed -n "/^${1}() {/,/^}/p" "$INSTALL_SH"; }
eval "$(extract_func create_install_backup)"
eval "$(extract_func pkg_install)"
eval "$(extract_func is_interactive_shell)"
info() { :; }
PANEL_DIR="$WORKDIR/panel with spaces"
mkdir -p "$PANEL_DIR/resources" "$PANEL_DIR/notur" "$PANEL_DIR/public/assets"
printf 'original' > "$PANEL_DIR/composer.json"
printf 'extension state' > "$PANEL_DIR/notur/extensions.json"
create_install_backup
FIRST_BACKUP="$BACKUP_DIR"
printf 'changed' > "$PANEL_DIR/composer.json"
create_install_backup
[ "$FIRST_BACKUP" != "$BACKUP_DIR" ]
mkdir "$WORKDIR/restored"
tar -xzf "$FIRST_BACKUP/files.tar.gz" -C "$WORKDIR/restored"
[ "$(cat "$WORKDIR/restored/composer.json")" = original ]
[ "$(cat "$WORKDIR/restored/notur/extensions.json")" = 'extension state' ]
! tar -tzf "$BACKUP_DIR/files.tar.gz" | grep -q storage/notur/backups
printf 'PASS: independent retained snapshots preserve originals and extension state\n'

npm() {
    [ "$NODE_ENV" = development ]
    [ "$npm_config_production" = false ]
    [ -z "$npm_config_omit" ]
    printf '%s\n' "$*" > "$WORKDIR/install-command"
}
cd "$PANEL_DIR"
PKG_MGR=npm
export NODE_ENV=production npm_config_production=true npm_config_omit=dev
: > package-lock.json
pkg_install
[ "$(cat "$WORKDIR/install-command")" = ci ]
[ "$NODE_ENV" = production ]
printf 'PASS: production builds include dev dependencies without changing caller environment\n'

# The same patch logic used by the installer must not undo an existing patch.
sed -n '/^if \[ -d "${PATCH_DIR}" \]; then/,/^# ── Step 4/p' "$INSTALL_SH" > "$WORKDIR/apply.sh"
PATCH_DIR="$WORKDIR/patches"
mkdir "$PATCH_DIR"
printf 'before\n' > source.txt
cat > "$PATCH_DIR/source.patch" <<'PATCH'
--- a/source.txt
+++ b/source.txt
@@ -1 +1 @@
-before
+after
PATCH
warn() { :; }
ok() { :; }
die() { echo "$*" >&2; exit 1; }
source "$WORKDIR/apply.sh"
source "$WORKDIR/apply.sh"
[ "$(cat source.txt)" = after ]
printf 'conflicting edit\n' > source.txt
if (source "$WORKDIR/apply.sh") >/dev/null 2>&1; then
    echo 'FAIL: conflicting patch was accepted' >&2
    exit 1
fi
[ "$(cat source.txt)" = 'conflicting edit' ]
printf 'PASS: patches are idempotent and conflicts fail without changing source\n'
