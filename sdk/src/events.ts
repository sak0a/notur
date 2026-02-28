import { getNoturApi } from './types';

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
