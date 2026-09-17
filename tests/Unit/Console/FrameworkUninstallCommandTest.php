<?php

declare(strict_types=1);

namespace Notur\Tests\Unit\Console;

use Notur\Console\Commands\FrameworkUninstallCommand;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;

class FrameworkUninstallCommandTest extends TestCase
{
    public static function panelVersions(): array
    {
        return [
            ['1.15.0', 'v1.15'],
            ['v1.15.1', 'v1.15'],
            ['1.12.2', 'v1.12'],
        ];
    }

    #[DataProvider('panelVersions')]
    public function test_selects_reverse_patches_for_the_root_panel_version(string $version, string $expected): void
    {
        config(['app.version' => $version]);
        $command = new FrameworkUninstallCommand();
        $method = new ReflectionMethod($command, 'detectPatchVersion');

        $this->assertSame($expected, $method->invoke($command));
    }
}
