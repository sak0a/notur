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
        // Mock window.location (use hex UUID format to match regex)
        const originalPathname = window.location.pathname;
        Object.defineProperty(window, 'location', {
            value: { pathname: '/server/abc-123-def-456' },
            writable: true,
            configurable: true,
        });

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

        // Restore
        Object.defineProperty(window, 'location', {
            value: { pathname: originalPathname },
            writable: true,
            configurable: true,
        });
    });

    it('returns null when not on server page', () => {
        // Mock window.location to non-server path
        Object.defineProperty(window, 'location', {
            value: { pathname: '/dashboard' },
            writable: true,
            configurable: true,
        });

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
