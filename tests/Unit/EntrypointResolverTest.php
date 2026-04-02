<?php

declare(strict_types=1);

namespace Notur\Tests\Unit;

use Notur\Contracts\ExtensionInterface;
use Notur\ExtensionManifest;
use Notur\Support\EntrypointResolver;
use PHPUnit\Framework\TestCase;

class EntrypointResolverTest extends TestCase
{
    private EntrypointResolver $resolver;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->resolver = new EntrypointResolver();
        $this->tempDir = sys_get_temp_dir() . '/notur-entrypoint-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->tempDir);
    }

    public function test_resolves_explicit_entrypoint(): void
    {
        $manifest = ExtensionManifest::fromArray([
            'id' => 'acme/test',
            'name' => 'Test',
            'version' => '1.0.0',
            'entrypoint' => 'Acme\Test\TestExtension',
        ], $this->tempDir);

        $result = $this->resolver->resolve($manifest, $this->tempDir, []);
        $this->assertSame('Acme\Test\TestExtension', $result);
    }

    public function test_resolves_from_composer_json_extra(): void
    {
        file_put_contents($this->tempDir . '/composer.json', json_encode([
            'extra' => [
                'notur' => [
                    'entrypoint' => 'Acme\Composer\MyExtension',
                ],
            ],
        ]));

        $manifest = ExtensionManifest::fromArray([
            'id' => 'acme/test',
            'name' => 'Test',
            'version' => '1.0.0',
        ], $this->tempDir);

        $result = $this->resolver->resolve($manifest, $this->tempDir, []);
        $this->assertSame('Acme\Composer\MyExtension', $result);
    }

    public function test_returns_null_when_no_entrypoint_found(): void
    {
        $manifest = ExtensionManifest::fromArray([
            'id' => 'acme/test',
            'name' => 'Test',
            'version' => '1.0.0',
        ], $this->tempDir);

        $result = $this->resolver->resolve($manifest, $this->tempDir, []);
        $this->assertNull($result);
    }

    public function test_infers_namespace_from_id(): void
    {
        $manifest = ExtensionManifest::fromArray([
            'id' => 'acme/hello-world',
            'name' => 'Hello World',
            'version' => '1.0.0',
        ], $this->tempDir);

        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir, 0755, true);

        $classContent = <<<'PHP'
<?php

namespace Acme\HelloWorld;

use Notur\Contracts\ExtensionInterface;

class HelloWorldExtension implements ExtensionInterface
{
    public function getId(): string { return 'acme/hello-world'; }
    public function getName(): string { return 'Hello World'; }
    public function getVersion(): string { return '1.0.0'; }
    public function register(): void {}
    public function boot(): void {}
    public function getBasePath(): string { return ''; }
}
PHP;

        file_put_contents($srcDir . '/HelloWorldExtension.php', $classContent);

        $psr4 = ['Acme\HelloWorld\\' => 'src/'];
        $result = $this->resolver->resolve($manifest, $this->tempDir, $psr4);

        $this->assertSame('Acme\HelloWorld\HelloWorldExtension', $result);
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

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
