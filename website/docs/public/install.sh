#!/usr/bin/env bash
# Notur Extension Framework Installer — Redirect
# This file is served via GitHub Pages so users can run:
#   curl -sSL https://docs.notur.site/install.sh | bash
#
# It fetches the real installer from the repository.

set -euo pipefail

REPO="sak0a/notur"
BRANCH="master"
INSTALLER_URL="https://raw.githubusercontent.com/${REPO}/refs/heads/${BRANCH}/installer/install.sh"

echo "[Notur] Fetching installer from ${REPO}..."
curl -sSL "$INSTALLER_URL" | bash
