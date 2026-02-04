interface UseNavigateOptions {
    extensionId: string;
}
/**
 * Returns a navigate function scoped to the extension's route namespace.
 * Navigates to `/notur/{extensionId}/{path}` using pushState.
 */
export declare function useNavigate({ extensionId }: UseNavigateOptions): (path: string, options?: {
    replace?: boolean;
}) => void;
export {};
//# sourceMappingURL=useNavigate.d.ts.map