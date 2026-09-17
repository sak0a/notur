<?php

declare(strict_types=1);

namespace Notur\Tests\Unit\Console;

use Illuminate\Console\OutputStyle;
use Notur\Console\Commands\BuildCommand;
use Orchestra\Testbench\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class BuildCommandTest extends TestCase
{
    public function test_process_drains_large_stderr_and_stdout_and_passes_build_environment(): void
    {
        $command = new BuildCommand();
        $command->setLaravel($this->app);
        $output = new BufferedOutput();
        $command->setOutput(new OutputStyle(new ArrayInput([]), $output));
        $script = 'fwrite(STDERR, str_repeat("e", 262144)); fwrite(STDOUT, str_repeat("o", 262144)); exit(getenv("NODE_ENV") === "development" ? 0 : 2);';
        $method = new \ReflectionMethod($command, 'runProcess');
        $status = $method->invoke($command, escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script), sys_get_temp_dir(), ['NODE_ENV' => 'development']);
        $this->assertSame(0, $status);
        $text = $output->fetch();
        $this->assertSame(262144, substr_count($text, 'e'));
        $this->assertSame(262144, substr_count($text, 'o'));
    }
}
