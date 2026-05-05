import { useCallback, useEffect, useRef, useState } from 'react';
/**
 * Fetch public extension settings exposed via admin.settings.*.public.
 */
export function useExtensionConfig(extensionId, options = {}) {
    const { baseUrl, initial, pollInterval } = options;
    const [config, setConfig] = useState((initial !== null && initial !== void 0 ? initial : {}));
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const mountedRef = useRef(true);
    useEffect(() => {
        mountedRef.current = true;
        return () => {
            mountedRef.current = false;
        };
    }, []);
    const refresh = useCallback(async () => {
        var _a;
        if (!extensionId) {
            const empty = (initial !== null && initial !== void 0 ? initial : {});
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
                    if (body === null || body === void 0 ? void 0 : body.message) {
                        message = body.message;
                    }
                }
                catch (_b) {
                    // Ignore JSON parse failures
                }
                throw new Error(message);
            }
            const payload = await response.json();
            const data = ((_a = payload === null || payload === void 0 ? void 0 : payload.data) !== null && _a !== void 0 ? _a : {});
            if (mountedRef.current) {
                setConfig(data);
                setLoading(false);
                setError(null);
            }
            return data;
        }
        catch (err) {
            const message = (err === null || err === void 0 ? void 0 : err.message) || 'Failed to load settings.';
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
//# sourceMappingURL=useExtensionConfig.js.map