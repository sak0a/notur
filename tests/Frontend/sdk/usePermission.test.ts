import * as React from 'react';
import * as ReactDOM from 'react-dom';
import { act } from 'react-dom/test-utils';
import { usePermission } from '../../../sdk/src/hooks/usePermission';

describe('usePermission', () => {
    let container: HTMLDivElement;
    let appElement: HTMLDivElement | null = null;

    function createHookRenderer(permission: string) {
        const result: { current: boolean | null } = { current: null };

        function TestComponent() {
            result.current = usePermission(permission);
            return null;
        }

        return { result, TestComponent };
    }

    function setupServerContext(data: object) {
        appElement = document.createElement('div');
        appElement.id = 'app';
        appElement.dataset.server = JSON.stringify(data);
        document.body.appendChild(appElement);
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

    it('returns true for granted permission', () => {
        setupServerContext({
            uuid: 'test-uuid',
            name: 'Test',
            node: 'node',
            isOwner: false,
            status: 'running',
            permissions: ['view', 'edit', 'notur.acme/test.admin'],
        });

        const { result, TestComponent } = createHookRenderer('notur.acme/test.admin');

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        expect(result.current).toBe(true);
    });

    it('returns false for missing permission', () => {
        setupServerContext({
            uuid: 'test-uuid',
            name: 'Test',
            node: 'node',
            isOwner: false,
            status: 'running',
            permissions: ['view', 'edit'],
        });

        const { result, TestComponent } = createHookRenderer('notur.acme/test.admin');

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        expect(result.current).toBe(false);
    });

    it('returns true for admin override', () => {
        setupServerContext({
            uuid: 'test-uuid',
            name: 'Test',
            node: 'node',
            isOwner: true,
            status: 'running',
            permissions: [], // Empty permissions, but owner
        });

        const { result, TestComponent } = createHookRenderer('any.permission');

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        expect(result.current).toBe(true);
    });

    it('handles wildcard permissions', () => {
        setupServerContext({
            uuid: 'test-uuid',
            name: 'Test',
            node: 'node',
            isOwner: false,
            status: 'running',
            permissions: ['*'],
        });

        const { result, TestComponent } = createHookRenderer('any.permission.here');

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        expect(result.current).toBe(true);
    });

    it('returns false when no server context', () => {
        // No app element, no server context

        const { result, TestComponent } = createHookRenderer('some.permission');

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        expect(result.current).toBe(false);
    });
});
