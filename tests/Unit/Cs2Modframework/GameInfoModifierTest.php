<?php

declare(strict_types=1);

namespace Notur\Tests\Unit\Cs2Modframework;

use Notur\Cs2Modframework\Services\GameInfoModifier;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__ . '/../../../extensions/cs2-modframework/src/Services/GameInfoModifier.php';

class GameInfoModifierTest extends TestCase
{
    public function test_detects_entry_with_spaces_or_tabs(): void
    {
        $content = "SearchPaths\n{\n\t\t\tGame\tcsgo/addons/metamod\n}\n";

        $this->assertTrue(GameInfoModifier::hasEntryInContent($content, 'Game csgo/addons/metamod'));
        $this->assertTrue(GameInfoModifier::hasEntryInContent($content, "Game\tcsgo/addons/metamod"));
    }

    public function test_ignores_commented_entries(): void
    {
        $content = "SearchPaths\n{\n\t\t\t// Game csgo/addons/metamod\n}\n";

        $this->assertFalse(GameInfoModifier::hasEntryInContent($content, 'Game csgo/addons/metamod'));
    }

    public function test_adds_entry_after_low_violence_anchor(): void
    {
        $content = implode("\n", [
            'SearchPaths',
            '{',
            "\t\t\tGame_LowViolence csgo_lv",
            "\t\t\tGame csgo",
            '}',
        ]);

        $updated = GameInfoModifier::addEntryToContent($content, 'Game csgo/addons/metamod');

        $this->assertStringContainsString("\t\t\tGame_LowViolence csgo_lv\n\t\t\tGame csgo/addons/metamod\n\t\t\tGame csgo", $updated);
    }

    public function test_add_is_idempotent_for_equivalent_whitespace(): void
    {
        $content = "SearchPaths\n{\n\t\t\tGame   csgo/addons/metamod\n}\n";

        $this->assertSame($content, GameInfoModifier::addEntryToContent($content, 'Game csgo/addons/metamod'));
    }

    public function test_remove_only_active_matching_entry(): void
    {
        $content = implode("\n", [
            'SearchPaths',
            '{',
            "\t\t\t// Game csgo/addons/metamod",
            "\t\t\tGame csgo/addons/metamod",
            "\t\t\tGame csgo",
            '}',
        ]);

        $updated = GameInfoModifier::removeEntryFromContent($content, 'Game csgo/addons/metamod');

        $this->assertStringContainsString('// Game csgo/addons/metamod', $updated);
        $this->assertFalse(GameInfoModifier::hasEntryInContent($updated, 'Game csgo/addons/metamod'));
    }

    public function test_add_throws_when_no_safe_insertion_point_exists(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no safe SearchPaths insertion point');

        GameInfoModifier::addEntryToContent("SearchPaths\n{\n}\n", 'Game csgo/addons/metamod');
    }
}
