import * as React from 'react';
import * as ReactDOM from 'react-dom';
import { PluginRegistry } from './PluginRegistry';
import { SlotRenderer, InlineSlot } from './SlotRenderer';
import { RouteRenderer, useRoutes } from './RouteRenderer';
import { SLOT_IDS, SLOT_DEFINITIONS, SlotId } from './slots/SlotDefinitions';
import { useSlot } from './hooks/useSlot';
import { useExtensionApi } from './hooks/useExtensionApi';
import { useExtensionState } from './hooks/useExtensionState';
import { ThemeProvider, useNoturTheme } from './theme/ThemeProvider';
import { createDevTools } from './DevTools';
import {
    DEFAULT_CSS_VARIABLES,
    applyCssVariables,
    extractPterodactylVariables,
} from './theme/CssVariables';

declare global {
    interface Window {
        __NOTUR__: {
            version: string;
            registry: PluginRegistry;
            slots: Record<string, any>;
            extensions: Array<{ id: string; bundle?: string; styles?: string }>;
            routes: any[];
            unregisterExtension: (id: string) => void;
            emitEvent: (event: string, data?: unknown) => void;
            onEvent: (event: string, callback: (data?: unknown) => void) => () => void;
            SlotRenderer: typeof SlotRenderer;
            InlineSlot: typeof InlineSlot;
            RouteRenderer: typeof RouteRenderer;
            hooks: {
                useSlot: typeof useSlot;
                useExtensionApi: typeof useExtensionApi;
                useExtensionState: typeof useExtensionState;
                useNoturTheme: typeof useNoturTheme;
                useRoutes: typeof useRoutes;
            };
            ThemeProvider: typeof ThemeProvider;
            SLOT_IDS: typeof SLOT_IDS;
            SLOT_DEFINITIONS: typeof SLOT_DEFINITIONS;
            debug: ReturnType<typeof createDevTools>;
        };
    }
}

/**
 * Map of mounted slot React roots, keyed by slot ID.
 * Used to avoid double-mounting and to enable cleanup.
 */
const mountedSlots = new Map<string, { unmount: () => void }>();

/**
 * Mount a SlotRenderer for a single slot into its DOM container.
 * The container element is expected to have `id="notur-slot-{slotId}"`.
 *
 * If the container does not yet exist in the DOM, the mount is deferred
 * and retried via MutationObserver until the element appears.
 */
function mountSlot(slotId: SlotId, registry: PluginRegistry): void {
    if (mountedSlots.has(slotId)) return;

    const containerId = `notur-slot-${slotId}`;

    const doMount = (container: Element): void => {
        if (mountedSlots.has(slotId)) return;

        // Create a nested mount point so SlotRenderer's portal target
        // and the React root are separate elements
        const mountPoint = document.createElement('div');
        mountPoint.setAttribute('data-notur-renderer', slotId);
        container.appendChild(mountPoint);

        const element = React.createElement(SlotRenderer, { slotId, registry });

        ReactDOM.render(element, mountPoint);

        mountedSlots.set(slotId, {
            unmount: () => {
                ReactDOM.unmountComponentAtNode(mountPoint);
                mountPoint.remove();
            },
        });

        console.log(`[Notur] Slot renderer mounted: ${slotId}`);
    };

    // Try immediately
    const existing = document.getElementById(containerId);
    if (existing) {
        doMount(existing);
        return;
    }

    // Defer via MutationObserver — the container may be injected after init
    const observer = new MutationObserver((_mutations, obs) => {
        const el = document.getElementById(containerId);
        if (el) {
            obs.disconnect();
            doMount(el);
        }
    });

    observer.observe(document.body, { childList: true, subtree: true });

    // Safety timeout — stop observing after 30 s to avoid leaks
    setTimeout(() => observer.disconnect(), 30_000);
}

/**
 * Mount SlotRenderers for ALL defined slots.
 */
function mountAllSlots(registry: PluginRegistry): void {
    for (const def of SLOT_DEFINITIONS) {
        mountSlot(def.id, registry);
    }
}

/**
 * The ThemeProvider root that wraps nothing visually but keeps the
 * document's CSS custom properties in sync with the registry.
 */
let themeRootUnmount: (() => void) | null = null;

function mountThemeRoot(registry: PluginRegistry): void {
    if (themeRootUnmount) return;

    const mountPoint = document.createElement('div');
    mountPoint.id = 'notur-theme-root';
    mountPoint.style.display = 'none';
    document.body.appendChild(mountPoint);

    const element = React.createElement(
        ThemeProvider,
        { registry, children: null }, // No visible children — just syncs CSS vars
    );

    ReactDOM.render(element, mountPoint);

    themeRootUnmount = () => {
        ReactDOM.unmountComponentAtNode(mountPoint);
        mountPoint.remove();
    };

    console.log('[Notur] ThemeProvider root mounted');
}

/**
 * Initialize the Notur bridge runtime.
 * Sets up the plugin registry, mounts all slot renderers, wires the theme
 * system, and exposes the API on window.__NOTUR__.
 */
function init(): void {
    const existing = window.__NOTUR__ || {};

    const registry = new PluginRegistry();

    // Apply default CSS variables immediately, then layer panel extraction
    applyCssVariables(DEFAULT_CSS_VARIABLES);

    // Extract Pterodactyl's live theme variables and apply them on top
    try {
        const panelVars = extractPterodactylVariables();
        if (Object.keys(panelVars).length > 0) {
            applyCssVariables(panelVars);
            console.log(
                `[Notur] Extracted ${Object.keys(panelVars).length} theme variables from panel`,
            );
        }
    } catch (e) {
        console.warn('[Notur] Failed to extract panel theme variables:', e);
    }

    const debug = createDevTools(registry);

    // Expose the full Notur API
    window.__NOTUR__ = {
        ...existing,
        version: existing.version || '1.0.0',
        registry,
        routes: [],
        unregisterExtension: (id: string) => registry.unregisterExtension(id),
        emitEvent: (event: string, data?: unknown) => registry.emitEvent(event, data),
        onEvent: (event: string, callback: (data?: unknown) => void) => registry.onEvent(event, callback),
        SlotRenderer,
        InlineSlot,
        RouteRenderer,
        hooks: {
            useSlot,
            useExtensionApi,
            useExtensionState,
            useNoturTheme,
            useRoutes,
        },
        ThemeProvider,
        SLOT_IDS,
        SLOT_DEFINITIONS,
        debug,
    };

    console.log(`[Notur] Bridge runtime v${window.__NOTUR__.version} initialized`);

    // Process any slot registrations from server-rendered config
    if (existing.slots) {
        for (const [extensionId, slots] of Object.entries(existing.slots as Record<string, any>)) {
            for (const [slotId] of Object.entries(slots as Record<string, any>)) {
                // Metadata-only; actual component registration happens when
                // extension bundles call registry.registerSlot()
                console.log(`[Notur] Slot defined: ${slotId} by ${extensionId}`);
            }
        }
    }

    // Mount the ThemeProvider root (keeps CSS variables reactive)
    mountThemeRoot(registry);

    // Mount SlotRenderer instances for every defined slot
    mountAllSlots(registry);
}

// Auto-initialize when the script loads
if (typeof window !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}

export {
    PluginRegistry,
    SlotRenderer,
    InlineSlot,
    RouteRenderer,
    useSlot,
    useExtensionApi,
    useExtensionState,
    useRoutes,
    ThemeProvider,
    useNoturTheme,
    SLOT_IDS,
    SLOT_DEFINITIONS,
};
