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
export declare function useServerContext(): ServerContext | null;
export {};
//# sourceMappingURL=useServerContext.d.ts.map