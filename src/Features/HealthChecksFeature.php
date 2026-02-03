<?php

declare(strict_types=1);

namespace Notur\Features;

use Notur\Contracts\HasHealthChecks;

final class HealthChecksFeature implements ExtensionFeature
{
    public function getCapabilityId(): ?string
    {
        return 'health';
    }

    public function getCapabilityVersion(): int
    {
        return 1;
    }

    public function isEnabledByDefault(): bool
    {
        return false;
    }

    public function supports(ExtensionContext $context): bool
    {
        return $context->extension instanceof HasHealthChecks;
    }

    public function register(ExtensionContext $context): void
    {
        if ($context->extension instanceof HasHealthChecks) {
            $context->manager->registerHealthCheckProvider($context->id, $context->extension);
        }
    }

    public function boot(ExtensionContext $context): void
    {
        // No-op for now.
    }
}
