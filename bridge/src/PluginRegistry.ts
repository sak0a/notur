import { SlotId } from './slots/SlotDefinitions';

export interface SlotRegistration {
    extensionId: string;
    slot: SlotId;
    component: React.ComponentType<any>;
    order?: number;
    label?: string;
    icon?: string;
    permission?: string;
}

export interface RouteRegistration {
    extensionId: string;
    area: 'server' | 'dashboard' | 'account';
    path: string;
    name: string;
    component: React.ComponentType<any>;
    icon?: string;
    permission?: string;
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
}

export class PluginRegistry {
    private extensions: Map<string, ExtensionRegistration> = new Map();
    private slotRegistrations: Map<string, SlotRegistration[]> = new Map();
    private routeRegistrations: RouteRegistration[] = [];
    private themeRegistrations: ThemeRegistration[] = [];
    private listeners: Map<string, Array<() => void>> = new Map();

    /**
     * Register a component into a slot.
     */
    registerSlot(registration: Omit<SlotRegistration, 'extensionId'> & { extensionId?: string }): void {
        const reg: SlotRegistration = {
            extensionId: registration.extensionId || 'unknown',
            order: 0,
            ...registration,
        };

        const existing = this.slotRegistrations.get(reg.slot) || [];
        existing.push(reg);
        existing.sort((a, b) => (a.order ?? 0) - (b.order ?? 0));
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

        for (const slot of ext.slots) {
            this.registerSlot({ ...slot, extensionId: ext.id });
        }

        for (const route of ext.routes) {
            this.registerRoute(route.area, { ...route, extensionId: ext.id });
        }

        if (ext.theme) {
            this.registerTheme({ ...ext.theme, extensionId: ext.id });
        }

        this.emit('extension:registered');
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
}
