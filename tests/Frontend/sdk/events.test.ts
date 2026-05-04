import { createScopedEventChannel } from '../../../sdk/src/events';

describe('createScopedEventChannel', () => {
    const emitEvent = vi.fn();
    const onEvent = vi.fn();

    beforeEach(() => {
        (window as any).__NOTUR__ = {
            registry: {
                emitEvent,
                onEvent,
            },
        };
        vi.clearAllMocks();
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
        const unsubscribe = vi.fn();
        onEvent.mockReturnValue(unsubscribe);

        const channel = createScopedEventChannel('acme/demo');
        const callback = vi.fn();
        const off = channel.on('updated', callback);

        expect(onEvent).toHaveBeenCalledWith('ext:acme/demo:updated', callback);
        expect(off).toBe(unsubscribe);
    });
});
