/** Inline, session-authenticated server search. No changes to the panel router or React tree. */
export function createSearch() {
    const element = document.createElement('div');
    element.className = 'ob-search';
    const input = document.createElement('input');
    input.type = 'search'; input.placeholder = 'Search servers…';
    input.setAttribute('aria-label', 'Search servers');
    input.setAttribute('aria-controls', 'ob-search-results');
    input.setAttribute('aria-expanded', 'false');
    input.autocomplete = 'off';
    const results = document.createElement('div');
    results.id = 'ob-search-results'; results.className = 'ob-search-results'; results.hidden = true;
    const status = document.createElement('p'); status.setAttribute('role', 'status');
    const links = document.createElement('div');
    results.append(status, links); element.append(input, results);
    let timer = 0, generation = 0, controller: AbortController | undefined;
    const hide = () => { results.hidden = true; input.setAttribute('aria-expanded', 'false'); };
    const show = () => { results.hidden = false; input.setAttribute('aria-expanded', 'true'); };
    const search = async () => {
        const query = input.value.trim();
        const current = ++generation;
        controller?.abort(); links.replaceChildren();
        if (query.length < 2) { hide(); return; }
        show(); status.textContent = 'Searching…';
        controller = new AbortController();
        const params = new URLSearchParams({ 'filter[*]': query, per_page: '5' });
        if (document.querySelector('[data-ob-nav] a[href="/admin"]')) params.set('type', 'admin-all');
        try {
            const response = await fetch(`/api/client?${params}`, { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: controller.signal });
            if (!response.ok) throw new Error('Search failed');
            const payload = await response.json();
            if (current !== generation) return;
            const servers = (payload.data ?? []).slice(0, 5);
            status.textContent = servers.length ? `${servers.length} server${servers.length === 1 ? '' : 's'}` : 'No servers found.';
            servers.forEach(({ attributes: server }: any) => {
                if (!/^[a-zA-Z0-9-]+$/.test(server.identifier)) return;
                const link = document.createElement('a');
                link.href = `/server/${server.identifier}`;
                const name = document.createElement('strong'); name.textContent = server.name;
                const detail = document.createElement('span'); detail.textContent = server.node ?? '';
                link.append(name, detail); links.append(link);
            });
        } catch (error) {
            if (current !== generation || (error as Error).name === 'AbortError') return;
            status.textContent = 'Search unavailable. Please try again.';
        }
    };
    input.oninput = () => {
        ++generation; controller?.abort(); clearTimeout(timer);
        links.replaceChildren();
        if (input.value.trim().length < 2) hide();
        else { show(); status.textContent = 'Searching…'; timer = window.setTimeout(search, 250); }
    };
    input.onfocus = () => { if (input.value.trim().length >= 2) void search(); };
    element.onkeydown = event => {
        const anchors = Array.from(links.querySelectorAll('a'));
        if (event.key === 'Escape') { input.focus(); ++generation; controller?.abort(); clearTimeout(timer); hide(); }
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault(); const current = anchors.indexOf(document.activeElement as HTMLAnchorElement);
            const next = event.key === 'ArrowDown' ? current + 1 : current - 1;
            (anchors[next] ?? input).focus();
        }
        if (event.key === 'Enter' && document.activeElement === input) { event.preventDefault(); anchors[0]?.click(); }
    };
    const outside = (event: Event) => { if (!element.contains(event.target as Node)) { ++generation; controller?.abort(); clearTimeout(timer); hide(); } };
    document.addEventListener('pointerdown', outside);
    document.addEventListener('focusin', outside);
    return { element, cleanup: () => { ++generation; clearTimeout(timer); controller?.abort(); document.removeEventListener('pointerdown', outside); document.removeEventListener('focusin', outside); element.remove(); } };
}
