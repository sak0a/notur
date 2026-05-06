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

            public function getContent(string $path): string
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
    ) {
    }

    public function getDirectory(string $path): array
    {
        if (!array_key_exists($path, $this->directories)) {
            throw new \RuntimeException("Missing directory {$path}");
        }

        return $this->directories[$path];
    }

    public function getContent(string $path): string
    {
        if ($path !== '/game/csgo/gameinfo.gi' || $this->gameinfo === null) {
            throw new \RuntimeException("Missing file {$path}");
        }

        return $this->gameinfo;
    }
}
}
