import css from './theme.css';
import { createPaletteAdapter } from './palette';

const STORAGE_KEY = 'notur.obsidian.appearance';

export function mountTheme(): () => void {
    if (document.querySelector('[data-obsidian-style]')) return () => {};
    // The Laravel admin uses a different layout; this extension targets the React client.
    if (/^\/admin(?:\/|$)/.test(location.pathname)) return () => {};
    const root = document.documentElement;
    const originalMode = root.getAttribute('data-obsidian');
    let mode = 'dark';
    try { if (localStorage.getItem(STORAGE_KEY) === 'light') mode = 'light'; } catch { /* Private browsing. */ }
    root.setAttribute('data-obsidian', mode);
    const adapted = document.createElement('style');
    const theme = document.createElement('style');
    adapted.setAttribute('data-obsidian-style', 'palette');
    theme.setAttribute('data-obsidian-style', 'theme');
    theme.textContent = css;
    document.head.append(adapted, theme);

    const controls = document.createElement('div');
    controls.className = 'ob-controls';
    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'ob-appearance';
    const updateToggle = () => {
        toggle.textContent = mode === 'dark' ? '◐  Light appearance' : '◑  Dark appearance';
        toggle.setAttribute('aria-label', `Switch to ${mode === 'dark' ? 'light' : 'dark'} mode`);
    };
    updateToggle();
    toggle.onclick = () => {
        mode = mode === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-obsidian', mode);
        try { localStorage.setItem(STORAGE_KEY, mode); } catch { /* Keep the session preference. */ }
        updateToggle();
    };
    controls.append(toggle);
    const menu = document.createElement('button');
    menu.type = 'button';
    menu.className = 'ob-menu';
    menu.textContent = '☰  Menu';
    menu.setAttribute('aria-expanded', 'false');
    const backdrop = document.createElement('button');
    backdrop.type = 'button';
    backdrop.className = 'ob-backdrop';
    backdrop.setAttribute('aria-label', 'Close navigation');
    backdrop.tabIndex = -1;
    const skip = document.createElement('a');
    skip.className = 'ob-skip';
    skip.href = '#ob-main';
    skip.textContent = 'Skip to content';
    document.body.append(controls, menu, backdrop);
    document.body.prepend(skip);

    const changes = new Map<HTMLElement, Map<string, string | null>>();
    const set = (node: HTMLElement, name: string, value: string) => {
        if (node.getAttribute(name) === value) return;
        if (!changes.has(node)) changes.set(node, new Map());
        if (!changes.get(node)!.has(name)) changes.get(node)!.set(name, node.getAttribute(name));
        node.setAttribute(name, value);
    };
    let nav: HTMLElement | null = null;
    let subnav: HTMLElement | null = null;
    let observedHeader: HTMLElement | null = null;
    const measureNavigation = () => {
        if (!observedHeader) return;
        root.style.setProperty('--ob-subnav-top', `${Math.ceil(observedHeader.getBoundingClientRect().bottom + 14)}px`);
    };
    const resizeObserver = new ResizeObserver(measureNavigation);
    let open = false;
    const close = (restoreFocus = false) => {
        open = false;
        root.removeAttribute('data-ob-open');
        menu.setAttribute('aria-expanded', 'false');
        if (restoreFocus) menu.focus();
    };
    menu.onclick = () => {
        if (open) { close(true); return; }
        open = true;
        root.setAttribute('data-ob-open', '');
        menu.setAttribute('aria-expanded', 'true');
        nav?.querySelector<HTMLElement>('a, button, [tabindex="0"]')?.focus();
    };
    backdrop.onclick = () => close(true);
    const keyboard = (event: KeyboardEvent) => {
        const target = event.target as HTMLElement;
        if (target.matches('[data-ob-search]') && ['Enter', ' '].includes(event.key)) {
            event.preventDefault(); target.click();
        }
        if (!open) return;
        if (event.key === 'Escape') { close(true); return; }
        if (event.key !== 'Tab') return;
        const candidates = [nav, subnav, controls, menu].flatMap(container => container ?
            (container === menu ? [menu] : Array.from(container.querySelectorAll<HTMLElement>('a[href], button:not(:disabled), [tabindex="0"]'))) : [])
            .filter(element => element.getClientRects().length > 0);
        const index = candidates.indexOf(document.activeElement as HTMLElement);
        if (event.shiftKey && index <= 0) { event.preventDefault(); candidates[candidates.length - 1]?.focus(); }
        else if (!event.shiftKey && (index < 0 || index === candidates.length - 1)) { event.preventDefault(); candidates[0]?.focus(); }
    };
    const clicked = (event: MouseEvent) => {
        if (open && (event.target as HTMLElement).closest('[data-ob-nav] a, [data-ob-subnav] a')) close(true);
    };
    const storage = (event: StorageEvent) => {
        if (event.key !== STORAGE_KEY) return;
        mode = event.newValue === 'light' ? 'light' : 'dark';
        root.setAttribute('data-obsidian', mode); updateToggle();
    };
    const media = window.matchMedia('(min-width: 901px)');
    const resized = () => { if (media.matches) close(); };
    media.addEventListener('change', resized);
    document.addEventListener('keydown', keyboard);
    document.addEventListener('click', clicked);
    window.addEventListener('storage', storage);

    const adapt = createPaletteAdapter();
    let frame = 0;
    const sync = () => {
        frame = 0;
        nav = document.getElementById('notur-slot-navbar')?.parentElement?.parentElement?.parentElement ?? null;
        const slot = document.getElementById('notur-slot-server.subnav') ?? document.getElementById('notur-slot-account.subnav');
        subnav = slot?.parentElement ?? null;
        root.toggleAttribute('data-ob-shell', !!nav);
        if (nav) {
            set(nav, 'data-ob-nav', ''); set(nav, 'role', 'navigation'); set(nav, 'aria-label', 'Main navigation');
            nav.querySelectorAll<HTMLElement>('a, button, .navigation-link').forEach(link => {
                if (link.closest('#logo, [id^="notur-slot-"]')) return;
                const href = link.getAttribute('href');
                const label = href === '/' ? 'Servers' : href === '/account' ? 'Account' : href === '/admin' ? 'Administration' :
                    link.matches('.navigation-link') ? 'Search servers' : link.tagName === 'BUTTON' ? 'Sign out' : '';
                if (label && !link.textContent?.trim()) {
                    set(link, 'data-ob-label', label); set(link, 'aria-label', label);
                }
                if (link.matches('.navigation-link')) {
                    set(link, 'role', 'button'); set(link, 'tabindex', '0'); set(link, 'data-ob-search', '');
                }
            });
            const header = nav.lastElementChild as HTMLElement | null;
            if (header !== observedHeader) {
                resizeObserver.disconnect(); observedHeader = header;
                if (header) resizeObserver.observe(header);
            }
            measureNavigation();
        } else close();
        if (subnav) {
            set(subnav, 'data-ob-subnav', ''); set(subnav, 'role', 'navigation'); set(subnav, 'aria-label', 'Page navigation');
            if (subnav.parentElement) set(subnav.parentElement, 'data-ob-subnav-shell', '');
        }
        const content = document.querySelector<HTMLElement>('[class*="ContentContainer"]');
        if (content) { set(content, 'id', 'ob-main'); set(content, 'tabindex', '-1'); }
        skip.hidden = !content;
        const mapped = adapt(document.styleSheets);
        if (adapted.textContent !== mapped) adapted.textContent = mapped;
        // Forget unmounted nodes instead of retaining every page visited in this session.
        changes.forEach((_, node) => { if (!node.isConnected) changes.delete(node); });
    };
    const schedule = () => { if (!frame) frame = requestAnimationFrame(sync); };
    const observer = new MutationObserver(records => {
        if (records.some(record => !(record.target as Element).closest?.('[data-obsidian-style], .ob-controls'))) schedule();
    });
    observer.observe(document.body, { childList: true, subtree: true });
    observer.observe(document.head, { childList: true, subtree: true, characterData: true });
    document.addEventListener('load', schedule, true);
    sync();
    return () => {
        observer.disconnect(); cancelAnimationFrame(frame);
        resizeObserver.disconnect(); root.style.removeProperty('--ob-subnav-top');
        document.removeEventListener('load', schedule, true);
        document.removeEventListener('keydown', keyboard);
        document.removeEventListener('click', clicked);
        window.removeEventListener('storage', storage);
        media.removeEventListener('change', resized);
        changes.forEach((attributes, node) => attributes.forEach((value, name) => value === null ? node.removeAttribute(name) : node.setAttribute(name, value)));
        [adapted, theme, controls, menu, backdrop, skip].forEach(node => node.remove());
        ['data-ob-shell', 'data-ob-open'].forEach(name => root.removeAttribute(name));
        if (originalMode === null) root.removeAttribute('data-obsidian'); else root.setAttribute('data-obsidian', originalMode);
    };
}
