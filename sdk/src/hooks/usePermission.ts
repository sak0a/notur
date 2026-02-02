import { useState, useEffect } from 'react';
import { useServerContext } from './useServerContext';

/**
 * Hook to check if the current user has a specific extension permission.
 */
export function usePermission(permission: string): boolean {
    const serverContext = useServerContext();
    const [hasPermission, setHasPermission] = useState(false);

    useEffect(() => {
        if (!serverContext) {
            setHasPermission(false);
            return;
        }

        // Admin override
        if (serverContext.isOwner) {
            setHasPermission(true);
            return;
        }

        // Check permissions array
        const perms = serverContext.permissions || [];
        setHasPermission(
            perms.includes('*') || perms.includes(permission),
        );
    }, [serverContext, permission]);

    return hasPermission;
}
