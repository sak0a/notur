import { useState, useEffect } from 'react';
import { useServerContext } from './useServerContext';

/**
 * Check whether the current server user has a permission.
 *
 * This hook reads server context permissions when available. It returns `false`
 * outside server pages, while context is unavailable, or when the permission is
 * missing. Server owners are treated as allowed.
 *
 * @example
 * ```tsx
 * const canInstall = usePermission('notur.acme/tools.install');
 * ```
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
            perms.indexOf('*') !== -1 || perms.indexOf(permission) !== -1,
        );
    }, [serverContext, permission]);

    return hasPermission;
}
