import { useCallback, useEffect, useRef, useState } from 'react';

/**
 * Options for `useExtensionConfig()`.
 */
export interface UseExtensionConfigOptions<T> {
    /** Override the Notur client API base URL. Defaults to `/api/client/notur`. */
    baseUrl?: string;
    /** Initial config value used before the first request completes. */
    initial?: T;
    /** Optional polling interval in milliseconds. Omit or set `0` to disable polling. */
    pollInterval?: number;
}

/**
 * Return value from `useExtensionConfig()`.
 */
export interface ExtensionConfigState<T> {
    /** Public settings object for the extension. */
    config: T;
    /** True while a request is in flight. */
    loading: boolean;
    /** Last error message, or `null` when config loaded successfully. */
    error: string | null;
    /** Re-fetch config immediately and resolve with the latest value. */
    refresh: () => Promise<T>;
}

/**
 * Fetch public extension settings exposed by `admin.settings` fields marked `public: true`.
 *
 * @example
 * ```tsx
 * const { config, loading, error, refresh } = useExtensionConfig('acme/red-button', {
 *   initial: { enabled: true },
 * });
 * ```
 */
export function useExtensionConfig<T extends Record<string, any> = Record<string, any>>(
    extensionId: string,
    options: UseExtensionConfigOptions<T> = {},
): ExtensionConfigState<T> {
    const { baseUrl, initial, pollInterval } = options;
    const [config, setConfig] = useState<T>((initial ?? {}) as T);
    const [loading, setLoading] = useState<boolean>(true);
    const [error, setError] = useState<string | null>(null);
    const mountedRef = useRef(true);

    useEffect(() => {
        mountedRef.current = true;
        return () => {
            mountedRef.current = false;
        };
    }, []);

    const refresh = useCallback(async (): Promise<T> => {
        if (!extensionId) {
            const empty = (initial ?? {}) as T;
            if (mountedRef.current) {
                setConfig(empty);
                setLoading(false);
                setError(null);
            }
            return empty;
        }

        if (mountedRef.current) {
            setLoading(true);
            setError(null);
        }

        const apiBase = baseUrl || '/api/client/notur';
        const url = `${apiBase}/extensions/${extensionId}/settings`;

        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                let message = `Settings request failed: ${response.status} ${response.statusText}`;
                try {
                    const body = await response.json();
                    if (body?.message) {
                        message = body.message;
                    }
                } catch {
                    // Ignore JSON parse failures
                }

                throw new Error(message);
            }

            const payload = await response.json();
            const data = (payload?.data ?? {}) as T;

            if (mountedRef.current) {
                setConfig(data);
                setLoading(false);
                setError(null);
            }

            return data;
        } catch (err: any) {
            const message = err?.message || 'Failed to load settings.';
            if (mountedRef.current) {
                setLoading(false);
                setError(message);
            }
            throw err;
        }
    }, [baseUrl, extensionId, initial]);

    useEffect(() => {
        refresh();
    }, [refresh]);

    useEffect(() => {
        if (!pollInterval || pollInterval <= 0) {
            return;
        }

        const id = window.setInterval(() => {
            refresh().catch(() => {
                // ignore polling errors; surface via hook state
            });
        }, pollInterval);

        return () => {
            window.clearInterval(id);
        };
    }, [pollInterval, refresh]);

    return {
        config,
        loading,
        error,
        refresh,
    };
}
