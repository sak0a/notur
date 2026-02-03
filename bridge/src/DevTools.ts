import { PluginRegistry } from './PluginRegistry';

interface DevToolsApi {
    /** Print a summary of all registered extensions, slots, routes, and themes */
    (): void;
    /** Print all slot registrations */
    slots: () => void;
    /** Print all route registrations */
    routes: () => void;
    /** Print all registered extensions */
    extensions: () => void;
    /** Print current theme overrides */
    theme: () => void;
    /** Subscribe to all registry events and log them to console */
    events: () => () => void;
}

/**
 * Create a debug API bound to a registry instance.
 * Activated via `window.__NOTUR__.debug()` or individual sub-methods.
 */
export function createDevTools(registry: PluginRegistry): DevToolsApi {
    const debug = (() => {
        console.group('[Notur] Debug Summary');
        debug.extensions();
        debug.slots();
        debug.routes();
        debug.theme();
        console.groupEnd();
    }) as DevToolsApi;

    debug.extensions = () => {
        const exts = registry.getExtensions();
        console.group(`[Notur] Extensions (${exts.length})`);
        for (const ext of exts) {
            console.log(`  ${ext.id} v${ext.version} — ${ext.slots.length} slots, ${ext.routes.length} routes`);
        }
        if (exts.length === 0) console.log('  (none)');
        console.groupEnd();
    };

    debug.slots = () => {
        const notur = (window as any).__NOTUR__;
        const slotIds = notur?.SLOT_IDS;
        console.group('[Notur] Slots');
        if (slotIds) {
            for (const key of Object.keys(slotIds)) {
                const id = slotIds[key];
                const regs = registry.getSlot(id);
                if (regs.length > 0) {
                    console.group(`  ${id} (${regs.length} components)`);
                    for (const reg of regs) {
                        console.log(`    [${reg.order ?? 0}] ${reg.extensionId} — ${reg.component.displayName || reg.component.name || 'Anonymous'}`);
                    }
                    console.groupEnd();
                }
            }
        }
        console.groupEnd();
    };

    debug.routes = () => {
        const areas = ['server', 'dashboard', 'account'] as const;
        console.group('[Notur] Routes');
        for (const area of areas) {
            const routes = registry.getRoutes(area);
            if (routes.length > 0) {
                console.group(`  ${area} (${routes.length})`);
                for (const route of routes) {
                    console.log(`    ${route.path} — ${route.name} (${route.extensionId})`);
                }
                console.groupEnd();
            }
        }
        console.groupEnd();
    };

    debug.theme = () => {
        const overrides = registry.getThemeOverrides();
        const keys = Object.keys(overrides);
        console.group(`[Notur] Theme Overrides (${keys.length})`);
        for (const key of keys) {
            console.log(`  ${key}: ${overrides[key]}`);
        }
        if (keys.length === 0) console.log('  (none)');
        console.groupEnd();
    };

    debug.events = () => {
        const events = [
            'change',
            'extension:registered',
            'extension:unregistered',
            'theme:changed',
        ];

        const unsubscribers: Array<() => void> = [];

        for (const event of events) {
            unsubscribers.push(
                registry.on(event, () => {
                    console.log(`[Notur Event] ${event}`, new Date().toISOString());
                }),
            );
        }

        console.log('[Notur] Event logging enabled. Call the returned function to stop.');
        return () => {
            unsubscribers.forEach(unsub => unsub());
            console.log('[Notur] Event logging disabled.');
        };
    };

    return debug;
}
