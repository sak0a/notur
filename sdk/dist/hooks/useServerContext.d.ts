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
export declare function useServerContext(): ServerContext | null;
//# sourceMappingURL=useServerContext.d.ts.map