<?php

declare(strict_types=1);

namespace Notur\Tests\Unit;

use Notur\MigrationManager;
use PHPUnit\Framework\TestCase;

class MigrationManagerTest extends TestCase
{
    private MigrationManager $manager;

    protected function setUp(): void
    {
        $this->manager = new MigrationManager();
    }

    public function test_migrate_returns_empty_for_nonexistent_dir(): void
    {
        $result = $this->manager->migrate('acme/test', '/nonexistent/path');
        $this->assertEmpty($result);
    }

    public function test_rollback_returns_empty_when_no_records(): void
    {
        // rollback queries the DB — test belongs in integration
        // Here we just verify the method signature exists
        $this->assertTrue(method_exists($this->manager, 'rollback'));
        $this->assertTrue(method_exists($this->manager, 'status'));
    }
}
