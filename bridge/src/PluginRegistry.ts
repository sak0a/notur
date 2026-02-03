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

export type SlotRenderCondition =
    | boolean
    | SlotRenderWhen
    | ((context: SlotRenderContext) => boolean);

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

export class PluginRegistry {
    private extensions: Map<string, ExtensionRegistration> = new Map();
    private slotRegistrations: Map<string, SlotRegistration[]> = new Map();
    private routeRegistrations: RouteRegistration[] = [];
    private themeRegistrations: ThemeRegistration[] = [];
    private destroyCallbacks: Map<string, () => void> = new Map();
    private listeners: Map<string, Array<() => void>> = new Map();
    private eventBusListeners: Map<string, Array<(data?: unknown) => void>> = new Map();

    /**
     * Register a component into a slot.
     */
    registerSlot(registration: Omit<SlotRegistration, 'extensionId'> & { extensionId?: string }): void {
        const reg: SlotRegistration = {
            extensionId: registration.extensionId || 'unknown',
            order: 0,
            priority: 0,
            ...registration,
        };

        const existing = this.slotRegistrations.get(reg.slot) || [];
        existing.push(reg);
        existing.sort((a, b) => {
            const priorityDiff = (b.priority ?? 0) - (a.priority ?? 0);
            if (priorityDiff !== 0) {
                return priorityDiff;
            }

            const orderDiff = (a.order ?? 0) - (b.order ?? 0);
            if (orderDiff !== 0) {
                return orderDiff;
            }

            return (a.extensionId || '').localeCompare(b.extensionId || '');
        });
        this.slotRegistrations.set(reg.slot, existing);

        this.emit('slot:' + reg.slot);
        this.emit('change');
    }

    /**
     * Register a route in an area.
     */
    registerRoute(
        area: RouteRegistration['area'],
        route: Omit<RouteRegistration, 'extensionId' | 'area'> & { extensionId?: string },
    ): void {
        const reg: RouteRegistration = {
            extensionId: route.extensionId || 'unknown',
            area,
            ...route,
        };

        this.routeRegistrations.push(reg);

        this.emit('route:' + area);
        this.emit('change');
    }

    /**
     * Register a complete extension.
     */
    registerExtension(ext: ExtensionRegistration): void {
        this.extensions.set(ext.id, ext);

        const scopeClass = this.resolveScopeClass(ext);

        for (const slot of ext.slots) {
            this.registerSlot({ ...slot, extensionId: ext.id, scopeClass });
        }

        for (const route of ext.routes) {
            this.registerRoute(route.area, { ...route, extensionId: ext.id, scopeClass });
        }

        if (ext.theme) {
            this.registerTheme({ ...ext.theme, extensionId: ext.id });
        }

        this.emit('extension:registered');
    }

    /**
     * Register an onDestroy callback for an extension.
     */
    registerDestroyCallback(extensionId: string, callback: () => void): void {
        this.destroyCallbacks.set(extensionId, callback);
    }

    /**
     * Unregister an extension, calling its destroy callback and removing
     * all associated slot, route, and theme registrations.
     */
    unregisterExtension(extensionId: string): void {
        // Call destroy callback if one exists
        const destroyCb = this.destroyCallbacks.get(extensionId);
        if (destroyCb) {
            try {
                destroyCb();
            } catch (e) {
                console.error(`[Notur] Extension ${extensionId} destroy error:`, e);
            }
        }

        // Remove from extensions map
        this.extensions.delete(extensionId);

        // Remove slot registrations for this extension
        for (const [slotId, registrations] of this.slotRegistrations.entries()) {
            const filtered = registrations.filter(r => r.extensionId !== extensionId);
            if (filtered.length !== registrations.length) {
                this.slotRegistrations.set(slotId, filtered);
                this.emit('slot:' + slotId);
            }
        }

        // Remove route registrations for this extension
        this.routeRegistrations = this.routeRegistrations.filter(r => r.extensionId !== extensionId);

        // Remove theme registrations for this extension
        this.themeRegistrations = this.themeRegistrations.filter(r => r.extensionId !== extensionId);

        // Remove the destroy callback
        this.destroyCallbacks.delete(extensionId);

        this.emit('extension:unregistered');
        this.emit('change');
    }

    /**
     * Get all registrations for a slot, sorted by order.
     */
    getSlot(slotId: SlotId): SlotRegistration[] {
        return this.slotRegistrations.get(slotId) || [];
    }

    /**
     * Get all route registrations for an area.
     */
    getRoutes(area: RouteRegistration['area']): RouteRegistration[] {
        return this.routeRegistrations.filter(r => r.area === area);
    }

    /**
     * Get all registered extensions.
     */
    getExtensions(): ExtensionRegistration[] {
        return Array.from(this.extensions.values());
    }

    /**
     * Register theme variable overrides from an extension.
     * Higher-priority registrations take precedence.
     */
    registerTheme(registration: ThemeRegistration): void {
        this.themeRegistrations.push({
            ...registration,
            priority: registration.priority ?? 0,
        });
        this.themeRegistrations.sort((a, b) => (a.priority ?? 0) - (b.priority ?? 0));

        this.emit('theme:changed');
        this.emit('change');
    }

    /**
     * Get the merged theme overrides from all registered themes.
     * Lower-priority registrations are applied first, so higher-priority
     * values win on conflict.
     */
    getThemeOverrides(): Record<string, string> {
        const merged: Record<string, string> = {};
        for (const reg of this.themeRegistrations) {
            Object.assign(merged, reg.variables);
        }
        return merged;
    }

    /**
     * Subscribe to registry changes.
     */
    on(event: string, callback: () => void): () => void {
        const listeners = this.listeners.get(event) || [];
        listeners.push(callback);
        this.listeners.set(event, listeners);

        return () => {
            const idx = listeners.indexOf(callback);
            if (idx >= 0) listeners.splice(idx, 1);
        };
    }

    /**
     * Emit an event on the inter-extension event bus.
     * Extensions use this to broadcast messages to other extensions.
     */
    emitEvent(event: string, data?: unknown): void {
        const listeners = this.eventBusListeners.get(event) || [];
        for (const listener of listeners) {
            try {
                listener(data);
            } catch (e) {
                console.error(`[Notur] Error in event bus listener for "${event}":`, e);
            }
        }
    }

    /**
     * Subscribe to an event on the inter-extension event bus.
     * Returns an unsubscribe function.
     */
    onEvent(event: string, callback: (data?: unknown) => void): () => void {
        const listeners = this.eventBusListeners.get(event) || [];
        listeners.push(callback);
        this.eventBusListeners.set(event, listeners);

        return () => {
            const idx = listeners.indexOf(callback);
            if (idx >= 0) listeners.splice(idx, 1);
        };
    }

    private emit(event: string): void {
        const listeners = this.listeners.get(event) || [];
        for (const listener of listeners) {
            try {
                listener();
            } catch (e) {
                console.error(`[Notur] Error in registry listener for "${event}":`, e);
            }
        }
    }

    private resolveScopeClass(ext: ExtensionRegistration): string | undefined {
        if (!ext.cssIsolation || ext.cssIsolation.mode !== 'root-class') {
            return undefined;
        }

        if (ext.cssIsolation.className) {
            return ext.cssIsolation.className;
        }

        const slug = ext.id.replace(/[^a-z0-9\-]/gi, '-');
        return `notur-ext--${slug}`;
    }
}
