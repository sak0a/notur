import { useCallback } from 'react';

interface UseNavigateOptions {
    extensionId: string;
}

/**
 * Returns a navigate function scoped to the extension's route namespace.
 * Navigates to `/notur/{extensionId}/{path}` using pushState.
 */
export function useNavigate({ extensionId }: UseNavigateOptions) {
    return useCallback(
        (path: string, options?: { replace?: boolean }) => {
            const fullPath = `/notur/${extensionId}${path.startsWith('/') ? path : '/' + path}`;
            if (options?.replace) {
                window.history.replaceState(null, '', fullPath);
            } else {
                window.history.pushState(null, '', fullPath);
            }
            // Dispatch popstate so RouteRenderer picks up the change
            window.dispatchEvent(new PopStateEvent('popstate'));
        },
        [extensionId],
    );
}
