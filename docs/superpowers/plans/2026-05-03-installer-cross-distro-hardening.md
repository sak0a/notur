# Installer Cross-Distro Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `installer/install.sh` fail fast on too-old Node.js (Pterodactyl v1.12 requires Node ≥ 22) and auto-bootstrap missing build tools on apt/dnf/yum/pacman systems — not just Alpine.

**Architecture:** Two surgical changes to `installer/install.sh`. (1) Add a Node major-version check after the existing `command -v node` check that dies with a clear NodeSource hint when Node < 22. (2) Rename `install_alpine_requirements()` → `install_distro_requirements()` and keep a `logical-dep → distro-package` map keyed on `detect_sys_pkg_manager()`'s result. Drop the `is_alpine` guard at the call site. Add comment markers around the new Node block so the test can extract it cleanly.

**Tech Stack:** Bash 4+ (POSIX-style `[ ]` tests, not `[[ ]]`). Existing helpers: `info`, `warn`, `error`, `die`, `detect_sys_pkg_manager`, `install_sys_package`, `print_node_install_hint`. Test pattern: `bash <<'INNER'` heredoc with env-var inputs (mirrors `test-pkg-manager-detection.sh`).

---

## File Structure

**Modified:**
- `installer/install.sh` — add Node version check (~30 lines), rename + generalize `install_alpine_requirements` (~80 lines), update one call site (1 line)
- `CHANGELOG.md` — add entries to `[Unreleased]` Added + Changed

**Created:**
- `installer/tests/test-node-version-check.sh` — 7 cases, heredoc-style stubs

**Out of scope (do not touch):**
- `.github/workflows/ci.yml` — wiring tests into CI is a follow-up
- `print_node_install_hint`, `get_node_packages` — leave alone unless strictly required
- `detect_pkg_manager` — landed in PR #34, do not modify
- Anything outside `installer/` and `CHANGELOG.md`

---

## Task 1: Branch from origin/master

**Files:** none

- [ ] **Step 1: Create branch from origin/master**

```bash
git checkout -B feat/installer-cross-distro-hardening origin/master
```

Expected: Switched to a new branch tracking origin/master.

- [ ] **Step 2: Verify clean tree (untracked files OK, no staged/modified)**

```bash
git status --short | grep -vE '^\?\?' || echo "clean"
```

Expected: `clean`

---

## Task 2: Add MIN_NODE_MAJOR constant and Node version-check block

**Files:**
- Modify: `installer/install.sh:25` (add constant after `NOTUR_VERSION="1.3.2"`)
- Modify: `installer/install.sh:437-458` (insert version check after the existing `command -v node` block)

- [ ] **Step 1: Add MIN_NODE_MAJOR constant**

After the existing `NOTUR_VERSION="1.3.2"` line, on a new line, insert:

```bash
NOTUR_VERSION="1.3.2"
MIN_NODE_MAJOR="${MIN_NODE_MAJOR:-22}"
```

The `${MIN_NODE_MAJOR:-22}` form lets tests override it via env var — mandatory because case 7 of the test asserts the override is honored.

- [ ] **Step 2: Add the version check block immediately after the existing Node presence/auto-install block**

The existing block is the `if ! command -v node &> /dev/null; then ... fi` at lines 438-458. After its closing `fi` (line 458), insert the new block bracketed by markers (the test extracts between markers):

```bash
# === node-version-check-start ===
# Verify Node.js meets the minimum major version (Pterodactyl v1.12 requires Node 22+).
# Read raw output, strip leading "v", split major. Use POSIX [ ] tests, not [[ ]].
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
    print_node_install_hint
    info "On Ubuntu/Debian, install Node ${MIN_NODE_MAJOR} from NodeSource:"
    info "  curl -fsSL https://deb.nodesource.com/setup_${MIN_NODE_MAJOR}.x | bash -"
    info "  apt-get install -y nodejs"
    die "Upgrade Node.js and re-run the installer."
fi

info "Node.js version: ${node_version}"
# === node-version-check-end ===
```

Critical:
- Markers MUST be on their own lines as written. The test does `sed -n '/=== node-version-check-start ===/,/=== node-version-check-end ===/p'`.
- Use `[ ]` not `[[ ]]` (matches script style — surrounding code uses POSIX form).
- `print_node_install_hint` is called BEFORE the NodeSource hint to keep parity with existing failure paths.

- [ ] **Step 3: Bash-syntax check the modified file**

```bash
bash -n installer/install.sh
```

Expected: no output, exit 0.

- [ ] **Step 4: Smoke-source the script and confirm constants/functions parse**

```bash
bash -c 'source installer/install.sh /nonexistent 2>/dev/null; true'
echo $?
```

Expected: exits non-zero (script dies on missing panel dir, that's fine), but no syntax errors during parsing. If `bash -n` passed in step 3, this is just a sanity check.

- [ ] **Step 5: Commit-staging only (do not commit yet — single-commit constraint)**

Skip — we're making one combined commit at the end of all tasks.

---

## Task 3: Rename + generalize `install_alpine_requirements` → `install_distro_requirements`

**Files:**
- Modify: `installer/install.sh:289-356` (function definition)
- Modify: `installer/install.sh:393-398` (call site)

- [ ] **Step 1: Replace the entire function body**

Find the existing `install_alpine_requirements()` function (currently `installer/install.sh:289-356`) and replace the WHOLE function (from `# Helper: Install Alpine-specific requirements for Notur` through the closing `}`) with:

```bash
# Helper: Install distro-specific build requirements for Notur.
#
# Logical dependencies (bash, git, patch, make, perl, python3, plus
# Alpine-only coreutils & libstdc++) map to per-distro package names via
# the table below. Skip a logical dep if its `command -v` check passes.
#
#   Logical    | apk         | apt              | dnf/yum          | pacman
#   -----------+-------------+------------------+------------------+----------
#   bash       | bash        | bash             | bash             | bash
#   git        | git         | git              | git              | git
#   patch      | patch       | patch            | patch            | patch
#   make       | build-base  | build-essential  | make gcc-c++     | base-devel
#   perl       | perl        | perl             | perl             | perl
#   python3    | python3     | python3          | python3          | python
#   coreutils  | coreutils   | (built-in)       | (built-in)       | (built-in)
#   libstdc++  | libstdc++   | (built-in)       | (built-in)       | (built-in)
#
# coreutils + libstdc++ rows are Alpine-only (musl + busybox quirks).
install_distro_requirements() {
    local sys_pkg_mgr
    sys_pkg_mgr=$(detect_sys_pkg_manager)

    if [ -z "$sys_pkg_mgr" ]; then
        warn "No supported system package manager detected. Skipping requirements bootstrap."
        return 0
    fi

    info "Detected ${sys_pkg_mgr}. Checking required packages..."

    local required_packages=""

    # Resolve the package name for a logical dependency on the current pkg mgr.
    # Echoes the package name, or empty string if the dep is built-in / N/A.
    pkg_name_for() {
        local logical="$1"
        case "$logical:$sys_pkg_mgr" in
            bash:*)            echo "bash" ;;
            git:*)             echo "git" ;;
            patch:*)           echo "patch" ;;
            perl:*)            echo "perl" ;;

            make:apk)          echo "build-base" ;;
            make:apt)          echo "build-essential" ;;
            make:dnf|make:yum) echo "make gcc-c++" ;;
            make:pacman)       echo "base-devel" ;;

            python3:apk|python3:apt|python3:dnf|python3:yum) echo "python3" ;;
            python3:pacman)    echo "python" ;;

            coreutils:apk)     echo "coreutils" ;;
            coreutils:*)       echo "" ;;

            libstdc++:apk)     echo "libstdc++" ;;
            libstdc++:*)       echo "" ;;

            *)                 echo "" ;;
        esac
    }

    add_if_missing() {
        local logical="$1" probe_cmd="$2" pkg
        if eval "$probe_cmd" >/dev/null 2>&1; then
            return 0
        fi
        pkg=$(pkg_name_for "$logical")
        if [ -n "$pkg" ]; then
            required_packages="$required_packages $pkg"
        fi
    }

    add_if_missing bash    "command -v bash"
    add_if_missing git     "command -v git"
    add_if_missing patch   "command -v patch"
    add_if_missing make    "command -v make"
    add_if_missing perl    "command -v perl"
    add_if_missing python3 "command -v python3"
    add_if_missing coreutils "command -v realpath"

    # libstdc++ has no command — probe via apk's package db (Alpine only).
    if [ "$sys_pkg_mgr" = "apk" ]; then
        if ! apk info -e libstdc++ >/dev/null 2>&1; then
            required_packages="$required_packages libstdc++"
        fi
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
```

Notes:
- `pkg_name_for` and `add_if_missing` are local helper functions defined inside `install_distro_requirements`. Keeping them nested keeps the global namespace clean and makes the function self-contained.
- The case glob `make:dnf|make:yum` works because `case` supports `|` alternation — POSIX-compliant.
- For pacman, `base-devel` is a metapackage group; `pacman -S --noconfirm base-devel` will install all of it (verbose but correct). If we wanted minimal, we'd say `make gcc`, but `base-devel` matches the Alpine pattern (`build-base` is also a metapackage).
- `coreutils` probes via `realpath` — same as the original Alpine code.
- libstdc++ probe stays Alpine-only — `apk info -e` is apk-specific syntax.

- [ ] **Step 2: Update the call site (line ~396)**

Find the existing call site:

```bash
# Install Alpine requirements first (before other checks)
# Use || true to continue even if package installation fails - the script will
# fail later at a more specific point if required tools are missing
if is_alpine; then
    install_alpine_requirements || true
fi
```

Replace with:

```bash
# Install distro-specific requirements first (before other checks).
# Use || true to continue even if package installation fails — the script
# will fail later at a more specific point if required tools are missing.
install_distro_requirements || true
```

The Alpine guard is dropped because `install_distro_requirements` itself returns cleanly on unknown package managers (with a warn, not a die).

- [ ] **Step 3: Bash-syntax check**

```bash
bash -n installer/install.sh
```

Expected: no output, exit 0.

- [ ] **Step 4: Confirm function exists when sourced**

```bash
bash -c '
    set +e
    # Source the script in a way that gets past parsing without running pre-flight.
    # Easiest: just verify the function name appears in the file with a definition.
    grep -E "^install_distro_requirements\(\)" installer/install.sh
'
```

Expected: prints `install_distro_requirements() {`.

Also confirm the old name is gone:

```bash
grep -E "^install_alpine_requirements\(\)" installer/install.sh && echo "FAIL: old fn still present" || echo "OK: old fn removed"
```

Expected: `OK: old fn removed`.

---

## Task 4: Write `installer/tests/test-node-version-check.sh`

**Files:**
- Create: `installer/tests/test-node-version-check.sh`

- [ ] **Step 1: Write the test file**

```bash
#!/usr/bin/env bash
# test-node-version-check.sh — verify the Node.js version check block in
# install.sh accepts modern Node, rejects too-old Node with a NodeSource
# hint, handles parse failures, and honors MIN_NODE_MAJOR override.
#
# Strategy: extract the block between # === node-version-check-{start,end} ===
# markers, stub `node`, `print_node_install_hint`, info/warn/error/die so the
# block runs in isolation. Inputs (T_NODE_OUTPUT, T_NODE_RC, T_MIN_MAJOR) are
# passed via env vars and consumed inside a single-quoted heredoc — no
# outer-vs-inner quoting dance.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
INSTALL_SH="$SCRIPT_DIR/../install.sh"

if [ ! -f "$INSTALL_SH" ]; then
    echo "FAIL: install.sh not found at $INSTALL_SH"
    exit 1
fi

pass=0
fail=0

# Run the version-check block in a controlled subshell.
#   T_NODE_OUTPUT — what `node --version` should print (empty = node fails to exec)
#   T_NODE_RC     — exit code `node --version` should return (default 0)
#   T_MIN_MAJOR   — value for MIN_NODE_MAJOR (default 22)
#
# Captures combined stdout/stderr and the exit code. Exit 0 = check passed,
# nonzero = check failed (which is correct behavior on too-old / unparseable Node).
run_check() {
    local node_output="$1"
    local node_rc="${2:-0}"
    local min_major="${3:-22}"

    INSTALL_SH="$INSTALL_SH" \
    T_NODE_OUTPUT="$node_output" \
    T_NODE_RC="$node_rc" \
    T_MIN_MAJOR="$min_major" \
    bash <<'INNER' 2>&1
        set +e

        # Extract the marked block from install.sh.
        block=$(sed -n '/=== node-version-check-start ===/,/=== node-version-check-end ===/p' "$INSTALL_SH")
        if [ -z "$block" ]; then
            echo "ERR: could not extract node-version-check block from install.sh"
            exit 99
        fi

        # Stubs: replace shell builtins / install.sh helpers with quiet versions
        # that still echo so the assertions can grep their output.
        info()  { echo "INFO: $*"; }
        warn()  { echo "WARN: $*"; }
        error() { echo "ERROR: $*"; }
        die()   { echo "DIE: $*"; exit 1; }
        print_node_install_hint() { echo "HINT: print_node_install_hint called"; }

        # Stub `node` so `node --version` returns T_NODE_OUTPUT and T_NODE_RC.
        node() {
            if [ "${1:-}" = "--version" ]; then
                if [ -n "$T_NODE_OUTPUT" ]; then
                    printf '%s\n' "$T_NODE_OUTPUT"
                fi
                return "$T_NODE_RC"
            fi
            return 0
        }

        MIN_NODE_MAJOR="$T_MIN_MAJOR"

        eval "$block"
        echo "EXIT: 0"
INNER
}

# Assert: command succeeded (exit 0 path) and output contains expected substring.
assert_pass() {
    local label="$1" node_output="$2" expect_substr="$3"
    local out
    out=$(run_check "$node_output" 0 "${4:-22}")
    if echo "$out" | grep -q "EXIT: 0" && echo "$out" | grep -qF "$expect_substr"; then
        echo "  PASS: $label"
        pass=$((pass+1))
    else
        echo "  FAIL: $label — expected EXIT: 0 + '$expect_substr', got:"
        echo "$out" | sed 's/^/      /'
        fail=$((fail+1))
    fi
}

# Assert: command died (no EXIT: 0 line) and output contains expected substring.
assert_fail() {
    local label="$1" node_output="$2" node_rc="$3" expect_substr="$4"
    local out
    out=$(run_check "$node_output" "$node_rc" "${5:-22}")
    if ! echo "$out" | grep -q "EXIT: 0" && echo "$out" | grep -qF "$expect_substr"; then
        echo "  PASS: $label"
        pass=$((pass+1))
    else
        echo "  FAIL: $label — expected die + '$expect_substr', got:"
        echo "$out" | sed 's/^/      /'
        fail=$((fail+1))
    fi
}

echo "=== Acceptable Node versions ==="
assert_pass "Node 22.0.0 passes"   "v22.0.0"  "Node.js version: 22.0.0"
assert_pass "Node 23.5.0 passes"   "v23.5.0"  "Node.js version: 23.5.0"
assert_pass "Node 22.11.0 passes"  "v22.11.0" "Node.js version: 22.11.0"

echo ""
echo "=== Too-old Node versions are rejected with NodeSource hint ==="
assert_fail "Node 18.19.0 rejected"  "v18.19.0" 0 "too old"
assert_fail "Node 18 NodeSource hint" "v18.19.0" 0 "deb.nodesource.com/setup_22.x"
assert_fail "Node 12.22.0 rejected"  "v12.22.0" 0 "too old"
assert_fail "Node 0.10.48 rejected"  "v0.10.48" 0 "too old"

echo ""
echo "=== Unparseable / failed `node --version` ==="
assert_fail "node --version returns empty" ""    0 "Could not parse"
assert_fail "node --version returns 'lol'" "lol" 0 "Could not parse"
assert_fail "node --version exits nonzero" ""    1 "Failed to read Node.js version"

echo ""
echo "=== MIN_NODE_MAJOR override is honored ==="
assert_pass "MIN_NODE_MAJOR=18 + Node 18 passes"   "v18.19.0" "Node.js version: 18.19.0" 18
assert_fail "MIN_NODE_MAJOR=18 + Node 16 rejected" "v16.20.0" 0 "too old" 18

echo ""
echo "Results: $pass passed, $fail failed"
[ "$fail" -eq 0 ] || exit 1
```

Critical implementation notes:
- `set +e` inside the heredoc is intentional — the block uses `die` (which is `exit 1`) on failure, and we want to capture that, not bail out of the test harness on the first failure inside the subshell.
- `bash <<'INNER' 2>&1` — single-quoted `INNER` so `$T_*` expansions happen in the inner shell, not the outer one. Mirrors the pattern from `test-pkg-manager-detection.sh`.
- The `node` stub uses `printf '%s\n'` instead of `echo` so we can distinguish empty output from a no-arg `echo` (which prints a newline).
- `assert_pass` accepts an optional 4th arg for `T_MIN_MAJOR`; `assert_fail` accepts an optional 5th. Default is 22.
- We assert on the literal string `deb.nodesource.com/setup_22.x` to confirm the NodeSource hint interpolates `MIN_NODE_MAJOR` correctly.

- [ ] **Step 2: chmod +x the test file**

```bash
chmod +x installer/tests/test-node-version-check.sh
```

- [ ] **Step 3: Run the new test**

```bash
bash installer/tests/test-node-version-check.sh
```

Expected: `Results: 12 passed, 0 failed` (3 pass + 7 fail + 2 override = 12 cases). If any FAIL: read the output, fix the install.sh markers or the test, re-run.

---

## Task 5: Run regression tests

**Files:** none

- [ ] **Step 1: Run version-mapping test**

```bash
bash installer/tests/test-version-mapping.sh
```

Expected: `Results: 12 passed, 0 failed`.

- [ ] **Step 2: Run pkg-manager-detection test**

```bash
bash installer/tests/test-pkg-manager-detection.sh
```

Expected: `Results: 13 passed, 0 failed`.

- [ ] **Step 3: Final syntax check**

```bash
bash -n installer/install.sh
```

Expected: no output, exit 0.

- [ ] **Step 4: Confirm `install_distro_requirements` parses correctly via source**

```bash
bash -c '
    # Just parse the script, do not execute past pre-flight.
    # We use a here-doc trick: cat the script up to "# ── Pre-flight checks", source that.
    awk "/^# ── Pre-flight checks/{exit} {print}" installer/install.sh > /tmp/notur-prelude.sh
    source /tmp/notur-prelude.sh
    type install_distro_requirements >/dev/null && echo "OK: install_distro_requirements defined"
    type install_alpine_requirements >/dev/null 2>&1 && echo "FAIL: old name still defined" || echo "OK: old name removed"
    rm -f /tmp/notur-prelude.sh
'
```

Expected:
```
OK: install_distro_requirements defined
OK: old name removed
```

---

## Task 6: Update CHANGELOG

**Files:**
- Modify: `CHANGELOG.md` `[Unreleased]` section

- [ ] **Step 1: Add to `### Added`**

In the `[Unreleased]` `### Added` section (currently lines 11-16), append after the existing `Package-manager detection test...` line:

```markdown
- Node.js version-check shell test at `installer/tests/test-node-version-check.sh` covering accepted versions, too-old versions, parse failures, and the `MIN_NODE_MAJOR` override.
```

- [ ] **Step 2: Add to `### Changed`**

Append two new bullets to the `[Unreleased]` `### Changed` section (currently lines 18-21), after the existing two bullets:

```markdown
- **Installer Node.js version enforcement**: the installer now requires Node.js ≥ 22 (Pterodactyl Panel v1.12 baseline). Older Node fails fast with a NodeSource install hint instead of a cryptic webpack error mid-build. Override the threshold via `MIN_NODE_MAJOR=<n> bash install.sh` for forks pinned to older toolchains. This is technically breaking for anyone running on a too-old Node, but in practice those installs were already failing further down the pipeline — the fix is to fail clearly rather than fail cryptically.
- **Installer requirements bootstrap is now cross-distro**: `install_alpine_requirements()` is renamed to `install_distro_requirements()` and works on apt, dnf, yum, and pacman in addition to apk. Missing build tools (`bash`, `git`, `patch`, `make`, `perl`, `python3`, plus `coreutils`/`libstdc++` on Alpine) are auto-installed using the right package name per distro (e.g. `build-essential` on apt, `base-devel` on pacman, `make gcc-c++` on dnf/yum). Stripped Ubuntu/Debian/CentOS minimal containers no longer fail with cryptic "patch: command not found" errors.
```

- [ ] **Step 3: Verify CHANGELOG renders sanely**

```bash
head -30 CHANGELOG.md
```

Expected: shows the new bullets in the right sections, no broken markdown.

---

## Task 7: Final acceptance check, commit, push, open PR

**Files:** none

- [ ] **Step 1: Run all five acceptance checks in sequence**

```bash
bash -n installer/install.sh \
  && bash installer/tests/test-version-mapping.sh \
  && bash installer/tests/test-pkg-manager-detection.sh \
  && bash installer/tests/test-node-version-check.sh \
  && bash -c '
    awk "/^# ── Pre-flight checks/{exit} {print}" installer/install.sh > /tmp/notur-prelude.sh
    source /tmp/notur-prelude.sh
    type install_distro_requirements >/dev/null && echo "OK: function exists"
    rm -f /tmp/notur-prelude.sh
  '
```

Expected: all five succeed in order, last line is `OK: function exists`.

- [ ] **Step 2: git status check — only the expected files are modified**

```bash
git status --short
```

Expected (modulo untracked files like `checksums.json`):
```
 M CHANGELOG.md
 M installer/install.sh
?? installer/tests/test-node-version-check.sh
?? docs/superpowers/plans/2026-05-03-installer-cross-distro-hardening.md
```

The plan file is included in the commit because it documents the change. (If reviewers prefer plans omitted, drop it from `git add`.)

- [ ] **Step 3: Stage exactly the four files**

```bash
git add CHANGELOG.md installer/install.sh installer/tests/test-node-version-check.sh docs/superpowers/plans/2026-05-03-installer-cross-distro-hardening.md
```

- [ ] **Step 4: Commit (one commit, conventional commits, NO Co-Authored-By)**

```bash
git commit -m "$(cat <<'EOF'
feat(installer): enforce Node ≥ 22 and generalize distro bootstrap

Two cross-distro hardening changes for installer/install.sh on top of
the Alpine work in PR #34:

1. Node.js version check. The installer previously only verified node
   was on PATH, so users on Ubuntu 22.04 (Node 12), Ubuntu 24.04
   (Node 18), or Debian 12 (Node 18) would fail mid-build with a
   cryptic webpack error. We now read `node --version`, parse the
   major, and die with a clear NodeSource install hint when it's
   below MIN_NODE_MAJOR (default 22, overridable via env var for
   forks pinned to older toolchains). Pterodactyl Panel v1.12's
   own Dockerfile pins Node 22, so this matches upstream's baseline.

2. Generalized requirements bootstrap. `install_alpine_requirements`
   is renamed to `install_distro_requirements` and now resolves a
   logical-dep → distro-package map for apk/apt/dnf/yum/pacman
   instead of being Alpine-only. Stripped ubuntu:24.04, debian:
   bookworm-slim, and centos:stream9 containers were all failing
   the same way Alpine used to — "patch: command not found",
   "make: command not found" — because git/patch/make/perl/python3
   aren't always pre-installed. The Alpine-specific coreutils +
   libstdc++ rows stay Alpine-only (musl + busybox quirks).

Adds installer/tests/test-node-version-check.sh covering accepted
versions (22, 23), rejected versions (12, 18, 0.10), parse failures
(empty / non-numeric / nonzero exit), and the MIN_NODE_MAJOR override
(12 cases, all green). Mirrors the heredoc-with-env-var pattern from
test-pkg-manager-detection.sh — no outer-vs-inner quoting dance.

Existing test-version-mapping.sh (12/12) and test-pkg-manager-
detection.sh (13/13) still pass unchanged.

Wiring the bash tests into CI is a known follow-up.
EOF
)"
```

Note: NO `Co-Authored-By:` line. The user's global CLAUDE.md forbids it.

- [ ] **Step 5: Push the branch**

```bash
git push -u origin feat/installer-cross-distro-hardening
```

- [ ] **Step 6: Open the PR against master**

```bash
gh pr create --base master --title "feat(installer): enforce Node ≥ 22 and generalize distro bootstrap" --body "$(cat <<'EOF'
## Summary

Two cross-distro hardening changes to `installer/install.sh` on top of #34:

- **Node.js version check.** The installer previously only verified `node` was on PATH. Users on Ubuntu 22.04 (Node 12), Ubuntu 24.04 (Node 18), and Debian 12 (Node 18) would fail mid-build with a cryptic webpack error. We now read `node --version`, parse the major, and die with a clear NodeSource hint when it's below `MIN_NODE_MAJOR` (default 22, env-var-overridable). Matches the Node 22 baseline in the Pterodactyl Panel v1.12 Dockerfile.
- **Generalized requirements bootstrap.** `install_alpine_requirements()` → `install_distro_requirements()`, now keyed on `detect_sys_pkg_manager()`'s output. Auto-installs missing `bash`/`git`/`patch`/`make`/`perl`/`python3` on apt/dnf/yum/pacman in addition to apk, with the right package name per distro (`build-essential` on apt, `base-devel` on pacman, `make gcc-c++` on dnf/yum). Stripped Ubuntu/Debian/CentOS minimal containers no longer die with "patch: command not found" mid-install.

Precursor: #34 (Alpine compat).

## Why

After #34 made Alpine work, two cross-distro asymmetries remained:

1. **No Node version check.** Anyone on a system-package Node older than 22 (which is most Ubuntu LTS, Debian stable, and CentOS Stream installs) was already silently broken — they just hit the failure 4 minutes deeper into the install. This is fail-fast hygiene, not new restrictiveness.
2. **Alpine-only auto-bootstrap is weird design.** If we auto-fix one distro family but die cryptically on the others, that's surprising. Real-world severity is lower than Alpine (most full VMs have these tools) but the asymmetry was confusing.

## What changed

- `installer/install.sh`
  - Added `MIN_NODE_MAJOR="${MIN_NODE_MAJOR:-22}"` constant
  - Added Node version check block (bracketed by `=== node-version-check-{start,end} ===` markers for testability) after the existing `command -v node` presence check
  - Renamed `install_alpine_requirements` → `install_distro_requirements`, replaced body with a `pkg_name_for` logical→distro map keyed on `detect_sys_pkg_manager`
  - Dropped the `is_alpine` guard at the call site (function self-handles unknown package managers)
- `installer/tests/test-node-version-check.sh` — new, 12 cases (heredoc + env-var pattern from `test-pkg-manager-detection.sh`)
- `CHANGELOG.md` — `[Unreleased]` Added + Changed bullets

## Test plan

- [x] `bash -n installer/install.sh` — syntax valid
- [x] `bash installer/tests/test-version-mapping.sh` — 12/12 pass (regression)
- [x] `bash installer/tests/test-pkg-manager-detection.sh` — 13/13 pass (regression)
- [x] `bash installer/tests/test-node-version-check.sh` — 12/12 pass (new)
- [x] Sourcing prelude exposes `install_distro_requirements`, old name `install_alpine_requirements` is gone

## Out of scope / follow-ups

- Wiring the three bash tests into `.github/workflows/ci.yml` is a known follow-up — separate PR.
- We do NOT auto-upgrade Node when too old — too risky on systems where Node was installed by the user's package manager. Fail fast with the NodeSource hint instead.
- `print_node_install_hint`, `get_node_packages`, and `detect_pkg_manager` are unchanged.
EOF
)"
```

- [ ] **Step 7: Capture and report PR URL**

The `gh pr create` command above prints the PR URL on stdout. Surface that to the user.

---

## Self-Review Notes

- **Spec coverage**: Change 1 → Task 2; Change 2 → Task 3; Change 3 → Task 4; Change 4 → Task 6; acceptance criteria → Task 5+7.
- **Marker convention**: matches the spec's suggestion (`# === node-version-check-start/-end ===`). Test extraction is `sed -n '/start ===/,/end ===/p'`.
- **POSIX style**: All comparisons use `[ ]`, never `[[ ]]`. Case glob with `|` alternation is POSIX-compliant.
- **No `Co-Authored-By`**: explicitly omitted in commit and PR templates per global CLAUDE.md.
- **Branch base**: `origin/master` (not main, the repo uses master).
- **Single commit**: all changes go into one commit per spec constraint.
