/**
 * The panel keeps its xterm instance in a React useMemo hook, not on window.
 * Discover only that instance, then use xterm's public options API. No React
 * state or canvas drawing methods are changed. CSS remains the fallback if a
 * different panel version does not expose the expected React ownership chain.
 */
export function createTerminalAdapter() {
    const originals = new Map<any, { element: HTMLElement; theme: any }>();
    const sync = () => {
        const element = document.querySelector<HTMLElement>('.xterm');
        const container = element?.parentElement;
        if (!container || !element) return;
        const key = Object.keys(container).find(name => /^__react(Fiber|InternalInstance)\$/.test(name));
        let fiber = key ? (container as any)[key] : null;
        for (let depth = 0; fiber && depth < 24; depth++, fiber = fiber.return) {
            let hook = fiber.memoizedState;
            for (let count = 0; hook && count < 80; count++, hook = hook.next) {
                const state = hook.memoizedState;
                const candidate = Array.isArray(state) ? state[0] : state;
                if (!candidate || candidate.element !== element || typeof candidate.write !== 'function' || !candidate.options) continue;
                if (!originals.has(candidate)) originals.set(candidate, { element, theme: candidate.options.theme });
                if (candidate.options.theme?.background !== '#09090b') {
                    candidate.options.theme = { ...candidate.options.theme, background: '#09090b', black: '#09090b' };
                }
            }
        }
        originals.forEach((value, instance) => { if (!value.element.isConnected) originals.delete(instance); });
    };
    const cleanup = () => {
        originals.forEach(({ element, theme }, instance) => {
            if (!element.isConnected) return;
            try { instance.options.theme = theme; } catch { /* The terminal may already be disposed. */ }
        });
        originals.clear();
    };
    return { sync, cleanup };
}
