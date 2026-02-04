import { getNoturApi } from './types';
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
export function createExtension(definition) {
    const { config, slots = [], routes = [], onInit, onDestroy, cssIsolation } = definition;
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
        }
        catch (e) {
            console.error(`[Notur] Extension ${config.id} init error:`, e);
        }
    }
    console.log(`[Notur] Extension registered: ${resolvedConfig.id} v${resolvedConfig.version}`);
}
function resolveConfig(api, config) {
    var _a, _b, _c;
    const manifestEntry = (_a = api.extensions) === null || _a === void 0 ? void 0 : _a.find(ext => ext.id === config.id);
    const name = (_b = config.name) !== null && _b !== void 0 ? _b : manifestEntry === null || manifestEntry === void 0 ? void 0 : manifestEntry.name;
    const version = (_c = config.version) !== null && _c !== void 0 ? _c : manifestEntry === null || manifestEntry === void 0 ? void 0 : manifestEntry.version;
    if (!name || !version) {
        throw new Error(`[Notur] Extension ${config.id} is missing name/version. Provide them in createExtension() or in extension.yaml.`);
    }
    return {
        id: config.id,
        name,
        version,
    };
}
function resolveCssIsolation(api, extensionId, local) {
    var _a, _b;
    if (local === true) {
        return { mode: 'root-class' };
    }
    if (local && typeof local === 'object') {
        return local;
    }
    const manifestConfig = (_b = (_a = api.extensions) === null || _a === void 0 ? void 0 : _a.find(ext => ext.id === extensionId)) === null || _b === void 0 ? void 0 : _b.cssIsolation;
    if (manifestConfig && typeof manifestConfig === 'object') {
        if ('class' in manifestConfig && !manifestConfig.className) {
            return { ...manifestConfig, className: manifestConfig.class };
        }
        return manifestConfig;
    }
    return undefined;
}
//# sourceMappingURL=createExtension.js.map