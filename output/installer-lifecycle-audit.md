# Installer and extension lifecycle audit

Completed 17 September 2026. Changes are in the working tree; nothing was deployed to a production panel.

## Implemented fixes

- Package-manager detection now runs in the requested panel directory, including when launched elsewhere or via a downloaded script. Invalid manager warnings no longer contaminate the selected command.
- Non-interactive terminal detection now tests whether a controlling terminal can actually be opened. Missing Yarn is bootstrapped as Yarn Classic instead of silently abandoning the panel's lockfile.
- System tool bootstrap runs after validating the panel/PHP/Composer. Missing required tools stop installation. PHP/Composer remain prerequisites; unattended deployments must provision compatible Node.
- Production environments include development dependencies needed for webpack, TypeScript and Tailwind builds. The Notur package resolves its tooling separately from the panel and checks its own Node engine range before source builds.
- Tailwind fallback uses the installed local CLI. Missing/empty required runtime assets fail installation. Yarn Classic execution no longer uses unsupported `yarn dlx`.
- Unsupported panel versions are rejected before Composer or panel-source changes. React patches distinguish already-applied patches from conflicts and never auto-reverse themselves on repeat installation.
- Framework installation/upgrades save private, uniquely named file snapshots before modifying Composer manifests, source or public assets. Extension replacement/removal and framework uninstall also retain snapshots. Success no longer deletes the only previous extension copy.
- Registry downloads must match the requested extension ID/version. Extension Composer requirements are checked against the panel's installed runtime before replacement.
- Extension updates preserve enabled/disabled state. Bulk updates support explicit unattended `--force` and reject unattended confirmation without it.
- Removal stops on migration rollback failures. Missing migration files preserve tracking records. Normal removal deletes extension settings; `--keep-data` preserves them. JSON-only installed state is recognized.
- Framework uninstall backs up files, safely reverses patches, requires a successful frontend rebuild, removes extensions in dependency order, and deletes exact framework migration records rather than every migration containing `notur`. Published config and caches are cleaned. Failures return nonzero instead of claiming completion.
- Extension build processes drain stdout/stderr concurrently to prevent native-build hangs and include build dependencies in production environments.
- Installation documentation now covers upgrades, file recovery, extension commands, requirements and Coolify/Docker persistence. Shell regression tests are wired into CI, and the Docker uninstall suite now exercises extension installation/replacement/removal.

## Verification

| Check | Result |
| --- | --- |
| Complete PHP unit/integration suite, local PHP 8.2.33 | 359 tests, 976 assertions passed |
| Installer helper/regression suites | 80 checks passed |
| Same shell suites inside Alpine container | Passed |
| Forward/reverse source patch round trip, Panel 1.12.2 | Passed, pristine source restored |
| Forward/reverse source patch round trip, Panel 1.15.1 | Passed, pristine source restored |
| Isolated Docker install, Panel 1.15.1 / PHP 8.4 / Node 24.21 / MySQL 8.4 | Passed |
| Repeat installer run from outside the panel directory | Passed; existing patches recognized |
| Source build with bridge and Tailwind artifacts intentionally absent, NODE_ENV=production | Passed; panel Yarn build and Notur npm builds succeeded |
| Docker extension install, duplicate detection, disabled upgrade, enable, removal and retained backups | Passed |
| Docker framework uninstall and reinstall/uninstall cycle | Passed; package, routes and runtime directories removed, backups retained |
| Shell syntax and git diff whitespace checks | Passed |

Docker tests used disposable containers. No production Coolify instance was accessed. Existing unrelated CS2 changes were left intact.

## Deployment and recovery boundaries

1. **Snapshots contain files, not database dumps.** Take a matching database backup before upgrades/removal. Database migrations can partially apply on engines with nontransactional DDL. Recovery is manual, and snapshots have no automatic retention/pruning policy.
2. **Persistent extension volumes do not preserve the framework installation across image replacement.** Preserve `notur`, `public/notur`, and `storage/notur`, plus existing panel/database storage. Include Composer dependencies, patched source and compiled panel assets in the deployed image, or rerun installation before serving traffic after redeploy. The complete installer needs database connectivity for migrations.
3. Provision PHP 8.2+, Composer, Bash and a supported Node version. This release's source-build engine range is `^22.22.2 || ^24.15.0 || >=26.0.0`; the panel requires Node 22+. System package installation requires sufficient privileges. Node installation can prompt; it is not an unattended distro-independent Node upgrade mechanism.
4. Extension archives contain compiled frontend assets and exclude `vendor`/`node_modules`. Compatible third-party Composer dependencies must be installed in the panel before adding an extension. The extension installer validates these requirements rather than automatically changing the panel dependency graph.
5. Full application installation was tested on the Debian-based Docker image above. Alpine testing covered the shell installer regression suites, not an entire Alpine panel or a live Coolify redeployment. Yarn Classic and npm received real build coverage; pnpm/Bun selection and failure paths received helper-test coverage.
6. Run one lifecycle operation at a time and stop serving traffic for manual recovery. These changes do not add a cross-process transaction covering Composer, files, builds and SQL. Developer-only `notur:dev:pull` is a separate vendor-source workflow and is outside the release-installer guarantees tested here.

Reference: [Coolify persistent storage](https://coolify.io/docs/applications/configuration/persistent-storage) explains container replacement and persistence; [Yarn Classic install documentation](https://classic.yarnpkg.com/en/docs/cli/install) documents production dependency omission.
