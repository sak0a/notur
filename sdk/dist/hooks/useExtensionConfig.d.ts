/**
 * Options for `useExtensionConfig()`.
 */
export interface UseExtensionConfigOptions<T> {
    /** Override the Notur client API base URL. Defaults to `/api/client/notur`. */
    baseUrl?: string;
    /** Initial config value used before the first request completes. */
    initial?: T;
    /** Optional polling interval in milliseconds. Omit or set `0` to disable polling. */
    pollInterval?: number;
}
/**
 * Return value from `useExtensionConfig()`.
 */
export interface ExtensionConfigState<T> {
    /** Public settings object for the extension. */
    config: T;
    /** True while a request is in flight. */
    loading: boolean;
    /** Last error message, or `null` when config loaded successfully. */
    error: string | null;
    /** Re-fetch config immediately and resolve with the latest value. */
    refresh: () => Promise<T>;
}
/**
 * Fetch public extension settings exposed by `admin.settings` fields marked `public: true`.
 *
 * @example
 * ```tsx
 * const { config, loading, error, refresh } = useExtensionConfig('acme/red-button', {
 *   initial: { enabled: true },
 * });
 * ```
 */
export declare function useExtensionConfig<T extends Record<string, any> = Record<string, any>>(extensionId: string, options?: UseExtensionConfigOptions<T>): ExtensionConfigState<T>;
//# sourceMappingURL=useExtensionConfig.d.ts.map