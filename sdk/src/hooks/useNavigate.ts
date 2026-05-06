import { useCallback } from 'react';

/**
 * Options for `useNavigate()`.
 */
export interface UseNavigateOptions {
    /** Owning extension id. Used to build `/notur/{extensionId}/...` URLs. */
    extensionId: string;
}

/**
 * Options accepted by the navigate function returned from `useNavigate()`.
 */
export interface NavigateOptions {
    /** Replace the current history entry instead of pushing a new one. */
    replace?: boolean;
}

/**
 * Function returned by `useNavigate()`.
 */
export type NavigateFunction = (path: string, options?: NavigateOptions) => void;

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
export function useNavigate({ extensionId }: UseNavigateOptions): NavigateFunction {
    return useCallback(
        (path: string, options?: NavigateOptions) => {
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
