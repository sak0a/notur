import { getNoturApi } from './types';

export interface ScopedEventChannel {
    /** Convert a local event name into the fully scoped global event name. */
    eventName: (name: string) => string;
    /** Emit a scoped event with optional payload data. */
    emit: (name: string, data?: unknown) => void;
    /** Subscribe to a scoped event. Returns an unsubscribe function. */
    on: (name: string, callback: (data?: unknown) => void) => () => void;
}

/**
 * Create a namespaced inter-extension event channel.
 *
 * This avoids accidental collisions in global event names by scoping
 * all events to `ext:<extensionId>:<event>`.
 *
 * @example
 * ```ts
 * const channel = createScopedEventChannel('acme/tools');
 * channel.emit('saved', { id: 1 });
 * channel.on('saved', data => console.log(data));
 * ```
 */
export function createScopedEventChannel(extensionId: string): ScopedEventChannel {
    const api = getNoturApi();

    const eventName = (name: string): string => `ext:${extensionId}:${name}`;

    return {
        eventName,
        emit: (name: string, data?: unknown): void => {
            api.registry.emitEvent(eventName(name), data);
        },
        on: (name: string, callback: (data?: unknown) => void): (() => void) => {
            return api.registry.onEvent(eventName(name), callback);
        },
    };
}
