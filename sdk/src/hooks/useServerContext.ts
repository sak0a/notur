import { useState, useEffect } from 'react';

interface ServerContext {
    uuid: string;
    name: string;
    node: string;
    isOwner: boolean;
    status: string | null;
    permissions: string[];
}

/**
 * Hook to access the current server context from the Pterodactyl panel.
 * Only available within server-scoped pages.
 */
export function useServerContext(): ServerContext | null {
    const [context, setContext] = useState<ServerContext | null>(null);

    useEffect(() => {
        // Pterodactyl stores server data in the ServerContext React context
        // We read it from the DOM data attribute as a fallback
        const serverElement = document.getElementById('app');
        if (!serverElement) return;

        const serverData = serverElement.dataset.server;
        if (serverData) {
            try {
                setContext(JSON.parse(serverData));
            } catch {
                // Not on a server page
            }
        }

        // Also check the URL for server UUID
        const match = window.location.pathname.match(/\/server\/([a-f0-9-]+)/);
        if (match) {
            setContext(prev => prev || { uuid: match[1], name: '', node: '', isOwner: false, status: null, permissions: [] });
        }
    }, []);

    return context;
}
