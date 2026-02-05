<?php

declare(strict_types=1);

namespace Notur\Tests\Unit\Events;

use Notur\Events\ExtensionDisabled;
use Notur\Events\ExtensionEnabled;
use Notur\Events\ExtensionInstalled;
use Notur\Events\ExtensionRemoved;
use Notur\Events\ExtensionUpdated;
use PHPUnit\Framework\TestCase;

class ExtensionEventsTest extends TestCase
{
    public function test_extension_installed_event_has_extension_id(): void
    {
        $event = new ExtensionInstalled('acme/test', '1.0.0');

        $this->assertSame('acme/test', $event->extensionId);
        $this->assertSame('1.0.0', $event->version);
    }

    public function test_extension_enabled_event_has_extension_id(): void
    {
        $event = new ExtensionEnabled('acme/test');

        $this->assertSame('acme/test', $event->extensionId);
    }

    public function test_extension_disabled_event_has_extension_id(): void
    {
        $event = new ExtensionDisabled('acme/test');

        $this->assertSame('acme/test', $event->extensionId);
    }

    public function test_extension_updated_event_has_versions(): void
    {
        $event = new ExtensionUpdated('acme/test', '1.0.0', '2.0.0');

        $this->assertSame('acme/test', $event->extensionId);
        $this->assertSame('1.0.0', $event->fromVersion);
        $this->assertSame('2.0.0', $event->toVersion);
    }

    public function test_extension_removed_event_has_extension_id(): void
    {
        $event = new ExtensionRemoved('acme/test');

        $this->assertSame('acme/test', $event->extensionId);
    }

    public function test_events_have_dispatchable_trait(): void
    {
        // Verify the Dispatchable trait is used by checking the dispatch method exists
        $this->assertTrue(method_exists(ExtensionInstalled::class, 'dispatch'));
        $this->assertTrue(method_exists(ExtensionEnabled::class, 'dispatch'));
        $this->assertTrue(method_exists(ExtensionDisabled::class, 'dispatch'));
        $this->assertTrue(method_exists(ExtensionUpdated::class, 'dispatch'));
        $this->assertTrue(method_exists(ExtensionRemoved::class, 'dispatch'));
    }
}
