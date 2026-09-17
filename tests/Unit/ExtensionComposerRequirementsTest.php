<?php

declare(strict_types=1);

namespace Notur\Tests\Unit;

use Notur\Support\ExtensionComposerRequirements;
use Orchestra\Testbench\TestCase;

class ExtensionComposerRequirementsTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . '/notur-requirements-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
        config(['notur.version' => '1.5.1']);
    }

    protected function tearDown(): void
    {
        @unlink($this->directory . '/composer.json');
        rmdir($this->directory);
        parent::tearDown();
    }

    public function test_accepts_satisfied_host_and_platform_requirements(): void
    {
        file_put_contents($this->directory . '/composer.json', json_encode(['require' => [
            'php' => '^8.2', 'ext-json' => '*', 'notur/notur' => '^1.2', 'guzzlehttp/guzzle' => '^7.0',
        ]]));
        (new ExtensionComposerRequirements())->validate($this->directory);
        $this->addToAssertionCount(1);
    }

    public function test_rejects_missing_or_incompatible_dependency(): void
    {
        file_put_contents($this->directory . '/composer.json', json_encode(['require' => ['guzzlehttp/guzzle' => '^999.0']]));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('guzzlehttp/guzzle ^999.0');
        (new ExtensionComposerRequirements())->validate($this->directory);
    }

    public function test_rejects_missing_php_extension(): void
    {
        file_put_contents($this->directory . '/composer.json', json_encode(['require' => ['ext-notur-nonexistent' => '*']]));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PHP module ext-notur-nonexistent');
        (new ExtensionComposerRequirements())->validate($this->directory);
    }
}
