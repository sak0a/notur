import { useState, useCallback, useRef, useEffect } from 'react';

interface UseExtensionApiOptions {
    extensionId: string;
    baseUrl?: string;
}

interface ApiState<T> {
    data: T | null;
    loading: boolean;
    error: string | null;
}

/**
 * Retrieve the CSRF token from either the meta tag or the XSRF cookie.
 * Pterodactyl's sanctum middleware accepts both `X-CSRF-TOKEN` (from meta)
 * and `X-XSRF-TOKEN` (from cookie, URL-decoded). We try the meta tag first
 * because it is cheaper.
 */
function getCsrfToken(): string | null {
    // 1. Meta tag (standard Laravel blade injection)
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) {
        const content = meta.getAttribute('content');
        if (content) return content;
    }

    // 2. XSRF-TOKEN cookie (set by Laravel sanctum / web middleware)
    const match = document.cookie
        .split('; ')
        .find(row => row.startsWith('XSRF-TOKEN='));
    if (match) {
        return decodeURIComponent(match.split('=')[1]);
    }

    return null;
}

/**
 * Hook for making API calls to an extension's namespaced endpoints.
 *
 * All requests target `/api/client/notur/{extensionId}/...` by default,
 * include CSRF tokens for mutation methods, and send credentials as
 * same-origin (cookie-based auth compatible with Pterodactyl).
 *
 * The hook is safe to use in components that may unmount while a request is
 * in flight — state updates are guarded by an `isMounted` ref.
 */
export function useExtensionApi<T = any>({ extensionId, baseUrl }: UseExtensionApiOptions) {
    const [state, setState] = useState<ApiState<T>>({
        data: null,
        loading: false,
        error: null,
    });

    const mountedRef = useRef(true);

    useEffect(() => {
        mountedRef.current = true;
        return () => {
            mountedRef.current = false;
        };
    }, []);

    const apiBase = baseUrl || `/api/client/notur/${extensionId}`;

    const request = useCallback(
        async (path: string, options: RequestInit = {}): Promise<T> => {
            if (mountedRef.current) {
                setState(prev => ({ ...prev, loading: true, error: null }));
            }

            try {
                const url = `${apiBase}${path.startsWith('/') ? path : '/' + path}`;

                const headers: Record<string, string> = {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    ...((options.headers as Record<string, string>) || {}),
                };

                // Attach CSRF token for state-changing methods
                const method = (options.method || 'GET').toUpperCase();
                if (method !== 'GET' && method !== 'HEAD' && method !== 'OPTIONS') {
                    const token = getCsrfToken();
                    if (token) {
                        headers['X-CSRF-TOKEN'] = token;
                        headers['X-XSRF-TOKEN'] = token;
                    }
                }

                const response = await fetch(url, {
                    ...options,
                    headers,
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    // Try to extract a structured error message from Laravel
                    let errorMessage = `API error: ${response.status} ${response.statusText}`;
                    try {
                        const errorBody = await response.json();
                        if (errorBody.message) {
                            errorMessage = errorBody.message;
                        } else if (errorBody.errors) {
                            errorMessage = Object.values(errorBody.errors).flat().join(', ');
                        }
                    } catch {
                        // Response body was not JSON — use the status text
                    }
                    throw new Error(errorMessage);
                }

                // Handle 204 No Content (e.g. successful DELETE)
                if (response.status === 204) {
                    if (mountedRef.current) {
                        setState({ data: null as any, loading: false, error: null });
                    }
                    return null as any;
                }

                const data = await response.json();
                if (mountedRef.current) {
                    setState({ data, loading: false, error: null });
                }
                return data;
            } catch (error: any) {
                const message = error.message || 'Unknown error';
                if (mountedRef.current) {
                    setState(prev => ({ ...prev, loading: false, error: message }));
                }
                throw error;
            }
        },
        [apiBase],
    );

    const get = useCallback(
        (path: string) => request(path, { method: 'GET' }),
        [request],
    );

    const post = useCallback(
        (path: string, body?: any) =>
            request(path, {
                method: 'POST',
                body: body != null ? JSON.stringify(body) : undefined,
            }),
        [request],
    );

    const put = useCallback(
        (path: string, body?: any) =>
            request(path, {
                method: 'PUT',
                body: body != null ? JSON.stringify(body) : undefined,
            }),
        [request],
    );

    const patch = useCallback(
        (path: string, body?: any) =>
            request(path, {
                method: 'PATCH',
                body: body != null ? JSON.stringify(body) : undefined,
            }),
        [request],
    );

    const del = useCallback(
        (path: string) => request(path, { method: 'DELETE' }),
        [request],
    );

    return {
        ...state,
        get,
        post,
        put,
        patch,
        delete: del,
        request,
    };
}
