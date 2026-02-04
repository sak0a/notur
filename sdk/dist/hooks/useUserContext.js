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
            .catch(() => { });
        return () => { cancelled = true; };
    }, []);
    return user;
}
//# sourceMappingURL=useUserContext.js.map