export interface ExtensionConfig {
    id: string;
    name: string;
    version: string;
}

export interface SlotConfig {
    slot: string;
    component: React.ComponentType<any>;
    order?: number;
    label?: string;
    icon?: string;
    permission?: string;
}

export interface RouteConfig {
    area: 'server' | 'dashboard' | 'account';
    path: string;
    name: string;
    component: React.ComponentType<any>;
    icon?: string;
    permission?: string;
}

export interface ExtensionDefinition {
    config: ExtensionConfig;
    slots?: SlotConfig[];
    routes?: RouteConfig[];
    onInit?: () => void;
    onDestroy?: () => void;
}

export interface NoturApi {
    version: string;
    registry: {
        registerSlot: (registration: any) => void;
        registerRoute: (area: string, route: any) => void;
        registerExtension: (ext: any) => void;
        getSlot: (slotId: string) => any[];
        getRoutes: (area: string) => any[];
        on: (event: string, callback: () => void) => () => void;
    };
    hooks: {
        useSlot: (slotId: string) => any[];
        useExtensionApi: (options: { extensionId: string }) => any;
        useExtensionState: <T extends Record<string, any>>(
            extensionId: string,
            initialState: T,
        ) => [T, (partial: Partial<T>) => void];
        useNoturTheme: () => any;
    };
    SLOT_IDS: Record<string, string>;
}

/**
 * Get the Notur API from the window global.
 */
export function getNoturApi(): NoturApi {
    const api = (window as any).__NOTUR__;
    if (!api) {
        throw new Error('[Notur SDK] Bridge runtime not found. Ensure bridge.js is loaded first.');
    }
    return api;
}
