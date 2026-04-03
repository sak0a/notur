import * as React from 'react';
import * as ReactDOM from 'react-dom';
import { act } from 'react-dom/test-utils';
import { useServerContext } from '../../../sdk/src/hooks/useServerContext';

describe('useServerContext', () => {
    let container: HTMLDivElement;
    let appElement: HTMLDivElement | null = null;

    function createHookRenderer() {
        const result: { current: ReturnType<typeof useServerContext> | null } = { current: null };

        function TestComponent() {
            result.current = useServerContext();
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
        if (appElement) {
            appElement.remove();
            appElement = null;
        }
    });

    it('reads from DOM data attribute', () => {
        const serverData = {
            uuid: 'abc-123',
            name: 'My Server',
            node: 'node-1',
            isOwner: true,
            status: 'running',
            permissions: ['*'],
        };

        appElement = document.createElement('div');
        appElement.id = 'app';
        appElement.dataset.server = JSON.stringify(serverData);
        document.body.appendChild(appElement);

        const { result, TestComponent } = createHookRenderer();

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        expect(result.current).toEqual(serverData);
    });

    it('falls back to URL pathname', () => {
        window.history.pushState({}, '', '/server/abc-123-def-456');

        // Create app element without server data
        appElement = document.createElement('div');
        appElement.id = 'app';
        document.body.appendChild(appElement);

        const { result, TestComponent } = createHookRenderer();

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        expect(result.current).toMatchObject({
            uuid: 'abc-123-def-456',
        });
    });

    it('returns null when not on server page', () => {
        window.history.pushState({}, '', '/dashboard');

        const { result, TestComponent } = createHookRenderer();

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        expect(result.current).toBeNull();
    });

    it('parses JSON data correctly', () => {
        const complexData = {
            uuid: 'complex-uuid',
            name: 'Complex "Server"',
            node: 'node-with-special',
            isOwner: false,
            status: null,
            permissions: ['view', 'edit', 'notur.acme/test.admin'],
        };

        appElement = document.createElement('div');
        appElement.id = 'app';
        appElement.dataset.server = JSON.stringify(complexData);
        document.body.appendChild(appElement);

        const { result, TestComponent } = createHookRenderer();

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        expect(result.current?.uuid).toBe('complex-uuid');
        expect(result.current?.permissions).toContain('notur.acme/test.admin');
        expect(result.current?.status).toBeNull();
        expect(result.current?.isOwner).toBe(false);
    });
});
