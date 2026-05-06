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
export declare function useUserContext(): UserContext | null;
//# sourceMappingURL=useUserContext.d.ts.map