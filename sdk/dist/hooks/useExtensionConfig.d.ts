interface UseExtensionConfigOptions<T> {
    baseUrl?: string;
    initial?: T;
    pollInterval?: number;
}
interface ExtensionConfigState<T> {
    config: T;
    loading: boolean;
    error: string | null;
    refresh: () => Promise<T>;
}
/**
 * Fetch public extension settings exposed via admin.settings.*.public.
 */
export declare function useExtensionConfig<T extends Record<string, any> = Record<string, any>>(extensionId: string, options?: UseExtensionConfigOptions<T>): ExtensionConfigState<T>;
export {};
//# sourceMappingURL=useExtensionConfig.d.ts.map