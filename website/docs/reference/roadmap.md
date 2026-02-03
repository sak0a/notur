# Roadmap

## Current Release

- Version: 1.0.0 (2026-02-03)
- Status: Stable
- Lifecycle CLI, registry distribution, admin UI, and theme extensions are complete
- Ed25519 signature verification is available and optional via `notur.require_signatures`
- E2E and compatibility matrix testing are in place

## Near-Term Focus

- Stabilize frontend tests and CI (Jest config)
- Validate integration tests in CI (Orchestra Testbench + SQLite)
- Harden the installer on non-macOS Linux
- Validate patch checksums on subsequent installer runs
- Verify `notur:dev` symlink workflow with real extensions

## Versioning

- Notur follows semantic versioning.
- Extension manifests use `notur: "1.0"` as the format version.
- Compatibility target: Pterodactyl Panel v1.11+, PHP 8.2+, Node.js 22+.

See the changelog for release history.
