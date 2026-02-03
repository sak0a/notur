export interface ExtensionConfig {
    id: string;
    name: string;
    version: string;
}

export interface CssIsolationConfig {
    mode: 'root-class';
    className?: string;
}

export interface SlotRenderContext {
    path: string;
    area: 'server' | 'dashboard' | 'account' | 'admin' | 'auth' | 'other';
    isServer: boolean;
    isDashboard: boolean;
    isAccount: boolean;
    isAdmin: boolean;
    isAuth: boolean;
    permissions: string[] | null;
}

export interface SlotRenderWhen {
    area?: SlotRenderContext['area'];
    areas?: Array<SlotRenderContext['area']>;
    path?: string | string[];
    pathStartsWith?: string | string[];
    pathIncludes?: string | string[];
    pathMatches?: string | RegExp;
    permission?: string | string[];
    server?: boolean;
    dashboard?: boolean;
    account?: boolean;
    admin?: boolean;
    auth?: boolean;
}

export type SlotRenderCondition =
    | boolean
    | SlotRenderWhen
    | ((context: SlotRenderContext) => boolean);

export interface SlotConfig {
    slot: string;
    component: React.ComponentType<any>;
    order?: number;
    priority?: number;
    label?: string;
    icon?: string;
    permission?: string;
    props?: Record<string, any>;
    when?: SlotRenderCondition;
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
    cssIsolation?: CssIsolationConfig | boolean;
    onInit?: () => void;
    onDestroy?: () => void;
}

export interface SlotRegistration {
    extensionId: string;
    slot: string;
    component: React.ComponentType<any>;
    order?: number;
    priority?: number;
    label?: string;
    icon?: string;
    permission?: string;
    scopeClass?: string;
    props?: Record<string, any>;
    when?: SlotRenderCondition;
}

export interface RouteRegistration {
    extensionId: string;
    area: 'server' | 'dashboard' | 'account';
    path: string;
    name: string;
    component: React.ComponentType<any>;
    icon?: string;
    permission?: string;
    scopeClass?: string;
}

export interface NoturApi {
    version: string;
    registry: {
        registerSlot: (registration: Omit<SlotRegistration, 'extensionId'> & { extensionId?: string }) => void;
        registerRoute: (area: RouteRegistration['area'], route: Omit<RouteRegistration, 'extensionId' | 'area'> & { extensionId?: string }) => void;
        registerExtension: (ext: { id: string; name: string; version: string; slots: SlotRegistration[]; routes: RouteRegistration[]; theme?: { variables: Record<string, string>; priority?: number }; cssIsolation?: CssIsolationConfig }) => void;
        registerDestroyCallback: (extensionId: string, callback: () => void) => void;
        unregisterExtension: (extensionId: string) => void;
        getSlot: (slotId: string) => SlotRegistration[];
        getRoutes: (area: RouteRegistration['area']) => RouteRegistration[];
        on: (event: string, callback: () => void) => () => void;
        emitEvent: (event: string, data?: unknown) => void;
        onEvent: (event: string, callback: (data?: unknown) => void) => () => void;
    };
    hooks: {
        useSlot: (slotId: string) => SlotRegistration[];
        useExtensionApi: (options: { extensionId: string; baseUrl?: string }) => {
            data: unknown;
            loading: boolean;
            error: string | null;
            get: (path: string) => Promise<unknown>;
            post: (path: string, body?: unknown) => Promise<unknown>;
            put: (path: string, body?: unknown) => Promise<unknown>;
            patch: (path: string, body?: unknown) => Promise<unknown>;
            delete: (path: string) => Promise<unknown>;
            request: (path: string, options?: RequestInit) => Promise<unknown>;
        };
        useExtensionState: <T extends Record<string, any>>(
            extensionId: string,
            initialState: T,
        ) => [T, (partial: Partial<T>) => void, () => void];
        useNoturTheme: () => Record<string, string>;
    };
    SLOT_IDS: Record<string, string>;
    unregisterExtension: (id: string) => void;
    extensions?: Array<{ id: string; bundle?: string; styles?: string; cssIsolation?: CssIsolationConfig }>;
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
