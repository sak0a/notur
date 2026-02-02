import { useState, useEffect, useCallback, useRef } from 'react';

type StateListener<T> = (state: T) => void;

/**
 * Simple shared state store for an extension.
 * All components belonging to the same extension share one store instance.
 */
class ExtensionStateStore<T extends Record<string, any>> {
    private state: T;
    private listeners: Set<StateListener<T>> = new Set();

    constructor(initialState: T) {
        this.state = { ...initialState };
    }

    getState(): T {
        return this.state;
    }

    setState(partial: Partial<T>): void {
        this.state = { ...this.state, ...partial };
        this.listeners.forEach(listener => {
            try {
                listener(this.state);
            } catch (e) {
                console.error('[Notur] Error in state listener:', e);
            }
        });
    }

    /**
     * Reset the store back to a given state (or empty object).
     */
    resetState(resetTo?: T): void {
        this.state = resetTo ? { ...resetTo } : ({} as T);
        this.listeners.forEach(listener => {
            try {
                listener(this.state);
            } catch (e) {
                console.error('[Notur] Error in state listener:', e);
            }
        });
    }

    subscribe(listener: StateListener<T>): () => void {
        this.listeners.add(listener);
        return () => {
            this.listeners.delete(listener);
        };
    }

    /**
     * Returns the number of active subscribers.
     */
    get listenerCount(): number {
        return this.listeners.size;
    }
}

// Global map of extension state stores
const stores = new Map<string, ExtensionStateStore<any>>();

/**
 * Get or create a state store for an extension.
 */
function getStore<T extends Record<string, any>>(
    extensionId: string,
    initialState: T,
): ExtensionStateStore<T> {
    if (!stores.has(extensionId)) {
        stores.set(extensionId, new ExtensionStateStore(initialState));
    }
    return stores.get(extensionId)!;
}

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
export function useExtensionState<T extends Record<string, any>>(
    extensionId: string,
    initialState: T,
): [T, (partial: Partial<T>) => void, () => void] {
    const store = getStore(extensionId, initialState);
    const [state, setLocalState] = useState<T>(store.getState());
    const extensionIdRef = useRef(extensionId);
    extensionIdRef.current = extensionId;

    useEffect(() => {
        // Re-sync in case the store was updated between render and effect
        setLocalState(store.getState());

        const unsubscribe = store.subscribe(setLocalState);

        return () => {
            unsubscribe();
            // Clean up the store if no more listeners remain
            if (store.listenerCount === 0) {
                stores.delete(extensionIdRef.current);
            }
        };
    }, [store]);

    const setState = useCallback(
        (partial: Partial<T>) => store.setState(partial),
        [store],
    );

    const resetState = useCallback(
        () => store.resetState(initialState),
        [store, initialState],
    );

    return [state, setState, resetState];
}
