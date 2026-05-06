import { useEffect, useCallback } from 'react';
import { getNoturApi } from '../types';
/**
 * Subscribe to an inter-extension event.
 *
 * Automatically unsubscribes on unmount.
 *
 * @example
 * ```tsx
 * useNoturEvent('acme:refresh', data => console.log(data));
 * ```
 */
export function useNoturEvent(event, handler) {
    useEffect(() => {
        const api = getNoturApi();
        const unsubscribe = api.registry.onEvent(event, handler);
        return unsubscribe;
    }, [event, handler]);
}
/**
 * Returns a function to emit events on the inter-extension event bus.
 *
 * @example
 * ```tsx
 * const emit = useEmitEvent();
 * emit('acme:refresh', { source: 'button' });
 * ```
 */
export function useEmitEvent() {
    return useCallback((event, data) => {
        const api = getNoturApi();
        api.registry.emitEvent(event, data);
    }, []);
}
//# sourceMappingURL=useNoturEvent.js.map