interface UserContext {
    uuid: string;
    username: string;
    email: string;
    isAdmin: boolean;
}
/**
 * Hook to access the current user context from the Pterodactyl panel.
 */
export declare function useUserContext(): UserContext | null;
export {};
//# sourceMappingURL=useUserContext.d.ts.map