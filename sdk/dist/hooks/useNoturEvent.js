import { useEffect, useCallback } from 'react';
import { getNoturApi } from '../types';
/**
 * Subscribe to an inter-extension event.
 * Automatically unsubscribes on unmount.
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
 */
export function useEmitEvent() {
    return useCallback((event, data) => {
        const api = getNoturApi();
        api.registry.emitEvent(event, data);
    }, []);
}
//# sourceMappingURL=useNoturEvent.js.map