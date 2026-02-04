import * as React from 'react';
import * as ReactDOM from 'react-dom';
import { act } from 'react-dom/test-utils';
import { useSlot } from '../../../bridge/src/hooks/useSlot';
import { PluginRegistry } from '../../../bridge/src/PluginRegistry';

describe('useSlot', () => {
    let container: HTMLDivElement;
    let registry: PluginRegistry;

    function createHookRenderer(slotId: string) {
        const result: { current: ReturnType<typeof useSlot> | null } = { current: null };

        function TestComponent() {
            result.current = useSlot(slotId as any);
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

    it('returns slot components', () => {
        const component = () => React.createElement('div', null, 'test');
        registry.registerSlot({
            extensionId: 'acme/test',
            slot: 'dashboard.widgets' as any,
            component,
            order: 10,
        });

        const { result, TestComponent } = createHookRenderer('dashboard.widgets');

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        expect(result.current).toHaveLength(1);
        expect(result.current![0].extensionId).toBe('acme/test');
        expect(result.current![0].component).toBe(component);
    });

    it('returns empty array for unknown slot', () => {
        const { result, TestComponent } = createHookRenderer('nonexistent.slot');

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        expect(result.current).toEqual([]);
    });

    it('updates when registry changes', () => {
        const { result, TestComponent } = createHookRenderer('dashboard.widgets');

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        expect(result.current).toHaveLength(0);

        act(() => {
            registry.registerSlot({
                extensionId: 'acme/new',
                slot: 'dashboard.widgets' as any,
                component: () => React.createElement('div'),
                order: 5,
            });
        });

        expect(result.current).toHaveLength(1);
        expect(result.current![0].extensionId).toBe('acme/new');
    });

    it('respects component order', () => {
        const comp1 = () => React.createElement('div', null, '1');
        const comp2 = () => React.createElement('div', null, '2');
        const comp3 = () => React.createElement('div', null, '3');

        registry.registerSlot({ extensionId: 'a', slot: 'navbar' as any, component: comp1, order: 30 });
        registry.registerSlot({ extensionId: 'b', slot: 'navbar' as any, component: comp2, order: 10 });
        registry.registerSlot({ extensionId: 'c', slot: 'navbar' as any, component: comp3, order: 20 });

        const { result, TestComponent } = createHookRenderer('navbar');

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        expect(result.current![0].extensionId).toBe('b');
        expect(result.current![1].extensionId).toBe('c');
        expect(result.current![2].extensionId).toBe('a');
    });

    it('handles missing global', () => {
        delete (window as any).__NOTUR__;

        const { result, TestComponent } = createHookRenderer('dashboard.widgets');

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        expect(result.current).toEqual([]);
    });

    it('unsubscribes on unmount', () => {
        const { TestComponent } = createHookRenderer('dashboard.widgets');

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        // Unmount should not throw and should clean up listeners
        act(() => {
            ReactDOM.unmountComponentAtNode(container);
        });

        // Registering after unmount should not cause issues
        expect(() => {
            registry.registerSlot({
                extensionId: 'acme/late',
                slot: 'dashboard.widgets' as any,
                component: () => React.createElement('div'),
            });
        }).not.toThrow();
    });
});
