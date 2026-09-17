#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
BASE_IMAGE="${E2E_BASE_IMAGE:-notur/e2e-base:php8.4-node24-panel1.15.1}"

docker build \
    -f "${SCRIPT_DIR}/Dockerfile.base" \
    -t "$BASE_IMAGE" \
    "$PROJECT_ROOT"
