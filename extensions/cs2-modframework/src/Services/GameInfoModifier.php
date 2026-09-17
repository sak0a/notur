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

        $updated = self::addEntryToContent($content, $entry);
        if ($updated === $content) {
            return;
        }

        $this->fileRepository->putContent(self::GAMEINFO_PATH . '.bak', $content);
        $this->fileRepository->putContent(self::GAMEINFO_PATH, $updated);
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
        // Normalize both loaders together, including entries left in the wrong order
        // by older installs. Metamod must precede Swiftly and the base game paths.
        $loaders = ['Game csgo/addons/metamod', 'Game csgo/addons/swiftlys2'];
        $managed = in_array(self::canonicalEntry($entry), $loaders, true);
        $entries = $managed
            ? array_values(array_filter($loaders, fn (string $loader): bool =>
                self::hasEntryInContent($content, $loader) || $loader === self::canonicalEntry($entry)))
            : [self::canonicalEntry($entry)];
        $lines = explode("\n", $content);
        $firstGame = null;
        foreach ($lines as $index => $line) {
            if (!self::isCommentLine($line) && preg_match('/^\s*Game\s+\S+/', $line) === 1) {
                $firstGame = $index;
                break;
            }
        }

        // Keep an already-correct block byte-for-byte unchanged.
        if ($firstGame !== null) {
            $correct = true;
            foreach ($entries as $offset => $loader) {
                $correct = $correct && self::lineMatchesEntry($lines[$firstGame + $offset] ?? '', $loader);
            }
            if ($correct) {
                return $content;
            }
        }

        $indent = self::detectIndent($lines[$firstGame ?? 0] ?? '');
        $lines = array_values(array_filter($lines, function (string $line) use ($entries): bool {
            foreach ($entries as $loader) {
                if (self::lineMatchesEntry($line, $loader)) {
                    return false;
                }
            }
            return true;
        }));
        $insertAt = null;
        foreach ($lines as $index => $line) {
            if (self::isCommentLine($line)) {
                continue;
            }
            if (preg_match('/^\s*Game\s+\S+/', $line) === 1) {
                $insertAt = $index;
                $indent = self::detectIndent($line);
                break;
            }
            if (preg_match('/^\s*' . preg_quote(self::ANCHOR_LINE, '/') . '\b/', $line) === 1) {
                $insertAt = $index + 1;
                $indent = self::detectIndent($line);
                break;
            }
        }

        if ($insertAt === null && $firstGame !== null) {
            $insertAt = $firstGame;
        }

        if ($insertAt === null) {
            throw new \RuntimeException('Unable to update gameinfo.gi: no safe SearchPaths insertion point was found.');
        }

        array_splice($lines, $insertAt, 0, array_map(
            fn (string $loader): string => $indent . $loader,
            $entries,
        ));

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
