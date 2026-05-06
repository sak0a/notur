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
export declare function useNoturEvent(event: string, handler: (data?: unknown) => void): void;
/**
 * Returns a function to emit events on the inter-extension event bus.
 *
 * @example
 * ```tsx
 * const emit = useEmitEvent();
 * emit('acme:refresh', { source: 'button' });
 * ```
 */
export declare function useEmitEvent(): (event: string, data?: unknown) => void;
//# sourceMappingURL=useNoturEvent.d.ts.map