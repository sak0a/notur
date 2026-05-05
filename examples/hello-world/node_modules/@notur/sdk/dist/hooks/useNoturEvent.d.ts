/**
 * Subscribe to an inter-extension event.
 * Automatically unsubscribes on unmount.
 */
export declare function useNoturEvent(event: string, handler: (data?: unknown) => void): void;
/**
 * Returns a function to emit events on the inter-extension event bus.
 */
export declare function useEmitEvent(): (event: string, data?: unknown) => void;
//# sourceMappingURL=useNoturEvent.d.ts.map