/**
 * Default CSS custom property definitions derived from the Pterodactyl theme.
 * These serve as a baseline when the panel's own properties are unavailable.
 */
export declare const DEFAULT_CSS_VARIABLES: Record<string, string>;
/**
 * Extract CSS variables from Pterodactyl's live DOM and Tailwind output.
 *
 * Strategy:
 * 1. Read any `--ptero-*` CSS custom properties from `:root` and map them.
 * 2. Probe computed styles on well-known panel elements as fallback.
 * 3. Return the merged result (only variables that were actually found).
 */
export declare function extractPterodactylVariables(): Record<string, string>;
/**
 * Apply CSS custom properties to the document root.
 */
export declare function applyCssVariables(variables: Record<string, string>): void;
/**
 * Remove CSS custom properties from the document root.
 */
export declare function removeCssVariables(variables: Record<string, string>): void;
/**
 * Get the current value of a CSS custom property.
 */
export declare function getCssVariable(name: string): string;
