import { useState, useEffect } from 'react';
/**
 * Hook to access the current user context from the Pterodactyl panel.
 */
export function useUserContext() {
    const [user, setUser] = useState(null);
    useEffect(() => {
        let cancelled = false;
        // Try reading from Pterodactyl's global state
        const storeState = window.PterodactylUser;
        if (storeState) {
            setUser(storeState);
            return;
        }
        // Fallback: fetch from API
        fetch('/api/client/account', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then(r => r.json())
            .then(data => {
            if (cancelled)
                return;
            if (data.attributes) {
                setUser({
                    uuid: data.attributes.uuid,
                    username: data.attributes.username,
                    email: data.attributes.email,
                    isAdmin: data.attributes.admin || false,
                });
            }
        })
            .catch((err) => {
            var _a;
            console.warn('[Notur] Failed to load user context:', err);
            const notur = window.__NOTUR__;
            if ((_a = notur === null || notur === void 0 ? void 0 : notur.diagnostics) === null || _a === void 0 ? void 0 : _a.errors) {
                notur.diagnostics.errors.push({
                    extensionId: 'notur:bridge',
                    message: `Failed to load user context: ${(err === null || err === void 0 ? void 0 : err.message) || String(err)}`,
                    time: new Date().toISOString(),
                });
                if (notur.diagnostics.errors.length > 100) {
                    notur.diagnostics.errors.splice(0, notur.diagnostics.errors.length - 100);
                }
            }
        });
        return () => { cancelled = true; };
    }, []);
    return user;
}
//# sourceMappingURL=useUserContext.js.map