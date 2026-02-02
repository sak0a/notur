<?php

declare(strict_types=1);

namespace Notur\Tests\Unit;

use Notur\ExtensionManifest;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class ManifestParserTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = sys_get_temp_dir() . '/notur-test-' . uniqid();
        mkdir($this->fixturesDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->fixturesDir);
    }

    public function test_parses_valid_manifest(): void
    {
        $yaml = <<<YAML
notur: "1.0"
id: "acme/test-extension"
name: "Test Extension"
version: "1.0.0"
description: "A test extension"
entrypoint: 'Acme\TestExtension\TestExtension'
license: "MIT"
authors:
  - name: "Test Author"

requires:
  notur: "^1.0"
  php: "^8.2"

dependencies:
  acme/other: "^1.0"

autoload:
  psr-4:
    'Acme\TestExtension\': "src/"

backend:
  routes:
    api-client: "src/routes/api.php"
  migrations: "database/migrations"
  permissions:
    - "test.view"
    - "test.edit"

frontend:
  bundle: "resources/frontend/dist/test.js"
  slots:
    dashboard.widgets:
      component: "TestWidget"
      order: 10
YAML;

        file_put_contents($this->fixturesDir . '/extension.yaml', $yaml);

        $manifest = ExtensionManifest::load($this->fixturesDir);

        $this->assertSame('acme/test-extension', $manifest->getId());
        $this->assertSame('Test Extension', $manifest->getName());
        $this->assertSame('1.0.0', $manifest->getVersion());
        $this->assertSame('A test extension', $manifest->getDescription());
        $this->assertSame('Acme\TestExtension\TestExtension', $manifest->getEntrypoint());
        $this->assertSame('MIT', $manifest->getLicense());
        $this->assertCount(1, $manifest->getAuthors());
        $this->assertSame(['acme/other' => '^1.0'], $manifest->getDependencies());
        $this->assertSame(['test.view', 'test.edit'], $manifest->getPermissions());
        $this->assertSame('resources/frontend/dist/test.js', $manifest->getFrontendBundle());
        $this->assertArrayHasKey('api-client', $manifest->getRoutes());
        $this->assertSame('database/migrations', $manifest->getMigrationsPath());
    }

    public function test_throws_on_missing_required_fields(): void
    {
        $yaml = <<<YAML
notur: "1.0"
name: "Missing ID"
version: "1.0.0"
YAML;

        file_put_contents($this->fixturesDir . '/extension.yaml', $yaml);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing required field: id');

        ExtensionManifest::load($this->fixturesDir);
    }

    public function test_throws_on_invalid_id_format(): void
    {
        $yaml = <<<YAML
id: "invalid_id"
name: "Bad ID"
version: "1.0.0"
entrypoint: 'Foo\Bar'
YAML;

        file_put_contents($this->fixturesDir . '/extension.yaml', $yaml);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid extension ID');

        ExtensionManifest::load($this->fixturesDir);
    }

    public function test_throws_on_missing_file(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No extension.yaml found');

        ExtensionManifest::load('/nonexistent/path');
    }

    public function test_loads_yml_extension(): void
    {
        $yaml = <<<YAML
id: "acme/yml-ext"
name: "YML Extension"
version: "1.0.0"
entrypoint: 'Acme\Yml\Ext'
YAML;

        file_put_contents($this->fixturesDir . '/extension.yml', $yaml);

        $manifest = ExtensionManifest::load($this->fixturesDir);
        $this->assertSame('acme/yml-ext', $manifest->getId());
    }

    public function test_get_returns_nested_values(): void
    {
        $yaml = <<<YAML
id: "acme/nested"
name: "Nested"
version: "1.0.0"
entrypoint: 'Acme\Nested'
backend:
  routes:
    api-client: "routes/api.php"
YAML;

        file_put_contents($this->fixturesDir . '/extension.yaml', $yaml);

        $manifest = ExtensionManifest::load($this->fixturesDir);
        $this->assertSame('routes/api.php', $manifest->get('backend.routes.api-client'));
        $this->assertNull($manifest->get('nonexistent.key'));
        $this->assertSame('default', $manifest->get('nonexistent', 'default'));
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) return;

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
