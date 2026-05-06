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
export declare function usePermission(permission: string): boolean;
//# sourceMappingURL=usePermission.d.ts.map