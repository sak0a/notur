<?php

declare(strict_types=1);

namespace Notur\Cs2Modframework\Services;

use Pterodactyl\Repositories\Wings\DaemonFileRepository;

class GameInfoModifier
{
    private const GAMEINFO_PATH = '/game/csgo/gameinfo.gi';
    private const ANCHOR_LINE = 'Game_LowViolence';

    public function __construct(
        private readonly DaemonFileRepository $fileRepository,
    ) {
    }

    public function hasEntry(string $entry): bool
    {
        $content = $this->readGameInfo();

        return self::hasEntryInContent($content, $entry);
    }

    public function addEntry(string $entry): void
    {
        $content = $this->readGameInfo();

        if (self::hasEntryInContent($content, $entry)) {
            return;
        }

        $this->fileRepository->putContent(self::GAMEINFO_PATH . '.bak', $content);
        $this->fileRepository->putContent(self::GAMEINFO_PATH, self::addEntryToContent($content, $entry));
    }

    public function removeEntry(string $entry): void
    {
        $content = $this->readGameInfo();

        if (!self::hasEntryInContent($content, $entry)) {
            return;
        }

        $this->fileRepository->putContent(self::GAMEINFO_PATH . '.bak', $content);
        $this->fileRepository->putContent(self::GAMEINFO_PATH, self::removeEntryFromContent($content, $entry));
    }

    private function readGameInfo(): string
    {
        return $this->fileRepository->getContent(self::GAMEINFO_PATH);
    }

    public static function hasEntryInContent(string $content, string $entry): bool
    {
        foreach (explode("\n", $content) as $line) {
            if (self::lineMatchesEntry($line, $entry)) {
                return true;
            }
        }

        return false;
    }

    public static function addEntryToContent(string $content, string $entry): string
    {
        if (self::hasEntryInContent($content, $entry)) {
            return $content;
        }

        $lines = explode("\n", $content);
        $insertAt = null;

        foreach ($lines as $index => $line) {
            if (self::isCommentLine($line)) {
                continue;
            }

            if (preg_match('/^\s*' . preg_quote(self::ANCHOR_LINE, '/') . '\b/', $line) === 1) {
                $insertAt = $index + 1;
                break;
            }
        }

        if ($insertAt === null) {
            foreach ($lines as $index => $line) {
                if (!self::isCommentLine($line) && preg_match('/^\s*Game\s+\S+/', $line) === 1) {
                    $insertAt = $index + 1;
                    break;
                }
            }
        }

        if ($insertAt === null) {
            throw new \RuntimeException('Unable to update gameinfo.gi: no safe SearchPaths insertion point was found.');
        }

        $indent = self::detectIndent($lines[$insertAt - 1] ?? '');
        array_splice($lines, $insertAt, 0, [$indent . self::canonicalEntry($entry)]);

        return implode("\n", $lines);
    }

    public static function removeEntryFromContent(string $content, string $entry): string
    {
        if (!self::hasEntryInContent($content, $entry)) {
            return $content;
        }

        $lines = explode("\n", $content);
        $newLines = array_filter($lines, fn (string $line): bool => !self::lineMatchesEntry($line, $entry));

        return implode("\n", array_values($newLines));
    }

    private static function lineMatchesEntry(string $line, string $entry): bool
    {
        if (self::isCommentLine($line)) {
            return false;
        }

        $path = preg_quote(self::entryPath($entry), '/');

        return preg_match('/^\s*Game\s+' . $path . '\s*$/i', $line) === 1;
    }

    private static function entryPath(string $entry): string
    {
        if (preg_match('/^\s*Game\s+(\S+)\s*$/i', $entry, $matches) !== 1) {
            throw new \InvalidArgumentException("Invalid gameinfo.gi SearchPaths entry: {$entry}");
        }

        return $matches[1];
    }

    private static function canonicalEntry(string $entry): string
    {
        return 'Game ' . self::entryPath($entry);
    }

    private static function detectIndent(string $line): string
    {
        preg_match('/^(\s*)/', $line, $matches);

        return $matches[1] ?? "\t\t\t";
    }

    private static function isCommentLine(string $line): bool
    {
        return preg_match('/^\s*(\/\/|#)/', $line) === 1;
    }
}
