import { ExtensionDefinition, SimpleExtensionDefinition, getNoturApi } from './types';

/**
 * Factory for registering a Notur extension.
 *
 * Supports two calling conventions:
 *
 * **Simplified** (recommended) — `id` at the top level, name/version auto-resolved from manifest:
 * ```ts
 * createExtension({
 *     id: 'acme/analytics',
 *     slots: [
 *         { slot: 'dashboard.widgets', component: AnalyticsWidget, order: 10 },
 *     ],
 * });
 * ```
 *
 * **Full** — explicit config object (backward compatible):
 * ```ts
 * createExtension({
 *     config: { id: 'acme/analytics', name: 'Analytics', version: '1.0.0' },
 *     slots: [
 *         { slot: 'dashboard.widgets', component: AnalyticsWidget, order: 10 },
 *     ],
 * });
 * ```
 */
export function createExtension(definition: ExtensionDefinition): void;
export function createExtension(definition: SimpleExtensionDefinition): void;
export function createExtension(definition: ExtensionDefinition | SimpleExtensionDefinition): void {
    // Normalize: if 'id' is at top level (simplified form), wrap in config
    const normalized: ExtensionDefinition = isSimpleDefinition(definition)
        ? { ...definition, config: { id: definition.id } }
        : definition;

    const { config, slots = [], routes = [], onInit, onDestroy, cssIsolation } = normalized;
    const api = getNoturApi();

    const resolvedConfig = resolveConfig(api, config);

    const resolvedCssIsolation = resolveCssIsolation(api, config.id, cssIsolation);

    // Register the extension
    api.registry.registerExtension({
        id: resolvedConfig.id,
        name: resolvedConfig.name,
        version: resolvedConfig.version,
        slots: slots.map(s => ({ ...s, extensionId: config.id })),
        routes: routes.map(r => ({ ...r, extensionId: config.id })),
        cssIsolation: resolvedCssIsolation,
    });

    // Register destroy callback
    if (onDestroy) {
        api.registry.registerDestroyCallback(config.id, onDestroy);
    }

    // Run init callback
    if (onInit) {
        try {
            onInit();
        } catch (e) {
            console.error(`[Notur] Extension ${config.id} init error:`, e);
        }
    }

    console.log(`[Notur] Extension registered: ${resolvedConfig.id} v${resolvedConfig.version}`);
}

/**
 * Check if the definition uses the simplified form (id at top level).
 */
function isSimpleDefinition(
    definition: ExtensionDefinition | SimpleExtensionDefinition,
): definition is SimpleExtensionDefinition {
    return 'id' in definition && !('config' in definition);
}

function resolveConfig(api: ReturnType<typeof getNoturApi>, config: ExtensionDefinition['config']) {
    const manifestEntry = api.extensions?.find(ext => ext.id === config.id);
    const name = config.name ?? manifestEntry?.name;
    const version = config.version ?? manifestEntry?.version;

    if (!name || !version) {
        throw new Error(
            `[Notur] Extension ${config.id} is missing name/version. Provide them in createExtension() or in extension.yaml.`,
        );
    }

    return {
        id: config.id,
        name,
        version,
    };
}

function resolveCssIsolation(
    api: ReturnType<typeof getNoturApi>,
    extensionId: string,
    local?: ExtensionDefinition['cssIsolation'],
) {
    if (local === true) {
        return { mode: 'root-class' as const };
    }

    if (local && typeof local === 'object') {
        return local;
    }

    const manifestConfig = api.extensions?.find(ext => ext.id === extensionId)?.cssIsolation;
    if (manifestConfig && typeof manifestConfig === 'object') {
        if ('class' in (manifestConfig as any) && !(manifestConfig as any).className) {
            return { ...manifestConfig, className: (manifestConfig as any).class };
        }
        return manifestConfig;
    }

    return undefined;
}
