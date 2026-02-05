import * as React from 'react';
import * as ReactDOM from 'react-dom';
import { act } from 'react-dom/test-utils';
import { PluginRegistry } from '../../../bridge/src/PluginRegistry';

// We need to mock the SDK's getNoturApi
jest.mock('../../../sdk/src/types', () => ({
    getNoturApi: () => (window as any).__NOTUR__,
}));

import { useNoturEvent, useEmitEvent } from '../../../sdk/src/hooks/useNoturEvent';

describe('useNoturEvent', () => {
    let container: HTMLDivElement;
    let registry: PluginRegistry;

    function createEventHookRenderer(eventName: string, handler: jest.Mock) {
        function TestComponent() {
            useNoturEvent(eventName, handler);
            return null;
        }
        return { TestComponent };
    }

    function createEmitHookRenderer() {
        const result: { current: ReturnType<typeof useEmitEvent> | null } = { current: null };

        function TestComponent() {
            result.current = useEmitEvent();
            return null;
        }

        return { result, TestComponent };
    }

    beforeEach(() => {
        container = document.createElement('div');
        document.body.appendChild(container);
        registry = new PluginRegistry();
        (window as any).__NOTUR__ = { registry };
    });

    afterEach(() => {
        ReactDOM.unmountComponentAtNode(container);
        container.remove();
        delete (window as any).__NOTUR__;
    });

    it('subscribes to named event', () => {
        const handler = jest.fn();
        const { TestComponent } = createEventHookRenderer('test-event', handler);

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        // Emit event
        act(() => {
            registry.emitEvent('test-event', { data: 'test' });
        });

        expect(handler).toHaveBeenCalledTimes(1);
    });

    it('receives event payload', () => {
        const handler = jest.fn();
        const { TestComponent } = createEventHookRenderer('my-event', handler);

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        const payload = { message: 'hello', count: 42 };
        act(() => {
            registry.emitEvent('my-event', payload);
        });

        expect(handler).toHaveBeenCalledWith(payload);
    });

    it('unsubscribes on unmount', () => {
        const handler = jest.fn();
        const { TestComponent } = createEventHookRenderer('cleanup-event', handler);

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        // Unmount
        act(() => {
            ReactDOM.unmountComponentAtNode(container);
        });

        // Emit after unmount should not trigger handler
        act(() => {
            registry.emitEvent('cleanup-event', {});
        });

        expect(handler).not.toHaveBeenCalled();
    });

    it('emits events via useEmitEvent', () => {
        const handler = jest.fn();
        registry.onEvent('emit-test', handler);

        const { result, TestComponent } = createEmitHookRenderer();

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        act(() => {
            result.current!('emit-test', { value: 'emitted' });
        });

        expect(handler).toHaveBeenCalledWith({ value: 'emitted' });
    });
});
