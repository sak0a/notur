import * as React from 'react';
import * as ReactDOM from 'react-dom';
import { act } from 'react-dom/test-utils';
import { useExtensionState } from '../../../bridge/src/hooks/useExtensionState';

describe('useExtensionState', () => {
    let container: HTMLDivElement;

    function createHookRenderer<T extends Record<string, any>>(
        extensionId: string,
        initialState: T,
    ) {
        const result: { current: ReturnType<typeof useExtensionState<T>> | null } = { current: null };

        function TestComponent() {
            result.current = useExtensionState(extensionId, initialState);
            return null;
        }

        return { result, TestComponent };
    }

    beforeEach(() => {
        container = document.createElement('div');
        document.body.appendChild(container);
    });

    afterEach(() => {
        ReactDOM.unmountComponentAtNode(container);
        container.remove();
    });

    it('returns initial state', () => {
        const { result, TestComponent } = createHookRenderer('acme/test', { count: 0 });

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        const [state] = result.current!;
        expect(state).toEqual({ count: 0 });
    });

    it('updates state', () => {
        const { result, TestComponent } = createHookRenderer('acme/test', { count: 0 });

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        act(() => {
            const [, setState] = result.current!;
            setState({ count: 5 });
        });

        const [state] = result.current!;
        expect(state.count).toBe(5);
    });

    it('shares state across components', () => {
        const container2 = document.createElement('div');
        document.body.appendChild(container2);

        const { result: result1, TestComponent: Component1 } = createHookRenderer('acme/shared', { value: 'initial' });
        const { result: result2, TestComponent: Component2 } = createHookRenderer('acme/shared', { value: 'initial' });

        act(() => {
            ReactDOM.render(React.createElement(Component1), container);
            ReactDOM.render(React.createElement(Component2), container2);
        });

        // Update state from first component
        act(() => {
            const [, setState] = result1.current!;
            setState({ value: 'updated' });
        });

        // Second component should see the update
        const [state2] = result2.current!;
        expect(state2.value).toBe('updated');

        // Cleanup
        ReactDOM.unmountComponentAtNode(container2);
        container2.remove();
    });

    it('notifies subscribers on change', () => {
        const { result, TestComponent } = createHookRenderer('acme/test', { name: 'initial' });

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        const initialState = result.current![0];
        expect(initialState.name).toBe('initial');

        act(() => {
            const [, setState] = result.current!;
            setState({ name: 'changed' });
        });

        const updatedState = result.current![0];
        expect(updatedState.name).toBe('changed');
    });

    it('auto cleanup when last subscriber unmounts', () => {
        const container2 = document.createElement('div');
        document.body.appendChild(container2);

        const { TestComponent: Component1 } = createHookRenderer('acme/cleanup-test', { data: 'test' });
        const { TestComponent: Component2 } = createHookRenderer('acme/cleanup-test', { data: 'test' });

        act(() => {
            ReactDOM.render(React.createElement(Component1), container);
            ReactDOM.render(React.createElement(Component2), container2);
        });

        // Unmount first component
        act(() => {
            ReactDOM.unmountComponentAtNode(container);
        });

        // Store should still exist (one subscriber remains)
        // Unmount second component
        act(() => {
            ReactDOM.unmountComponentAtNode(container2);
        });

        // Store should be cleaned up, no errors should occur
        container2.remove();
    });

    it('provides reset state function', () => {
        const { result, TestComponent } = createHookRenderer('acme/test', { count: 0 });

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        // Modify state
        act(() => {
            const [, setState] = result.current!;
            setState({ count: 100 });
        });

        expect(result.current![0].count).toBe(100);

        // Reset state
        act(() => {
            const [, , resetState] = result.current!;
            resetState();
        });

        expect(result.current![0].count).toBe(0);
    });

    it('reuses existing store for extension', () => {
        const container2 = document.createElement('div');
        document.body.appendChild(container2);

        const { result: result1, TestComponent: Component1 } = createHookRenderer('acme/reuse', { val: 'first' });

        act(() => {
            ReactDOM.render(React.createElement(Component1), container);
        });

        // Update state
        act(() => {
            const [, setState] = result1.current!;
            setState({ val: 'modified' });
        });

        // Mount second component with different initial state
        const { result: result2, TestComponent: Component2 } = createHookRenderer('acme/reuse', { val: 'second' });

        act(() => {
            ReactDOM.render(React.createElement(Component2), container2);
        });

        // Second component should get existing store's state, not its initial state
        const [state2] = result2.current!;
        expect(state2.val).toBe('modified');

        ReactDOM.unmountComponentAtNode(container2);
        container2.remove();
    });
});
