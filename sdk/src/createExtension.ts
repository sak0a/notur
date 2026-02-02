import { ExtensionDefinition, getNoturApi } from './types';

/**
 * Factory for registering a Notur extension.
 *
 * Usage:
 * ```ts
 * import { createExtension } from '@notur/sdk';
 *
 * createExtension({
 *   config: { id: 'acme/analytics', name: 'Analytics', version: '1.0.0' },
 *   slots: [
 *     { slot: 'dashboard.widgets', component: AnalyticsWidget, order: 10 },
 *   ],
 *   routes: [
 *     { area: 'server', path: '/analytics', name: 'Analytics', component: AnalyticsPage },
 *   ],
 * });
 * ```
 */
export function createExtension(definition: ExtensionDefinition): void {
    const { config, slots = [], routes = [], onInit, onDestroy } = definition;
    const api = getNoturApi();

    // Register slots
    for (const slot of slots) {
        api.registry.registerSlot({
            ...slot,
            extensionId: config.id,
        });
    }

    // Register routes
    for (const route of routes) {
        api.registry.registerRoute(route.area, {
            ...route,
            extensionId: config.id,
        });
    }

    // Register the extension
    api.registry.registerExtension({
        id: config.id,
        name: config.name,
        version: config.version,
        slots: slots.map(s => ({ ...s, extensionId: config.id })),
        routes: routes.map(r => ({ ...r, extensionId: config.id })),
    });

    // Run init callback
    if (onInit) {
        try {
            onInit();
        } catch (e) {
            console.error(`[Notur] Extension ${config.id} init error:`, e);
        }
    }

    console.log(`[Notur] Extension registered: ${config.id} v${config.version}`);
}
