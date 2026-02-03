<?php

declare(strict_types=1);

namespace Notur\Support;

use Symfony\Component\Console\Output\OutputInterface;

final class ConsoleBanner
{
    private static bool $printed = false;

    public static function render(OutputInterface $output): void
    {
        if (self::$printed || self::isDisabled()) {
            return;
        }

        $useColor = $output->isDecorated() && !self::noColor();

        foreach (self::lines($useColor) as $line) {
            $output->writeln($line);
        }

        $output->writeln('');

        self::$printed = true;
    }

    private static function isDisabled(): bool
    {
        $value = getenv('NOTUR_NO_BANNER');
        if ($value === false || $value === '') {
            return false;
        }

        return !in_array(strtolower($value), ['0', 'false', 'off'], true);
    }

    private static function noColor(): bool
    {
        $value = getenv('NO_COLOR');
        return $value !== false && $value !== '';
    }

    /**
     * @return array<int, string>
     */
    private static function lines(bool $color): array
    {
        if ($color) {
            $purpleBg = "\033[48;2;124;58;237m"; // #7c3aed
            $white = "\033[38;2;255;255;255m";
            $reset = "\033[0m";

            $blockTop = $purpleBg . '      ' . $reset;
            $blockMid = $purpleBg . '  ' . $white . 'N' . $purpleBg . '   ' . $reset;
            $text = $white . 'Notur' . $reset;

            return [
                '  ' . $blockTop,
                '  ' . $blockMid . '  ' . $text,
                '  ' . $blockTop,
            ];
        }

        return [
            '  +------+  Notur',
            '  |  N  |',
            '  +------+',
        ];
    }
}
