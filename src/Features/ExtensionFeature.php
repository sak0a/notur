<?php

declare(strict_types=1);

namespace Notur\Features;

interface ExtensionFeature
{
    /**
     * Capability identifier required to enable this feature.
     * Return null for features that are always enabled.
     */
    public function getCapabilityId(): ?string;

    /**
     * Major version supported by this feature (used for capability matching).
     */
    public function getCapabilityVersion(): int;

    /**
     * Whether this feature should be enabled when no capabilities are declared.
     */
    public function isEnabledByDefault(): bool;

    /**
     * Determine if this feature applies to the given extension.
     */
    public function supports(ExtensionContext $context): bool;

    /**
     * Register the feature after the extension's register() phase.
     * Runs before the extension's boot() phase.
     */
    public function register(ExtensionContext $context): void;

    /**
     * Finalize the feature after the extension's boot() phase.
     */
    public function boot(ExtensionContext $context): void;
}
