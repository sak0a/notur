import { useCallback } from 'react';
/**
 * Returns a navigate function scoped to the extension's route namespace.
 * Navigates to `/notur/{extensionId}/{path}` using pushState.
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