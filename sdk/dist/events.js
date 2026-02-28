import { getNoturApi } from './types';
/**
 * Create a namespaced inter-extension event channel.
 *
 * This avoids accidental collisions in global event names by scoping
 * all events to `ext:<extensionId>:<event>`.
 */
export function createScopedEventChannel(extensionId) {
    const api = getNoturApi();
    const eventName = (name) => `ext:${extensionId}:${name}`;
    return {
        eventName,
        emit: (name, data) => {
            api.registry.emitEvent(eventName(name), data);
        },
        on: (name, callback) => {
            return api.registry.onEvent(eventName(name), callback);
        },
    };
}
//# sourceMappingURL=events.js.map