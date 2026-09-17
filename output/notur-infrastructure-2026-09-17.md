# Notur infrastructure maintenance — 17 September 2026

## Live upgrade

Pterodactyl 1.15.1 now runs **Notur 1.5.3**, upgraded from 1.5.2 using the tagged installer with Composer pinned to 1.5.3. The installer exited 0; the frontend rebuilt and migrations reported nothing pending.

- CS2 Mod Frameworks **1.1.4 remains enabled**. Its five health checks passed without warnings or errors.
- The CS2 Mod Frameworks UI loaded and returned framework status and release choices.
- `notur:status`, run as nginx, reported a compatible runtime and one enabled extension.
- `/app/notur/extensions.json.lock` remains **nginx:nginx, mode 0644**; nginx write access was verified.
- `/var/lib/pterodactyl/machine-id` remains a **directory, root:root, mode 0755**.
- Both VMs and both previously running TF2 containers remained running. The TF2 console connected and displayed live usage and uptime. The previously stopped CS2 servers were left stopped.

## Host memory

FameSystems Main has 32,094 MiB RAM (31.34 GiB). Its management VM is allocated 18 GiB and secondary VM 8 GiB. Two running game containers each have approximately 4.30 GiB Docker limits. Swap totals 1,023 MiB, with approximately 863 MiB already used.

The kernel recorded a **global OOM**, killing the management VM's QEMU process with 18,760,432 KiB anonymous RSS (approximately 17.89 GiB). The panel had no memory limit, and webpack's Terser configuration enabled parallel workers.

Applied without container restarts:

- Panel hard limit: **2 GiB RAM**, with **2 GiB combined RAM + swap**, meaning no container swap allowance.
- Saved matching `mem_limit: 2g` and `memswap_limit: 2g` in Coolify Compose for subsequent deployments.
- Upgrade environment: Node heap **1,024 MiB**, Composer **512 MiB**, package-manager child concurrency **1**, and process niceness **15**.
- Live webpack configuration: **Terser parallel workers disabled**, `module.exports.parallelism = 1`.
- Saved Node heap and package-manager concurrency settings in Coolify's panel environment for subsequent deployments.
- Checked at least **6 GiB MemAvailable** before starting; approximately **15.6 GiB** was available. This is an operational build gate, not a capacity guarantee.

The upgrade completed with zero panel cgroup OOM/OOM-kill events and no new host OOM entries. Available memory remained approximately 15.6 GiB afterward. The cgroup's historical peak predates these limits and is not a measurement of this upgrade's peak.

**Capacity limitation:** configured VM and active game-container maxima alone total approximately 34.6 GiB, before the panel and host overhead. Therefore the host remains overcommitted at simultaneous maximum load. Use off-host builds when headroom is low; do not run unbounded builds here. A lasting capacity guarantee requires additional RAM or reducing/moving workload allocations during planned maintenance. No VM or game-server memory allocation was reduced.

Docker documents hard limits and combined memory/swap semantics at <https://docs.docker.com/engine/containers/resource_constraints/>.

## Backups and persistence

Backups are retained **on FameSystems Main**, outside the panel container:

`/root/notur-backups/20260917-v1.5.2/`

- `panel.tar.gz`: approximately 79 MiB; panel files, vendor dependencies, assets, and extension state. Reinstallable `node_modules`, runtime logs, and nested installer backups were excluded.
- `database.sql.gz`: approximately 43 KiB; a single-transaction MariaDB dump with routines, triggers, and events. Forty CREATE TABLE statements were verified.
- `panel-inspect.json`: original container configuration.
- `upgrade.log`: installer output.

Both backup commands succeeded, and archive readability was checked. Backup files are private to root and are deliberately not attached to this report. A restore was not performed against production.

Coolify's Compose changes are saved; the memory cap was also applied directly to the running container. A full stack redeployment was deliberately not performed. The existing deployment still uses the upstream Pterodactyl image: custom vendor files, patched sources, and built Notur assets in the container layer must be baked into a custom image or restored/reinstalled before replacing that container. Existing volume layout was preserved.

## E2E CI

The missing image was published using the repository's existing workflow:

`ghcr.io/sak0a/notur-e2e-base:php8.4-node24-panel1.15.1`

Digest: `sha256:4729501cb59a08ae4644d44193a8591e0c2f7524e060a663759e2d7139f2a789`

[Successful base-image publication](https://github.com/sak0a/notur/actions/runs/35259655023)

The original failure was caused by the absent registry tag. Its local fallback did not help because the subsequent isolated Docker-container Buildx builder did not consume the Docker daemon's locally built base image. Publishing the tag resolves that lookup. The existing fallback implementation was not changed.

- [Original failed E2E run, rerun](https://github.com/sak0a/notur/actions/runs/35257079372)
- [Fresh E2E run on master](https://github.com/sak0a/notur/actions/runs/35260085921)

**Both runs completed successfully.** Shell checks, all 27 browser tests, and the install/uninstall lifecycle suite passed. The original failure is resolved, and the fresh run verifies the current master branch. No repository source or workflow changes were required to publish the missing image.
