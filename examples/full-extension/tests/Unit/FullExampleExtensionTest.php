<?php

declare(strict_types=1);

namespace Notur\FullExtension\Tests\Unit;

use Notur\FullExtension\FullExampleExtension;
use PHPUnit\Framework\TestCase;

class FullExampleExtensionTest extends TestCase
{
    public function test_metadata_is_loaded_from_manifest(): void
    {
        $extension = new FullExampleExtension();

        $this->assertSame('notur/full-extension', $extension->getId());
        $this->assertSame('Notur Full Example', $extension->getName());
        $this->assertSame('1.0.0', $extension->getVersion());
    }
}
