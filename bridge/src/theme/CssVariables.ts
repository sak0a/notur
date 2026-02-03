/**
 * Default CSS custom property definitions derived from the Pterodactyl theme.
 * These serve as a baseline when the panel's own properties are unavailable.
 */
export const DEFAULT_CSS_VARIABLES: Record<string, string> = {
    '--notur-primary': '#0967d2',
    '--notur-primary-light': '#47a3f3',
    '--notur-primary-dark': '#03449e',
    '--notur-secondary': '#7c8b9a',
    '--notur-success': '#27ab83',
    '--notur-danger': '#e12d39',
    '--notur-warning': '#f7c948',
    '--notur-info': '#2bb0ed',
    '--notur-bg-primary': '#0b0d10',
    '--notur-bg-secondary': 'rgba(17, 19, 24, 0.68)',
    '--notur-bg-tertiary': 'rgba(25, 28, 35, 0.8)',
    '--notur-text-primary': '#f1f5f9',
    '--notur-text-secondary': '#cbd5e1',
    '--notur-text-muted': '#94a3b8',
    '--notur-border': 'rgba(148, 163, 184, 0.18)',
    '--notur-glass-bg': 'rgba(17, 19, 24, 0.55)',
    '--notur-glass-border': 'rgba(255, 255, 255, 0.12)',
    '--notur-glass-highlight': 'rgba(255, 255, 255, 0.06)',
    '--notur-glass-shadow': '0 16px 40px rgba(0, 0, 0, 0.55)',
    '--notur-glass-blur': '16px',
    '--notur-radius-sm': '6px',
    '--notur-radius-md': '12px',
    '--notur-radius-lg': '18px',
    '--notur-font-sans': '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
    '--notur-font-mono': '"Fira Code", "JetBrains Mono", monospace',
};

/**
 * Mapping from Pterodactyl's Tailwind-generated CSS custom properties
 * (or well-known class computed styles) to Notur variable names.
 *
 * Pterodactyl v1 uses a custom Tailwind palette under `neutral-*` / `gray-*`
 * and exposes colors via CSS custom properties in some themes. This map lets
 * us read whatever is available and translate it into the Notur namespace.
 */
const PTERODACTYL_VARIABLE_MAP: Record<string, string> = {
    '--ptero-primary': '--notur-primary',
    '--ptero-primary-light': '--notur-primary-light',
    '--ptero-primary-dark': '--notur-primary-dark',
    '--ptero-success': '--notur-success',
    '--ptero-danger': '--notur-danger',
    '--ptero-warning': '--notur-warning',
    '--ptero-info': '--notur-info',
};

/**
 * Selectors + CSS properties to sample from the live DOM when no CSS custom
 * properties are exposed. We read the computed style of well-known elements
 * and derive Notur variables from them.
 */
const COMPUTED_STYLE_PROBES: Array<{
    selector: string;
    cssProp: string;
    noturVar: string;
}> = [
    { selector: '#app', cssProp: 'background-color', noturVar: '--notur-bg-primary' },
    { selector: '#app', cssProp: 'color', noturVar: '--notur-text-primary' },
    { selector: 'a.text-neutral-200, a[class*="NavigationBar"]', cssProp: 'color', noturVar: '--notur-text-secondary' },
    { selector: 'input', cssProp: 'background-color', noturVar: '--notur-bg-secondary' },
    { selector: 'input', cssProp: 'border-color', noturVar: '--notur-border' },
    { selector: 'button.bg-primary-500, button[class*="primary"]', cssProp: 'background-color', noturVar: '--notur-primary' },
];

/**
 * Extract CSS variables from Pterodactyl's live DOM and Tailwind output.
 *
 * Strategy:
 * 1. Read any `--ptero-*` CSS custom properties from `:root` and map them.
 * 2. Probe computed styles on well-known panel elements as fallback.
 * 3. Return the merged result (only variables that were actually found).
 */
export function extractPterodactylVariables(): Record<string, string> {
    const extracted: Record<string, string> = {};
    const rootStyles = getComputedStyle(document.documentElement);

    // Strategy 1: Read mapped CSS custom properties
    for (const [pteroVar, noturVar] of Object.entries(PTERODACTYL_VARIABLE_MAP)) {
        const value = rootStyles.getPropertyValue(pteroVar).trim();
        if (value) {
            extracted[noturVar] = value;
        }
    }

    // Strategy 2: Probe computed styles of live DOM elements
    for (const probe of COMPUTED_STYLE_PROBES) {
        // Skip if we already have a value from strategy 1
        if (extracted[probe.noturVar]) continue;

        const el = document.querySelector(probe.selector);
        if (el) {
            const computed = getComputedStyle(el);
            const value = computed.getPropertyValue(probe.cssProp).trim();
            if (value && value !== 'rgba(0, 0, 0, 0)' && value !== 'transparent') {
                extracted[probe.noturVar] = value;
            }
        }
    }

    // Strategy 3: Scan all stylesheets for --tw-* or --ptero-* declarations
    try {
        for (const sheet of Array.from(document.styleSheets)) {
            try {
                for (const rule of Array.from(sheet.cssRules || [])) {
                    if (rule instanceof CSSStyleRule && rule.selectorText === ':root') {
                        for (let i = 0; i < rule.style.length; i++) {
                            const prop = rule.style[i];
                            if (prop.startsWith('--ptero-') && PTERODACTYL_VARIABLE_MAP[prop]) {
                                const noturVar = PTERODACTYL_VARIABLE_MAP[prop];
                                if (!extracted[noturVar]) {
                                    extracted[noturVar] = rule.style.getPropertyValue(prop).trim();
                                }
                            }
                        }
                    }
                }
            } catch {
                // Cross-origin stylesheet — skip silently
            }
        }
    } catch {
        // StyleSheet access not supported — skip
    }

    return extracted;
}

/**
 * Apply CSS custom properties to the document root.
 */
export function applyCssVariables(variables: Record<string, string>): void {
    const root = document.documentElement;
    for (const [key, value] of Object.entries(variables)) {
        root.style.setProperty(key, value);
    }
}

/**
 * Remove CSS custom properties from the document root.
 */
export function removeCssVariables(variables: Record<string, string>): void {
    const root = document.documentElement;
    for (const key of Object.keys(variables)) {
        root.style.removeProperty(key);
    }
}

/**
 * Get the current value of a CSS custom property.
 */
export function getCssVariable(name: string): string {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}
