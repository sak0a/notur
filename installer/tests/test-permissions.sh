#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
INSTALL_SH="$SCRIPT_DIR/../install.sh"
WORKDIR=$(mktemp -d)
trap 'rm -rf "$WORKDIR"' EXIT
extract_func() { sed -n "/^${1}() {/,/^}/p" "$INSTALL_SH"; }
eval "$(extract_func fix_permissions)"
eval "$(extract_func finalize_permissions)"
error() { echo "$*" >&2; }
PANEL_DIR="$WORKDIR/panel"
mkdir -p "$PANEL_DIR/notur" "$PANEL_DIR/public/notur" "$PANEL_DIR/storage/notur"
WEB_USER=www-data

# An ownership failure must turn successful installation into failure and
# must not hide the original status when installation already failed.
(
    fix_permissions() { return 1; }
    if finalize_permissions 0; then exit 1; fi
    code=0
    finalize_permissions 17 || code=$?
    [ "$code" -eq 17 ]
) 2>/dev/null
printf 'PASS: ownership errors are reported and original failures preserved\n'

if [ "$(id -u)" != 0 ] || ! id www-data >/dev/null 2>&1; then
    printf 'SKIP: ownership integration requires root and www-data (run in E2E image)\n'
    exit 0
fi
chmod 755 "$WORKDIR"
for expected in 0 17; do
    code=0
    (
        trap 'finalize_permissions "$?"' EXIT
        fix_permissions "$PANEL_DIR/notur"
        # Model the late root Artisan bootstrap that caused the production bug.
        touch "$PANEL_DIR/notur/late-$expected.lock"
        chmod 600 "$PANEL_DIR/notur/late-$expected.lock"
        exit "$expected"
    ) || code=$?
    [ "$code" -eq "$expected" ]
    [ "$(stat -c %U "$PANEL_DIR/notur/late-$expected.lock")" = www-data ]
    runuser -u www-data -- php -r '$f = fopen($argv[1], "c+"); if (!$f || !flock($f, LOCK_EX | LOCK_NB)) exit(1); fclose($f);' "$PANEL_DIR/notur/late-$expected.lock"
done
printf 'PASS: late root-created locks writable by PHP user after success and failure\n'
