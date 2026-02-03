<?php

declare(strict_types=1);

namespace Notur\Features;

use Notur\Support\CapabilityMatcher;

final class FeatureRegistry
{
    /** @var ExtensionFeature[] */
    private array $features;

    /**
     * @param ExtensionFeature[] $features
     */
    public function __construct(array $features = [])
    {
        $this->features = $features;
    }

    public static function defaults(): self
    {
        return new self([
            new RoutesFeature(),
            new HealthChecksFeature(),
            new SchedulesFeature(),
        ]);
    }

    public function add(ExtensionFeature $feature): void
    {
        $this->features[] = $feature;
    }

    /**
     * @return ExtensionFeature[]
     */
    public function all(): array
    {
        return $this->features;
    }

    public function register(ExtensionContext $context): void
    {
        foreach ($this->features as $feature) {
            if ($this->isEnabledFor($feature, $context) && $feature->supports($context)) {
                $feature->register($context);
            }
        }
    }

    public function boot(ExtensionContext $context): void
    {
        foreach ($this->features as $feature) {
            if ($this->isEnabledFor($feature, $context) && $feature->supports($context)) {
                $feature->boot($context);
            }
        }
    }

    private function isEnabledFor(ExtensionFeature $feature, ExtensionContext $context): bool
    {
        $capabilityId = $feature->getCapabilityId();

        if ($capabilityId === null) {
            return true;
        }

        $manifest = $context->manifest;

        if (!$manifest->hasCapabilitiesDeclared()) {
            return $feature->isEnabledByDefault();
        }

        $capabilities = $manifest->getCapabilities();
        if (!isset($capabilities[$capabilityId])) {
            return false;
        }

        return CapabilityMatcher::matches((string) $capabilities[$capabilityId], $feature->getCapabilityVersion());
    }
}
