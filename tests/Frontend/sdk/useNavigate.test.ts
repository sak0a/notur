import * as React from 'react';
import * as ReactDOM from 'react-dom';
import { act } from 'react-dom/test-utils';
import { useNavigate } from '../../../sdk/src/hooks/useNavigate';

describe('useNavigate', () => {
    let container: HTMLDivElement;
    let originalPushState: typeof window.history.pushState;
    let originalReplaceState: typeof window.history.replaceState;
    let mockPushState: jest.Mock;
    let mockReplaceState: jest.Mock;
    let popstateHandler: jest.Mock;

    function createHookRenderer(extensionId: string) {
        const result: { current: ReturnType<typeof useNavigate> | null } = { current: null };

        function TestComponent() {
            result.current = useNavigate({ extensionId });
            return null;
        }

        return { result, TestComponent };
    }

    beforeEach(() => {
        container = document.createElement('div');
        document.body.appendChild(container);

        // Mock history methods
        originalPushState = window.history.pushState;
        originalReplaceState = window.history.replaceState;
        mockPushState = jest.fn();
        mockReplaceState = jest.fn();
        window.history.pushState = mockPushState;
        window.history.replaceState = mockReplaceState;

        // Track popstate events
        popstateHandler = jest.fn();
        window.addEventListener('popstate', popstateHandler);
    });

    afterEach(() => {
        ReactDOM.unmountComponentAtNode(container);
        container.remove();
        window.history.pushState = originalPushState;
        window.history.replaceState = originalReplaceState;
        window.removeEventListener('popstate', popstateHandler);
    });

    it('constructs extension path', () => {
        const { result, TestComponent } = createHookRenderer('acme/test');

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        act(() => {
            result.current!('/settings');
        });

        expect(mockPushState).toHaveBeenCalledWith(null, '', '/notur/acme/test/settings');
    });

    it('uses push state by default', () => {
        const { result, TestComponent } = createHookRenderer('acme/test');

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        act(() => {
            result.current!('/page');
        });

        expect(mockPushState).toHaveBeenCalled();
        expect(mockReplaceState).not.toHaveBeenCalled();
    });

    it('uses replace state with option', () => {
        const { result, TestComponent } = createHookRenderer('acme/test');

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        act(() => {
            result.current!('/page', { replace: true });
        });

        expect(mockReplaceState).toHaveBeenCalledWith(null, '', '/notur/acme/test/page');
        expect(mockPushState).not.toHaveBeenCalled();
    });

    it('dispatches popstate event', () => {
        const { result, TestComponent } = createHookRenderer('acme/test');

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        act(() => {
            result.current!('/route');
        });

        expect(popstateHandler).toHaveBeenCalled();
    });

    it('handles leading slash in path', () => {
        const { result, TestComponent } = createHookRenderer('vendor/ext');

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        // With leading slash
        act(() => {
            result.current!('/with-slash');
        });
        expect(mockPushState).toHaveBeenLastCalledWith(null, '', '/notur/vendor/ext/with-slash');

        // Without leading slash
        act(() => {
            result.current!('without-slash');
        });
        expect(mockPushState).toHaveBeenLastCalledWith(null, '', '/notur/vendor/ext/without-slash');
    });
});
