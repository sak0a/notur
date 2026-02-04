interface UseExtensionApiOptions {
    extensionId: string;
    baseUrl?: string;
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
export declare function useExtensionApi<T = any>({ extensionId, baseUrl }: UseExtensionApiOptions): {
    get: (path: string) => Promise<T>;
    post: (path: string, body?: any) => Promise<T>;
    put: (path: string, body?: any) => Promise<T>;
    patch: (path: string, body?: any) => Promise<T>;
    delete: (path: string) => Promise<T>;
    request: (path: string, options?: RequestInit) => Promise<T>;
    data: T | null;
    loading: boolean;
    error: string | null;
};
export {};
