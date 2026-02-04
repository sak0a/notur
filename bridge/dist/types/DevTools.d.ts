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
export declare function createDevTools(registry: PluginRegistry): DevToolsApi;
export {};
