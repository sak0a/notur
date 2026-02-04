import { SlotId } from './slots/SlotDefinitions';
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
export type SlotRenderCondition = boolean | SlotRenderWhen | ((context: SlotRenderContext) => boolean);
export interface SlotRegistration {
    extensionId: string;
    slot: SlotId;
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
export interface CssIsolation {
    mode: 'root-class';
    className?: string;
}
export interface ThemeRegistration {
    extensionId: string;
    /** CSS custom property overrides */
    variables: Record<string, string>;
    /** Priority — higher wins when multiple extensions register themes */
    priority?: number;
}
export interface ExtensionRegistration {
    id: string;
    name: string;
    version: string;
    slots: SlotRegistration[];
    routes: RouteRegistration[];
    theme?: Omit<ThemeRegistration, 'extensionId'>;
    cssIsolation?: CssIsolation;
}
export declare class PluginRegistry {
    private extensions;
    private slotRegistrations;
    private routeRegistrations;
    private themeRegistrations;
    private destroyCallbacks;
    private listeners;
    private eventBusListeners;
    /**
     * Register a component into a slot.
     */
    registerSlot(registration: Omit<SlotRegistration, 'extensionId'> & {
        extensionId?: string;
    }): void;
    /**
     * Register a route in an area.
     */
    registerRoute(area: RouteRegistration['area'], route: Omit<RouteRegistration, 'extensionId' | 'area'> & {
        extensionId?: string;
    }): void;
    /**
     * Register a complete extension.
     */
    registerExtension(ext: ExtensionRegistration): void;
    /**
     * Register an onDestroy callback for an extension.
     */
    registerDestroyCallback(extensionId: string, callback: () => void): void;
    /**
     * Unregister an extension, calling its destroy callback and removing
     * all associated slot, route, and theme registrations.
     */
    unregisterExtension(extensionId: string): void;
    /**
     * Get all registrations for a slot, sorted by order.
     */
    getSlot(slotId: SlotId): SlotRegistration[];
    /**
     * Get all route registrations for an area.
     */
    getRoutes(area: RouteRegistration['area']): RouteRegistration[];
    /**
     * Get all registered extensions.
     */
    getExtensions(): ExtensionRegistration[];
    /**
     * Register theme variable overrides from an extension.
     * Higher-priority registrations take precedence.
     */
    registerTheme(registration: ThemeRegistration): void;
    /**
     * Get the merged theme overrides from all registered themes.
     * Lower-priority registrations are applied first, so higher-priority
     * values win on conflict.
     */
    getThemeOverrides(): Record<string, string>;
    /**
     * Subscribe to registry changes.
     */
    on(event: string, callback: () => void): () => void;
    /**
     * Emit an event on the inter-extension event bus.
     * Extensions use this to broadcast messages to other extensions.
     */
    emitEvent(event: string, data?: unknown): void;
    /**
     * Subscribe to an event on the inter-extension event bus.
     * Returns an unsubscribe function.
     */
    onEvent(event: string, callback: (data?: unknown) => void): () => void;
    private emit;
    private resolveScopeClass;
}
