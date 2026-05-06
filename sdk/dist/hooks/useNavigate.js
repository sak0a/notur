import { useCallback } from 'react';
/**
 * Returns a navigate function scoped to the extension route namespace.
 *
 * Navigates to `/notur/{extensionId}/{path}` using the browser History API and
 * dispatches `popstate` so Notur route renderers update immediately.
 *
 * @example
 * ```tsx
 * const navigate = useNavigate({ extensionId: 'acme/tools' });
 * navigate('/settings');
 * navigate('/overview', { replace: true });
 * ```
 */
export function useNavigate({ extensionId }) {
    return useCallback((path, options) => {
        const fullPath = `/notur/${extensionId}${path.startsWith('/') ? path : '/' + path}`;
        if (options === null || options === void 0 ? void 0 : options.replace) {
            window.history.replaceState(null, '', fullPath);
        }
        else {
            window.history.pushState(null, '', fullPath);
        }
        // Dispatch popstate so RouteRenderer picks up the change
        window.dispatchEvent(new PopStateEvent('popstate'));
    }, [extensionId]);
}
//# sourceMappingURL=useNavigate.js.map