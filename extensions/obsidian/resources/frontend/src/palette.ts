/** Translate the stock panel palette without depending on generated class names. */
const neutral = ['216,33%,97%', '214,15%,91%', '210,16%,82%', '211,13%,65%', '211,10%,53%', '211,12%,43%', '209,14%,37%', '209,18%,30%', '209,20%,25%', '210,24%,16%'];
const shades = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900];
const accent = ['#eff6ff', '#dbeafe', '#bfdbfe', '#93c5fd', '#60a5fa', '#3b82f6', '#2563eb', '#1d4ed8', '#1e40af', '#1e3a8a', '#ecfeff', '#cffafe', '#a5f3fc', '#67e8f9', '#22d3ee', '#06b6d4', '#0891b2', '#0e7490', '#155e75', '#164e63'];

function scopeSelectors(selector: string): string {
    let depth = 0;
    let quote = '';
    let escaped = false;
    let start = 0;
    const groups: string[] = [];
    for (let i = 0; i < selector.length; i++) {
        const character = selector[i];
        if (escaped) { escaped = false; continue; }
        if (character === '\\') { escaped = true; continue; }
        if (quote) { if (character === quote) quote = ''; continue; }
        if (character === '"' || character === "'") { quote = character; continue; }
        if (character === '(' || character === '[') depth++;
        if (character === ')' || character === ']') depth--;
        if (character === ',' && depth === 0) { groups.push(selector.slice(start, i)); start = i + 1; }
    }
    groups.push(selector.slice(start));
    return groups.map(group => `:root[data-obsidian] ${group.trim()}`).join(',');
}

export function createPaletteAdapter(): (sheets: StyleSheetList) => string {
    const probe = document.createElement('canvas').getContext('2d');
    if (!probe) return () => '';
    const normalize = (color: string) => {
        probe.fillStyle = '#010203';
        probe.fillStyle = color;
        return probe.fillStyle;
    };
    const colors = new Map<string, string>();
    neutral.forEach((color, i) => colors.set(normalize(`hsl(${color})`), `var(--ob-n${shades[i]})`));
    accent.forEach((color, i) => colors.set(normalize(color), i % 10 < 3 ? 'var(--ob-accent-ink)' : 'var(--ob-accent)'));
    colors.set('#131a20', 'var(--ob-base)');
    const cache = new WeakMap<CSSStyleSheet, { count: number; css: string }>();
    const colorPattern = /#[\da-f]{3,8}\b|(?:rgb|hsl)a?\((?:[^()]|\([^()]*\))*\)/gi;
    const translate = (color: string, surface = false, hover = false): string => {
        const direct = colors.get(normalize(color));
        const role = (mapped: string) => surface && /--ob-n(?:600|700|800|900)/.test(mapped) ?
            `var(--ob-panel${hover ? '-hover' : ''})` : mapped;
        if (direct) return role(direct);
        // Tailwind v3 emits hsl(... / var(--tw-bg-opacity)), including inside
        // styled-components. Resolve the palette independently of its opacity.
        const functional = color.match(/^(rgba?|hsla?)\((.*)\)$/i);
        if (!functional) return color;
        const [, kind, body] = functional;
        // Do not split the fallback comma inside var(--tw-bg-opacity, 1).
        const legacy = body.match(/^([^,]+),([^,]+),([^,]+),(.+)$/);
        const parts = body.includes('/') ? body.split('/') : legacy ?
            [legacy.slice(1, 4).join(','), legacy[4]] : [];
        if (parts.length !== 2) return color;
        const mapped = colors.get(normalize(`${kind.replace(/a$/, '')}(${parts[0].trim()})`));
        if (!mapped) return color;
        const opacity = parts[1].trim();
        const percentage = opacity.endsWith('%') ? opacity : `calc(${opacity} * 100%)`;
        return `color-mix(in srgb, ${role(mapped)} ${percentage}, transparent)`;
    };
    const visit = (rules: CSSRuleList): string => Array.from(rules).map(rule => {
        if (rule instanceof CSSStyleRule) {
            // Terminal/editor colors carry syntax meaning and remain deliberately dark.
            if (/xterm|CodeMirror|cm-|ace_|obsidian|ob-/i.test(rule.selectorText)) return '';
            const declarations: string[] = [];
            const background = rule.style.getPropertyValue('background-color') || rule.style.getPropertyValue('background');
            const backgroundColor = background.match(colorPattern)?.[0];
            const surface = !!backgroundColor && translate(backgroundColor, true).includes('--ob-panel');
            const semanticBackground = backgroundColor && translate(backgroundColor) === backgroundColor &&
                !['#ffffff', '#000000', 'rgba(0, 0, 0, 0)'].includes(normalize(backgroundColor));
            const properties = new Set([...Array.from(rule.style), 'background', 'border', 'border-color']);
            properties.forEach(property => {
                if (!/color|background|border|shadow|fill|stroke/.test(property) || property.startsWith('--')) return;
                const value = rule.style.getPropertyValue(property);
                if (!value) return;
                const mapped = property === 'color' && semanticBackground ? value :
                    value.replace(colorPattern, color => translate(color, property.startsWith('background'), rule.selectorText.includes(':hover')));
                // Mirror unchanged semantic colors too: otherwise the scoped primary
                // rule would outrank a later danger/success variant from the host.
                declarations.push(`${property}:${mapped}${rule.style.getPropertyPriority(property) ? ' !important' : ''};`);
            });
            if (surface) {
                declarations.push('border:1px solid var(--ob-border);border-radius:12px;box-shadow:inset 0 1px rgba(255,255,255,.04),var(--ob-shadow);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);');
            }
            // Expose destructive intent independently of a production class hash.
            if (backgroundColor) {
                const rgb = backgroundColor.match(/rgba?\(\s*(\d+)[, ]+\s*(\d+)[, ]+\s*(\d+)/i);
                const hex = backgroundColor.match(/^#([\da-f]{2})([\da-f]{2})([\da-f]{2})$/i);
                const channels = rgb ? rgb.slice(1).map(Number) : hex ? hex.slice(1).map(value => parseInt(value, 16)) : [];
                if (channels.length && channels[0] > channels[1] * 1.35 && channels[0] > channels[2] * 1.15) {
                    declarations.push('--ob-button-tone:var(--ob-danger);');
                }
            }
            if (!declarations.length) return '';
            // Keep pseudo-elements and each selector's original specificity intact.
            const selector = scopeSelectors(rule.selectorText);
            return `${selector}{${declarations.join('')}}`;
        }
        if (rule instanceof CSSMediaRule) return `@media ${rule.conditionText}{${visit(rule.cssRules)}}`;
        if (rule instanceof CSSSupportsRule) return `@supports ${rule.conditionText}{${visit(rule.cssRules)}}`;
        return '';
    }).join('\n');
    return sheets => Array.from(sheets).map(sheet => {
        if ((sheet.ownerNode as HTMLElement | null)?.hasAttribute('data-obsidian-style')) return '';
        try {
            const previous = cache.get(sheet);
            if (previous?.count === sheet.cssRules.length) return previous.css;
            const css = visit(sheet.cssRules);
            cache.set(sheet, { count: sheet.cssRules.length, css });
            return css;
        } catch { return ''; } // Cross-origin styles are inaccessible.
    }).join('\n');
}
