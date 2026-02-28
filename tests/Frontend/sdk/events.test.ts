import { createScopedEventChannel } from '../../../sdk/src/events';

describe('createScopedEventChannel', () => {
    const emitEvent = jest.fn();
    const onEvent = jest.fn();

    beforeEach(() => {
        (window as any).__NOTUR__ = {
            registry: {
                emitEvent,
                onEvent,
            },
        };
        jest.clearAllMocks();
    });

    afterEach(() => {
        delete (window as any).__NOTUR__;
    });

    it('emits events with extension namespace prefix', () => {
        const channel = createScopedEventChannel('acme/demo');
        channel.emit('ready', { ok: true });

        expect(emitEvent).toHaveBeenCalledWith('ext:acme/demo:ready', { ok: true });
    });

    it('subscribes with namespaced event names', () => {
        const unsubscribe = jest.fn();
        onEvent.mockReturnValue(unsubscribe);

        const channel = createScopedEventChannel('acme/demo');
        const callback = jest.fn();
        const off = channel.on('updated', callback);

        expect(onEvent).toHaveBeenCalledWith('ext:acme/demo:updated', callback);
        expect(off).toBe(unsubscribe);
    });
});
