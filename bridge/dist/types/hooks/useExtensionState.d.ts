/**
 * Hook for shared state scoped to an extension.
 *
 * Multiple components from the same extension share the same underlying
 * store, so state changes are reflected everywhere. Returns the current
 * state, a `setState` (merge) function, and a `resetState` function.
 *
 * The store is automatically cleaned up when the last subscriber
 * unmounts, freeing memory for extensions that are no longer rendered.
 */
export declare function useExtensionState<T extends Record<string, any>>(extensionId: string, initialState: T): [T, (partial: Partial<T>) => void, () => void];
