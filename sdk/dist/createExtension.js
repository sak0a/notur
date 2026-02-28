import { getNoturApi } from './types';
export function createExtension(definition) {
    // Normalize: if 'id' is at top level (simplified form), wrap in config
    const normalized = isSimpleDefinition(definition)
        ? { ...definition, config: { id: definition.id } }
        : definition;
    const { config, slots = [], routes = [], onInit, onDestroy, cssIsolation } = normalized;
    const api = getNoturApi();
    warnOnMisconfiguration(config.id, slots, routes);
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
/**
 * Check if the definition uses the simplified form (id at top level).
 */
function isSimpleDefinition(definition) {
    return 'id' in definition && !('config' in definition);
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
function warnOnMisconfiguration(extensionId, slots, routes) {
    if (!/^[a-z0-9-]+\/[a-z0-9-]+$/.test(extensionId)) {
        console.warn(`[Notur SDK] Extension id "${extensionId}" does not match vendor/name format (lowercase, hyphen-safe).`);
    }
    const normalizedSlots = slots !== null && slots !== void 0 ? slots : [];
    const normalizedRoutes = routes !== null && routes !== void 0 ? routes : [];
    const routeKeys = new Set();
    for (const slot of normalizedSlots) {
        if (!slot.slot || typeof slot.slot !== 'string') {
            console.warn(`[Notur SDK] Extension ${extensionId} has a slot registration without a valid slot id.`);
        }
        if (typeof slot.component !== 'function') {
            console.warn(`[Notur SDK] Extension ${extensionId} slot "${slot.slot}" has a non-component value. Expected a React component.`);
        }
    }
    for (const route of normalizedRoutes) {
        if (!route.path.startsWith('/')) {
            console.warn(`[Notur SDK] Extension ${extensionId} route "${route.name}" should start with "/". Received "${route.path}".`);
        }
        if (typeof route.component !== 'function') {
            console.warn(`[Notur SDK] Extension ${extensionId} route "${route.name}" has a non-component value. Expected a React component.`);
        }
        if (route.permission && !route.permission.includes('.')) {
            console.warn(`[Notur SDK] Extension ${extensionId} route "${route.name}" uses permission "${route.permission}" without namespace (recommended: vendor.action).`);
        }
        const routeKey = `${route.area}:${route.path}`;
        if (routeKeys.has(routeKey)) {
            console.warn(`[Notur SDK] Extension ${extensionId} registers duplicate route path "${route.path}" in area "${route.area}".`);
        }
        else {
            routeKeys.add(routeKey);
        }
    }
}
//# sourceMappingURL=createExtension.js.map