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

        return str_contains($content, $entry);
    }

    public function addEntry(string $entry): void
    {
        $content = $this->readGameInfo();

        // Already present — idempotent
        if (str_contains($content, $entry)) {
            return;
        }

        // Create backup first
        $this->fileRepository->putContent(self::GAMEINFO_PATH . '.bak', $content);

        // Find the anchor line and insert after it
        $lines = explode("\n", $content);
        $newLines = [];
        $inserted = false;

        foreach ($lines as $line) {
            $newLines[] = $line;

            if (!$inserted && str_contains($line, self::ANCHOR_LINE)) {
                // Detect indentation from the anchor line
                preg_match('/^(\s*)/', $line, $matches);
                $indent = $matches[1] ?? "\t\t\t";
                $newLines[] = $indent . $entry;
                $inserted = true;
            }
        }

        if (!$inserted) {
            // Fallback: append in SearchPaths block if anchor not found
            // Try to find any "Game" line and insert after it
            $newLines = [];
            foreach ($lines as $line) {
                $newLines[] = $line;
                if (!$inserted && preg_match('/^\s+Game\s+/', $line)) {
                    preg_match('/^(\s*)/', $line, $matches);
                    $indent = $matches[1] ?? "\t\t\t";
                    $newLines[] = $indent . $entry;
                    $inserted = true;
                }
            }
        }

        $this->fileRepository->putContent(self::GAMEINFO_PATH, implode("\n", $newLines));
    }

    public function removeEntry(string $entry): void
    {
        $content = $this->readGameInfo();

        if (!str_contains($content, $entry)) {
            return;
        }

        // Create backup first
        $this->fileRepository->putContent(self::GAMEINFO_PATH . '.bak', $content);

        $lines = explode("\n", $content);
        $newLines = array_filter($lines, fn (string $line) => !str_contains($line, $entry));

        $this->fileRepository->putContent(self::GAMEINFO_PATH, implode("\n", array_values($newLines)));
    }

    private function readGameInfo(): string
    {
        return $this->fileRepository->getContent(self::GAMEINFO_PATH);
    }
}
