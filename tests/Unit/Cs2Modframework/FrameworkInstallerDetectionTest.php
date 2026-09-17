<?php

declare(strict_types=1);

namespace Pterodactyl\Exceptions\Http\Connection {
    if (!class_exists(DaemonConnectionException::class, false)) {
        class DaemonConnectionException extends \RuntimeException
        {
        }
    }
}

namespace Pterodactyl\Repositories\Wings {
    if (!class_exists(DaemonFileRepository::class, false)) {
        class DaemonFileRepository
        {
            public function getDirectory(string $path): array
            {
                return [];
            }

            public function getContent(string $path, ?int $notLargerThan = null): string
            {
                return '';
            }
        }
    }
}

namespace Notur\Tests\Unit\Cs2Modframework {

use Notur\Cs2Modframework\Services\FrameworkInstaller;
use Notur\Cs2Modframework\Services\GameInfoModifier;
use Notur\Cs2Modframework\Services\GitHubReleaseResolver;
use PHPUnit\Framework\TestCase;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;

require_once __DIR__ . '/../../../extensions/cs2-modframework/src/Services/GameInfoModifier.php';
require_once __DIR__ . '/../../../extensions/cs2-modframework/src/Services/GitHubReleaseResolver.php';
require_once __DIR__ . '/../../../extensions/cs2-modframework/src/Services/InstalledVersionDetector.php';
require_once __DIR__ . '/../../../extensions/cs2-modframework/src/Services/FrameworkInstaller.php';

class FrameworkInstallerDetectionTest extends TestCase
{
    public function test_detects_case_insensitive_framework_directories(): void
    {
        $installer = $this->makeInstaller(new FakeDaemonFileRepository([
            '/game/csgo/addons' => [
                ['name' => 'MetaMod', 'is_file' => false],
                ['name' => 'CounterStrikeSharp', 'is_file' => false],
                ['name' => 'SwiftlyS2', 'is_file' => false],
            ],
        ]));

        $status = $installer->getStatus();

        $this->assertTrue($status['metamod']['installed']);
        $this->assertSame('game/csgo/addons/MetaMod', $status['metamod']['directory']);
        $this->assertTrue($status['counterstrikesharp']['installed']);
        $this->assertSame('game/csgo/addons/CounterStrikeSharp', $status['counterstrikesharp']['directory']);
        $this->assertTrue($status['swiftly']['installed']);
        $this->assertSame('game/csgo/addons/SwiftlyS2', $status['swiftly']['directory']);
    }

    public function test_detects_manual_css_and_metamod_marker_files(): void
    {
        $installer = $this->makeInstaller(new FakeDaemonFileRepository([
            '/game/csgo/addons' => [
                ['name' => 'metamod.vdf', 'is_file' => true],
            ],
            '/game/csgo/addons/metamod' => [
                ['name' => 'counterstrikesharp.vdf', 'is_file' => true],
            ],
        ]));

        $status = $installer->getStatus();

        $this->assertTrue($status['metamod']['installed']);
        $this->assertSame('game/csgo/addons/metamod', $status['metamod']['directory']);
        $this->assertTrue($status['counterstrikesharp']['installed']);
        $this->assertSame('game/csgo/addons/counterstrikesharp', $status['counterstrikesharp']['directory']);
    }

    public function test_detects_css_marker_inside_case_variant_metamod_directory(): void
    {
        $installer = $this->makeInstaller(new FakeDaemonFileRepository([
            '/game/csgo/addons' => [
                ['name' => 'MetaMod', 'is_file' => false],
            ],
            '/game/csgo/addons/MetaMod' => [
                ['name' => 'counterstrikesharp.vdf', 'is_file' => true],
                ['name' => 'bin', 'is_file' => false],
            ],
        ]));

        $status = $installer->getStatus();

        $this->assertTrue($status['metamod']['installed']);
        $this->assertSame('game/csgo/addons/MetaMod', $status['metamod']['directory']);
        $this->assertTrue($status['counterstrikesharp']['installed']);
        $this->assertSame('game/csgo/addons/counterstrikesharp', $status['counterstrikesharp']['directory']);
    }

    public function test_detects_framework_markers_when_addons_listing_is_unavailable(): void
    {
        $installer = $this->makeInstaller(new FakeDaemonFileRepository([
            '/game/csgo/addons/metamod' => [
                ['name' => 'metaplugins.ini', 'is_file' => true],
                ['name' => 'plugins', 'is_file' => false],
                ['name' => 'counterstrikesharp.vdf', 'is_file' => true],
            ],
            '/game/csgo/addons/counterstrikesharp' => [
                ['name' => 'CounterStrikeSharp.API.dll', 'is_file' => true],
            ],
        ]));

        $status = $installer->getStatus();

        $this->assertTrue($status['metamod']['installed']);
        $this->assertTrue($status['counterstrikesharp']['installed']);
    }

    public function test_detects_gameinfo_entries_as_install_signals(): void
    {
        $installer = $this->makeInstaller(new FakeDaemonFileRepository(
            ['/game/csgo/addons' => []],
            "SearchPaths\n{\n\t\t\tGame csgo/addons/metamod\n\t\t\tGame csgo/addons/swiftlys2\n\t\t\tGame csgo\n}\n",
        ));

        $status = $installer->getStatus();

        $this->assertTrue($status['metamod']['installed']);
        $this->assertTrue($status['swiftly']['installed']);
        $this->assertFalse($status['counterstrikesharp']['installed']);
        $this->assertTrue($status['gameinfo_entries']['metamod']);
        $this->assertTrue($status['gameinfo_entries']['swiftly']);
    }

    public function test_status_includes_recorded_installed_version(): void
    {
        $installer = $this->makeInstaller(new FakeDaemonFileRepository([
            '/game/csgo/addons' => [
                ['name' => 'swiftlys2', 'is_file' => false],
            ],
        ], null, [
            '/game/csgo/addons/.notur-framework-versions.json' => json_encode([
                'swiftly' => ['version' => '1.3.2'],
            ]),
        ]));

        $status = $installer->getStatus();

        $this->assertTrue($status['swiftly']['installed']);
        $this->assertSame('1.3.2', $status['swiftly']['installed_version']);
        $this->assertNull($status['metamod']['installed_version']);
    }

    public function test_manual_versions_override_stale_records_and_preserve_case(): void
    {
        $files = [
            '/game/csgo/addons/CounterStrikeSharp/api/CounterStrikeSharp.API.deps.json' => json_encode([
                'libraries' => ['Some.Plugin/9.9.9' => [], 'CounterStrikeSharp.API/1.0.374' => []],
            ]),
            '/game/csgo/addons/SwiftlyS2/bin/managed/SwiftlyS2.CS2.deps.json' => json_encode([
                'libraries' => ['SwiftlyS2.CS2/1.4.10' => []],
            ]),
            '/game/csgo/addons/metamod/bin/linuxsteamrt64/metamod.2.cs2.so' => "\x7fELF\x00other\x002.0.0-dev+1469\x00",
            '/game/csgo/addons/.notur-framework-versions.json' => json_encode([
                'swiftly' => ['version' => '1.0.0'],
            ]),
        ];
        $repository = new FakeDaemonFileRepository([
            '/game/csgo/addons' => array_map(fn ($name) => ['name' => $name, 'is_file' => false], ['CounterStrikeSharp', 'SwiftlyS2', 'metamod']),
        ], null, $files);
        $status = $this->makeInstaller($repository)->getStatus();
        foreach (['swiftly' => '1.4.10', 'counterstrikesharp' => '1.0.374', 'metamod' => '2.0.0-git1469'] as $framework => $version) {
            $this->assertSame($version, $status[$framework]['installed_version']);
            $this->assertSame('server_files', $status[$framework]['version_source']);
        }
    }

    public function test_unreadable_metadata_falls_back_to_labeled_install_record(): void
    {
        $status = $this->makeInstaller(new FakeDaemonFileRepository([
            '/game/csgo/addons' => [['name' => 'swiftlys2', 'is_file' => false]],
        ], null, [
            '/game/csgo/addons/swiftlys2/bin/managed/SwiftlyS2.CS2.deps.json' => '{bad json',
            '/game/csgo/addons/.notur-framework-versions.json' => '{"swiftly":{"version":"1.2.0"}}',
        ]))->getStatus();
        $this->assertSame('1.2.0', $status['swiftly']['installed_version']);
        $this->assertSame('install_record', $status['swiftly']['version_source']);
    }

    public function test_unrelated_or_ambiguous_metadata_does_not_guess_a_version(): void
    {
        foreach ([
            '{"libraries":{"Some.Plugin/9.9.9":{}}}',
            '{"libraries":{"SwiftlyS2.CS2/1.0.0":{},"SwiftlyS2.CS2/2.0.0":{}}}',
            '{"libraries":{"SwiftlyS2.CS2/not-a-version":{}}}',
            str_repeat('x', 262145),
        ] as $content) {
            $detector = new \Notur\Cs2Modframework\Services\InstalledVersionDetector(new FakeDaemonFileRepository([], null, [
                '/game/csgo/addons/swiftlys2/bin/managed/SwiftlyS2.CS2.deps.json' => $content,
            ]));
            $this->assertNull($detector->detect('swiftly', 'swiftlys2'));
        }
        $detector = new \Notur\Cs2Modframework\Services\InstalledVersionDetector(new FakeDaemonFileRepository([], null, [
            '/game/csgo/addons/metamod/bin/linuxsteamrt64/metamod.2.cs2.so' => "\x002.0.0-dev+1469\x00",
        ]));
        $this->assertNull($detector->detect('metamod', 'metamod'));
        $this->assertNull($detector->detect('swiftly', '../swiftlys2'));
    }

    private function makeInstaller(FakeDaemonFileRepository $repository): FrameworkInstaller
    {
        $releaseResolver = $this->createMock(GitHubReleaseResolver::class);
        $gameInfoModifier = new GameInfoModifier($repository);

        return new FrameworkInstaller($repository, $releaseResolver, $gameInfoModifier);
    }
}

class FakeDaemonFileRepository extends DaemonFileRepository
{
    /**
     * @param array<string, array<int, array{name: string, is_file: bool}>> $directories
     */
    public function __construct(
        private readonly array $directories,
        private readonly ?string $gameinfo = null,
        private array $files = [],
    ) {
    }

    public function getDirectory(string $path): array
    {
        if (!array_key_exists($path, $this->directories)) {
            throw new \RuntimeException("Missing directory {$path}");
        }

        return $this->directories[$path];
    }

    public function getContent(string $path, ?int $notLargerThan = null): string
    {
        if (array_key_exists($path, $this->files)) {
            return $this->files[$path];
        }

        if ($path !== '/game/csgo/gameinfo.gi' || $this->gameinfo === null) {
            throw new \RuntimeException("Missing file {$path}");
        }

        return $this->gameinfo;
    }

    public function putContent(string $path, string $content): void
    {
        $this->files[$path] = $content;
    }
}
}
