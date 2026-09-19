/** One shared highlight per menu, so adjacent entries morph instead of flashing. */
export function createNavigationMotion() {
    const bindings = new Map<HTMLElement, { refresh: () => void; cleanup: () => void }>();
    const bind = (host: HTMLElement) => {
        host.querySelectorAll(':scope > .ob-nav-highlight').forEach(node => node.remove());
        const highlight = document.createElement('span');
        highlight.className = 'ob-nav-highlight'; highlight.setAttribute('aria-hidden', 'true');
        host.append(highlight); host.setAttribute('data-ob-motion', '');
        let hovered: HTMLElement | null = null;
        let frame = 0;
        const eligible = (node: EventTarget | null) => {
            const link = node instanceof Element ? node.closest<HTMLElement>('a, button') : null;
            return link?.parentElement === host && !link.closest('.ob-search') ? link : null;
        };
        const place = () => {
            frame = 0;
            const target = hovered?.isConnected ? hovered : eligible(document.activeElement) ?? host.querySelector<HTMLElement>(':scope > a.active');
            if (!target || !target.getClientRects().length) { highlight.style.opacity = '0'; return; }
            const box = target.getBoundingClientRect(), parent = host.getBoundingClientRect();
            const first = !highlight.style.transform;
            if (first) highlight.style.transition = 'none';
            highlight.style.transform = `translate3d(${box.left-parent.left+host.scrollLeft-host.clientLeft}px,${box.top-parent.top+host.scrollTop-host.clientTop}px,0)`;
            highlight.style.width = `${box.width}px`; highlight.style.height = `${box.height}px`; highlight.style.opacity = '1';
            if (first) { highlight.getBoundingClientRect(); highlight.style.removeProperty('transition'); }
        };
        const refresh = () => { if (!frame) frame = requestAnimationFrame(place); };
        const over = (event: Event) => { hovered = eligible(event.target); refresh(); };
        const leave = () => { hovered = null; refresh(); };
        host.addEventListener('pointerover', over); host.addEventListener('pointerleave', leave);
        host.addEventListener('focusin', refresh); host.addEventListener('focusout', refresh);
        const observer = new MutationObserver(refresh);
        observer.observe(host, { attributes: true, attributeFilter: ['class'], subtree: true });
        const resize = new ResizeObserver(refresh); resize.observe(host);
        window.addEventListener('resize', refresh);
        refresh();
        return { refresh, cleanup: () => {
            cancelAnimationFrame(frame); observer.disconnect(); resize.disconnect();
            host.removeEventListener('pointerover', over); host.removeEventListener('pointerleave', leave);
            host.removeEventListener('focusin', refresh); host.removeEventListener('focusout', refresh);
            window.removeEventListener('resize', refresh); host.removeAttribute('data-ob-motion'); highlight.remove();
        } };
    };
    return {
        sync: (hosts: (HTMLElement | null)[]) => {
            bindings.forEach((binding, host) => { if (!hosts.includes(host) || !host.isConnected) { binding.cleanup(); bindings.delete(host); } });
            hosts.forEach(host => { if (host && !bindings.has(host)) bindings.set(host, bind(host)); });
        },
        cleanup: () => { bindings.forEach(binding => binding.cleanup()); bindings.clear(); },
    };
}
