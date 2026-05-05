export interface ScopedEventChannel {
    eventName: (name: string) => string;
    emit: (name: string, data?: unknown) => void;
    on: (name: string, callback: (data?: unknown) => void) => () => void;
}
/**
 * Create a namespaced inter-extension event channel.
 *
 * This avoids accidental collisions in global event names by scoping
 * all events to `ext:<extensionId>:<event>`.
 */
export declare function createScopedEventChannel(extensionId: string): ScopedEventChannel;
//# sourceMappingURL=events.d.ts.map