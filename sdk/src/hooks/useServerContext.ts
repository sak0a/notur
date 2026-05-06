import { useState, useEffect } from 'react';

/**
 * Server metadata available when the current page is scoped to a Pterodactyl server.
 */
export interface ServerContext {
    /** Server UUID from the current URL or panel data payload. */
    uuid: string;
    /** Server display name when available. */
    name: string;
    /** Node name/identifier when available. */
    node: string;
    /** True when the current user owns the server or has owner-level access. */
    isOwner: boolean;
    /** Runtime status reported by the panel, or `null` when unknown. */
    status: string | null;
    /** Server permission keys available to the current user. */
    permissions: string[];
}

/**
 * Hook to access the current server context from the Pterodactyl panel.
 *
 * Returns `null` outside server-scoped pages or while context is unavailable.
 *
 * @example
 * ```tsx
 * const server = useServerContext();
 * return <span>{server?.uuid}</span>;
 * ```
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
