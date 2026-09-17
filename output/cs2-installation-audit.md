# CS2 extension installation audit

Checked 17 September 2026 against upstream installation documentation, GitHub release APIs and the Metamod Linux latest-build endpoint.

## Conclusion

The extension's download selection and destination paths still match upstream instructions, but it needs loader-order corrections and its automatic CounterStrikeSharp + latest Metamod combination cannot currently be assumed compatible. Source fixes for loader ordering are included in this workspace; existing `.notur` packages have not been rebuilt or deployed.

## Current upstream releases

| Framework | Latest observed | Selected Linux asset |
| --- | --- | --- |
| SwiftlyS2 | 1.4.10 | `swiftlys2-linux-v1.4.10-with-runtimes.zip` |
| CounterStrikeSharp | 1.0.374 | `counterstrikesharp-with-runtime-linux-1.0.374.zip` |
| Metamod:Source | 2.0.0-git1469 | `mmsource-2.0.0-git1469-linux.tar.gz` |

The extension resolves releases dynamically; no hardcoded framework version bump is needed. These assets match its existing filename filters. The documentation for both plugin frameworks calls for copying `addons` into `game/csgo`, matching the extraction destination. Bundled-runtime downloads remain appropriate, including for runtime-major-version upgrades.

## Findings

1. **Fixed: Swiftly/Metamod ordering depended on installation order.** Both were inserted immediately below `Game_LowViolence`, so installing Swiftly second placed it above Metamod. Swiftly explicitly requires Metamod first. The modifier now keeps Metamod before Swiftly and both before normal `Game` paths; reinstalling either also repairs existing incorrect ordering.
2. **Fixed: fallback insertion came after the first base-game path.** If `Game_LowViolence` was absent, the old implementation inserted the loader after the first `Game` entry. It now inserts before that entry.
3. **Upstream compatibility issue remains.** Issue #1415 reports CounterStrikeSharp 1.0.374 failing with recent Metamod builds because of a SourceHook API mismatch. Reports mention builds 1461, 1462 and 1467. The latest endpoint now returns 1469; this audit did not run that combination on a game server. The extension automatically installs latest Metamod when Metamod is detected as absent during CSS installation, so successful extraction must not be treated as proof the plugin will load. Some commenters report build 1411 working, but that is not a verified universal workaround and no automatic downgrade/pin was added.
4. **Metamod files alone are insufficient.** When installing CSS, the extension only checks Metamod's `installed` flag. It does not repair an existing Metamod installation's missing `gameinfo.gi` entry. Steam/game updates can overwrite this file. Status detects a missing entry, but the UI offers no repair/reinstall button when the recorded version is already current. A dedicated loader-repair action would address this without downloading the framework again.
5. **Linux prerequisites are not checked.** Bundling .NET does not supply all OS libraries. CSS documents ICU requirements (`libicu`, `icu-libs` or `libicu-dev`, depending on the distribution), or invariant globalization configuration. These belong in the server container image/environment. The extension currently cannot validate them through its file-only installation flow.
6. **Installation status is only a file/configuration heuristic.** A directory, marker file or gameinfo entry can produce `installed=true`; no console/runtime load verification occurs. Legacy `swiftly` and case-insensitive directory matches also do not establish that the current Linux SwiftlyS2 loader works.

## Operational installation steps

1. Stop the CS2 server and back up plugin/configuration files before changing frameworks.
2. Use a Linux runtime container meeting the selected framework's native dependencies.
3. For SwiftlyS2, extract the Linux **with-runtimes** archive into `game/csgo` and add its loader entry. It does not require Metamod.
4. For CSS, first select a Metamod/CSS combination verified against your CS2 build; extract their `addons` contents into `game/csgo`. Use CSS's **with-runtime** package.
5. Keep loader entries before the normal game paths, ordered `Game csgo/addons/metamod` then `Game csgo/addons/swiftlys2` when both are present.
6. Restart and verify `meta version`, `meta list` (CSS must be loaded without ERROR), and `sw` for Swiftly. Check startup logs for native-library/runtime errors.
7. Recheck loader entries after CS2 updates. Installing files does not certify compatibility with a new game build.

## Validation and limits

Regression tests cover both installation orders, repair of an existing bad order, insertion without the low-violence anchor, minimal SearchPaths, and existing detection/removal behavior. No live Wings/CS2 server was available for runtime validation. This audit did not install mods on a server, change framework pins, rebuild distributable extension archives, or publish a release.

## Sources

- [SwiftlyS2 installation](https://swiftlys2.net/docs/installation)
- [SwiftlyS2 latest release API](https://api.github.com/repos/swiftly-solution/swiftlys2/releases/latest)
- [CounterStrikeSharp installation](https://github.com/roflmuffin/CounterStrikeSharp/blob/main/INSTALL.md)
- [CounterStrikeSharp getting started](https://github.com/roflmuffin/CounterStrikeSharp/blob/main/docfx/docs/guides/getting-started.md)
- [CounterStrikeSharp releases](https://github.com/roflmuffin/CounterStrikeSharp/releases)
- [CounterStrikeSharp latest release API](https://api.github.com/repos/roflmuffin/CounterStrikeSharp/releases/latest)
- [Reported CSS/Metamod incompatibility, issue #1415](https://github.com/roflmuffin/CounterStrikeSharp/issues/1415)
- [Metamod installation / Source 2](https://wiki.alliedmods.net/Installing_SourceMM#Source_2)
- [Metamod latest Linux filename](https://mms.alliedmods.net/mmsdrop/2.0/mmsource-latest-linux)
