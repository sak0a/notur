import { useState, useEffect } from 'react';

/**
 * Current Pterodactyl user metadata returned by `useUserContext()`.
 */
export interface UserContext {
    /** User UUID. */
    uuid: string;
    /** Username shown in the panel. */
    username: string;
    /** User email address. */
    email: string;
    /** True when the user is a root/admin user. */
    isAdmin: boolean;
}

/**
 * Hook to access the current user context from the Pterodactyl panel.
 *
 * Returns `null` while loading or when the user cannot be resolved.
 */
export function useUserContext(): UserContext | null {
    const [user, setUser] = useState<UserContext | null>(null);

    useEffect(() => {
        let cancelled = false;

        // Try reading from Pterodactyl's global state
        const storeState = (window as any).PterodactylUser;
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
                if (cancelled) return;
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
                console.warn('[Notur] Failed to load user context:', err);
                const notur = (window as any).__NOTUR__;
                if (notur?.diagnostics?.errors) {
                    notur.diagnostics.errors.push({
                        extensionId: 'notur:bridge',
                        message: `Failed to load user context: ${err?.message || String(err)}`,
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
