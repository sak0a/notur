# Extension management UI audit

Completed 17 September 2026. Fixes are in the working tree; no production deployment was performed.

## Changes

- Installation accepts either a registry ID or an uploaded `.notur` archive, with validation for conflicting inputs. Replacing an installed extension requires an explicit checkbox instead of silently forcing replacement.
- Detached `.sig` uploads are supported and required when signature enforcement is configured. Uploads use isolated private temporary directories with cleanup.
- Extension update buttons call the version-aware update command. Updates preserve disabled state and do not downgrade extensions. Bulk updates continue after individual failures and report the results.
- Removal offers a Keep data option on both the list and detail pages. Confirmation explains whether settings and migration-managed database tables will be removed or retained. Missing-extension enable/disable actions return feedback instead of an unhandled error.
- Lifecycle forms display progress feedback, prevent repeated submissions, and recover their controls when returning through browser history. Cancelling confirmation leaves the controls usable.
- Failed extension loading and safe-mode suspension are visible in the list. Versions newer than the registry are distinguished from available upgrades.
- The install form and page headers fit mobile screens. Slot filtering has accessible labels, and its extension column accurately describes active registrations.
- Framework self-update invokes the complete installer rather than Composer alone, so the installer handles backups, migrations, patches and assets. Failures and timeouts are surfaced.
- Archive extraction checks unsafe paths, symlinks, entry count and expanded size before extracting. Compressed expansion is bounded before Phar opens the archive.
- Archive packing uses unique temporary tar paths, supports repeated packing to the same destination, cleans temporary files and restores pre-existing source checksums.
- The Docker browser harness preserves the incoming host port in FastCGI and seeds a local upgrade fixture for repeatable tests.

## Verification

| Check | Result |
| --- | --- |
| Complete PHP unit/integration suite, PHP 8.2.33 | 373 tests, 1,023 assertions passed |
| Browser suite against disposable Panel 1.15.1 / PHP 8.4 / MySQL Docker environment | 27 tests passed |
| Desktop and 390px mobile inspection | Install controls, headers and slots inspected |
| Whitespace check | Passed |

Browser coverage includes registry and archive installs, duplicate replacement, conflicting inputs, invalid archives, disabled-state upgrade, enable/disable, ordinary and keep-data removal, missing-file removal, cancellation, duplicate-submit prevention, settings persistence/validation, slots filtering, health/diagnostics, runtime rendering and non-admin access. HTTP integration tests additionally cover signed uploads, backup creation, downgrade prevention and continuing bulk updates after an exception.

## Operational limits

- Framework self-update dispatch is tested; a full framework upgrade was not triggered through the browser. It still requires writable panel files, available build tools and sufficient web-server/PHP timeouts. For immutable Coolify images, bake updates into the image and redeploy.
- Actions remain synchronous. Progress is a waiting indicator, not a live stream of installer stages. The process timeout is 300 seconds; web-server limits can be shorter.
- The application accepts extension uploads up to 50 MB, but PHP and proxy limits may be lower. Archive limits are 512 MiB of file contents and 10,000 entries, plus bounded tar overhead during decompression.
- Lifecycle snapshots are file backups, not database backups. Keep data preserves settings and tables; it does not replace a database backup or provide a restore UI.
- No production Coolify instance was accessed. Existing unrelated CS2 changes were preserved.
