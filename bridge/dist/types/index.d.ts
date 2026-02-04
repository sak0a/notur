import { PluginRegistry } from './PluginRegistry';
import { SlotRenderer, InlineSlot } from './SlotRenderer';
import { RouteRenderer, useRoutes } from './RouteRenderer';
import { SLOT_IDS, SLOT_DEFINITIONS } from './slots/SlotDefinitions';
import { useSlot } from './hooks/useSlot';
import { useExtensionApi } from './hooks/useExtensionApi';
import { useExtensionState } from './hooks/useExtensionState';
import { ThemeProvider, useNoturTheme } from './theme/ThemeProvider';
import { createDevTools } from './DevTools';
declare global {
    interface Window {
        __NOTUR__: {
            version: string;
            registry: PluginRegistry;
            slots: Record<string, any>;
            extensions: Array<{
                id: string;
                name?: string;
                version?: string;
                bundle?: string;
                styles?: string;
                cssIsolation?: {
                    mode: 'root-class';
                    className?: string;
                };
            }>;
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
            diagnostics: {
                errors: Array<{
                    extensionId: string;
                    message: string;
                    stack?: string;
                    componentStack?: string;
                    time: string;
                }>;
            };
        };
    }
}
export { PluginRegistry, SlotRenderer, InlineSlot, RouteRenderer, useSlot, useExtensionApi, useExtensionState, useRoutes, ThemeProvider, useNoturTheme, SLOT_IDS, SLOT_DEFINITIONS, };
