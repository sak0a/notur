<?php

declare(strict_types=1);

namespace Notur\Tests\Unit;

use Illuminate\Contracts\Foundation\Application;
use Notur\DependencyResolver;
use Notur\ExtensionManager;
use Notur\ExtensionManifest;
use Notur\Features\ExtensionContext;
use Notur\Features\ExtensionFeature;
use Notur\Features\FeatureRegistry;
use Notur\PermissionBroker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class FeatureRegistryTest extends TestCase
{
    public function test_enabled_by_default_when_capabilities_missing(): void
    {
        $feature = new TrackingFeature('routes', 1, true);
        $registry = new FeatureRegistry([$feature]);

        $context = $this->makeContext([
            'id' => 'acme/test',
            'name' => 'Test',
            'version' => '1.0.0',
            'entrypoint' => DummyExtension::class,
        ]);

        $registry->register($context);
        $registry->boot($context);

        $this->assertSame(1, $feature->registerCalls);
        $this->assertSame(1, $feature->bootCalls);
    }

    public function test_disabled_by_default_when_capabilities_missing(): void
    {
        $feature = new TrackingFeature('health', 1, false);
        $registry = new FeatureRegistry([$feature]);

        $context = $this->makeContext([
            'id' => 'acme/test',
            'name' => 'Test',
            'version' => '1.0.0',
            'entrypoint' => DummyExtension::class,
        ]);

        $registry->register($context);
        $registry->boot($context);

        $this->assertSame(0, $feature->registerCalls);
        $this->assertSame(0, $feature->bootCalls);
    }

    public function test_capabilities_gate_feature_by_version(): void
    {
        $featureV1 = new TrackingFeature('health', 1, false);
        $featureV2 = new TrackingFeature('health', 2, false);
        $featureOther = new TrackingFeature('schedules', 1, false);
        $registry = new FeatureRegistry([$featureV1, $featureV2, $featureOther]);

        $context = $this->makeContext([
            'id' => 'acme/test',
            'name' => 'Test',
            'version' => '1.0.0',
            'entrypoint' => DummyExtension::class,
            'capabilities' => [
                'health' => '^1',
            ],
        ]);

        $registry->register($context);

        $this->assertSame(1, $featureV1->registerCalls);
        $this->assertSame(0, $featureV2->registerCalls);
        $this->assertSame(0, $featureOther->registerCalls);
    }

    private function makeContext(array $manifestData): ExtensionContext
    {
        $manifestPath = tempnam(sys_get_temp_dir(), 'notur-manifest-');
        if ($manifestPath === false) {
            $this->fail('Failed to create temp manifest file.');
        }

        file_put_contents($manifestPath, Yaml::dump($manifestData, 4, 2));

        $manifest = new ExtensionManifest($manifestPath);

        $app = $this->createMock(Application::class);
        $resolver = new DependencyResolver();
        $broker = new PermissionBroker();
        $manager = new ExtensionManager($app, $resolver, $broker);

        return new ExtensionContext(
            id: $manifestData['id'],
            extension: new DummyExtension(),
            manifest: $manifest,
            path: '/tmp/notur-test',
            app: $app,
            manager: $manager,
        );
    }
}

class DummyExtension implements \Notur\Contracts\ExtensionInterface
{
    public function getId(): string { return 'acme/test'; }
    public function getName(): string { return 'Test'; }
    public function getVersion(): string { return '1.0.0'; }
    public function register(): void {}
    public function boot(): void {}
    public function getBasePath(): string { return '/tmp/notur-test'; }
}

class TrackingFeature implements ExtensionFeature
{
    public int $registerCalls = 0;
    public int $bootCalls = 0;

    public function __construct(
        private readonly string $capabilityId,
        private readonly int $capabilityVersion,
        private readonly bool $enabledByDefault,
    ) {}

    public function getCapabilityId(): ?string
    {
        return $this->capabilityId;
    }

    public function getCapabilityVersion(): int
    {
        return $this->capabilityVersion;
    }

    public function isEnabledByDefault(): bool
    {
        return $this->enabledByDefault;
    }

    public function supports(ExtensionContext $context): bool
    {
        return true;
    }

    public function register(ExtensionContext $context): void
    {
        $this->registerCalls++;
    }

    public function boot(ExtensionContext $context): void
    {
        $this->bootCalls++;
    }
}
