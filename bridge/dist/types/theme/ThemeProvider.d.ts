import * as React from 'react';
import { PluginRegistry } from '../PluginRegistry';
export interface NoturTheme {
    /** The fully resolved CSS variable map (defaults + panel + extension overrides). */
    variables: Record<string, string>;
    /** The subset contributed by extension theme registrations. */
    extensionOverrides: Record<string, string>;
}
interface ThemeProviderProps {
    /** Inline overrides passed directly by the caller. */
    overrides?: Record<string, string>;
    /** If provided, the ThemeProvider subscribes to theme registrations. */
    registry?: PluginRegistry;
    children: React.ReactNode;
}
/**
 * ThemeProvider wraps extension components and applies CSS custom properties.
 *
 * Resolution order (later layers win):
 * 1. Notur defaults (`DEFAULT_CSS_VARIABLES`)
 * 2. Variables extracted from Pterodactyl's live DOM / Tailwind output
 * 3. Extension theme registrations from the PluginRegistry (sorted by priority)
 * 4. Inline `overrides` prop
 *
 * When a `registry` prop is supplied the provider subscribes to
 * `theme:changed` events so new extension themes are picked up reactively.
 */
export declare function ThemeProvider({ overrides, registry, children, }: ThemeProviderProps): React.ReactElement;
/**
 * Hook to access the current Notur theme.
 *
 * Returns the fully resolved variable map and the extension-contributed
 * overrides separately, so components can distinguish between base theme
 * values and extension customisations.
 */
export declare function useNoturTheme(): NoturTheme;
export {};
