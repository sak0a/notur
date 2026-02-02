import * as React from 'react';
import {
    DEFAULT_CSS_VARIABLES,
    applyCssVariables,
    extractPterodactylVariables,
} from './CssVariables';
import { PluginRegistry } from '../PluginRegistry';

export interface NoturTheme {
    /** The fully resolved CSS variable map (defaults + panel + extension overrides). */
    variables: Record<string, string>;
    /** The subset contributed by extension theme registrations. */
    extensionOverrides: Record<string, string>;
}

const ThemeContext = React.createContext<NoturTheme>({
    variables: DEFAULT_CSS_VARIABLES,
    extensionOverrides: {},
});

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
export function ThemeProvider({
    overrides,
    registry,
    children,
}: ThemeProviderProps): React.ReactElement {
    // Extract Pterodactyl's live styles once on mount
    const [panelVars, setPanelVars] = React.useState<Record<string, string>>({});

    React.useEffect(() => {
        // Defer extraction so the DOM has settled after initial render
        const id = requestAnimationFrame(() => {
            setPanelVars(extractPterodactylVariables());
        });
        return () => cancelAnimationFrame(id);
    }, []);

    // Track extension theme registrations reactively
    const [registryOverrides, setRegistryOverrides] = React.useState<Record<string, string>>(
        registry ? registry.getThemeOverrides() : {},
    );

    React.useEffect(() => {
        if (!registry) return;

        // Sync immediately
        setRegistryOverrides(registry.getThemeOverrides());

        return registry.on('theme:changed', () => {
            setRegistryOverrides(registry.getThemeOverrides());
        });
    }, [registry]);

    // Merge all layers: defaults < panel < extensions < inline
    const variables = React.useMemo(() => {
        return {
            ...DEFAULT_CSS_VARIABLES,
            ...panelVars,
            ...registryOverrides,
            ...overrides,
        };
    }, [panelVars, registryOverrides, overrides]);

    // Apply to the document root whenever the merged set changes
    React.useEffect(() => {
        applyCssVariables(variables);
    }, [variables]);

    const theme: NoturTheme = React.useMemo(
        () => ({ variables, extensionOverrides: registryOverrides }),
        [variables, registryOverrides],
    );

    return React.createElement(ThemeContext.Provider, { value: theme }, children);
}

/**
 * Hook to access the current Notur theme.
 *
 * Returns the fully resolved variable map and the extension-contributed
 * overrides separately, so components can distinguish between base theme
 * values and extension customisations.
 */
export function useNoturTheme(): NoturTheme {
    return React.useContext(ThemeContext);
}
