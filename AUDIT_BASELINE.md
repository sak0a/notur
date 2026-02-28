# Notur Audit Baseline

Captured on `2026-02-28T03:20:34Z` (UTC) on branch `codex/notur-audit-hardening`.

## Baseline Commands

```bash
./vendor/bin/phpunit --display-warnings
npm run test:frontend -- --runInBand
composer audit --format=json
npm audit --audit-level=high --json
ls src/Console/Commands | sed 's/\.php$//' | sort
```

## Baseline Results

- PHPUnit: `OK (207 tests, 435 assertions)` with no warnings.
- Frontend tests: `14` suites passed, `97` tests passed.
- `composer audit`: no advisories, no abandoned packages.
- `npm audit --audit-level=high`: `10` vulnerabilities (`9 high`, `1 moderate`) in current JS dependency graph.
- Console command inventory: `17` commands currently shipped (`Build`, `Dev`, `DevPull`, `Disable`, `Enable`, `Export`, `Install`, `Keygen`, `List`, `New`, `RegistryStatus`, `RegistrySync`, `Remove`, `Status`, `Uninstall`, `Update`, `Validate`).

## Non-Regression Gates

Every subsequent phase should keep these gates green unless a phase explicitly updates the gate definition:

1. `./vendor/bin/phpunit --display-warnings` must pass.
2. `npm run test:frontend -- --runInBand` must pass.
3. `notur:install` must reject archives with missing/invalid checksums by default.
4. Signature-required installs from registry must fail fast on missing/invalid `.sig`.
5. `notur:new` frontend scaffolds must not hardcode Bun-only commands.
6. CI frontend test step must not ignore failures.
