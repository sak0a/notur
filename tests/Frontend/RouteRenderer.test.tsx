import * as React from 'react';
import * as ReactDOM from 'react-dom';
import { act } from 'react-dom/test-utils';
import { RouteRenderer, useRoutes } from '../../bridge/src/RouteRenderer';
import { PluginRegistry } from '../../bridge/src/PluginRegistry';

describe('RouteRenderer', () => {
    let container: HTMLDivElement;
    let registry: PluginRegistry;

    function setPathname(path: string) {
        window.history.pushState({}, '', path);
    }

    beforeEach(() => {
        container = document.createElement('div');
        document.body.appendChild(container);
        registry = new PluginRegistry();
        setPathname('/');
    });

    afterEach(() => {
        ReactDOM.unmountComponentAtNode(container);
        container.remove();
    });

    it('subscribes to route changes', () => {
        act(() => {
            ReactDOM.render(
                React.createElement(RouteRenderer, { area: 'server', registry }),
                container,
            );
        });

        const TestPage = () => React.createElement('div', { 'data-testid': 'test-page' }, 'Test Page');

        act(() => {
            registry.registerRoute('server', {
                extensionId: 'acme/test',
                path: '/test',
                name: 'Test',
                component: TestPage,
            });
        });

        // Route is registered but path doesn't match yet
        expect(container.querySelector('[data-testid="test-page"]')).toBeNull();
    });

    it('listens to popstate events', () => {
        const TestPage = () => React.createElement('div', { 'data-testid': 'my-page' }, 'My Page');

        registry.registerRoute('server', {
            extensionId: 'acme/test',
            path: '/page',
            name: 'Page',
            component: TestPage,
        });

        act(() => {
            ReactDOM.render(
                React.createElement(RouteRenderer, { area: 'server', registry }),
                container,
            );
        });

        // Change location and trigger popstate
        setPathname('/notur/acme/test/page');

        act(() => {
            window.dispatchEvent(new PopStateEvent('popstate'));
        });

        expect(container.querySelector('[data-testid="my-page"]')).not.toBeNull();
    });

    it('builds route path correctly', () => {
        const TestComponent = () => React.createElement('div', { 'data-testid': 'built-path' }, 'Built');

        registry.registerRoute('server', {
            extensionId: 'vendor/extension',
            path: '/settings',
            name: 'Settings',
            component: TestComponent,
        });

        setPathname('/notur/vendor/extension/settings');

        act(() => {
            ReactDOM.render(
                React.createElement(RouteRenderer, { area: 'server', registry }),
                container,
            );
        });

        expect(container.querySelector('[data-testid="built-path"]')).not.toBeNull();
    });

    it('matches exact path', () => {
        const ExactComponent = () => React.createElement('div', { 'data-testid': 'exact' }, 'Exact');

        registry.registerRoute('server', {
            extensionId: 'acme/test',
            path: '/exact',
            name: 'Exact',
            component: ExactComponent,
        });

        setPathname('/notur/acme/test/exact');

        act(() => {
            ReactDOM.render(
                React.createElement(RouteRenderer, { area: 'server', registry }),
                container,
            );
        });

        expect(container.querySelector('[data-testid="exact"]')).not.toBeNull();
    });

    it('matches nested path', () => {
        const ParentComponent = () => React.createElement('div', { 'data-testid': 'parent' }, 'Parent');

        registry.registerRoute('server', {
            extensionId: 'acme/test',
            path: '/parent',
            name: 'Parent',
            component: ParentComponent,
        });

        // Navigate to nested path
        setPathname('/notur/acme/test/parent/child/grandchild');

        act(() => {
            ReactDOM.render(
                React.createElement(RouteRenderer, { area: 'server', registry }),
                container,
            );
        });

        expect(container.querySelector('[data-testid="parent"]')).not.toBeNull();
    });

    it('filters routes by area', () => {
        const ServerRoute = () => React.createElement('div', { 'data-testid': 'server-route' }, 'Server');
        const DashboardRoute = () => React.createElement('div', { 'data-testid': 'dashboard-route' }, 'Dashboard');

        registry.registerRoute('server', {
            extensionId: 'acme/test',
            path: '/route',
            name: 'Server Route',
            component: ServerRoute,
        });

        registry.registerRoute('dashboard', {
            extensionId: 'acme/test',
            path: '/route',
            name: 'Dashboard Route',
            component: DashboardRoute,
        });

        setPathname('/notur/acme/test/route');

        // Render server area
        act(() => {
            ReactDOM.render(
                React.createElement(RouteRenderer, { area: 'server', registry }),
                container,
            );
        });

        expect(container.querySelector('[data-testid="server-route"]')).not.toBeNull();
        expect(container.querySelector('[data-testid="dashboard-route"]')).toBeNull();
    });

    it('returns null when no match', () => {
        const TestRoute = () => React.createElement('div', { 'data-testid': 'test' }, 'Test');

        registry.registerRoute('server', {
            extensionId: 'acme/test',
            path: '/specific',
            name: 'Specific',
            component: TestRoute,
        });

        setPathname('/different/path');

        act(() => {
            ReactDOM.render(
                React.createElement(RouteRenderer, { area: 'server', registry }),
                container,
            );
        });

        expect(container.querySelector('[data-testid="test"]')).toBeNull();
        expect(container.innerHTML).toBe('');
    });

    it('passes props to route component', () => {
        let receivedProps: any = null;
        const PropsComponent = (props: any) => {
            receivedProps = props;
            return React.createElement('div', { 'data-testid': 'props' }, 'Props');
        };

        registry.registerRoute('server', {
            extensionId: 'acme/test',
            path: '/props-test',
            name: 'Props Test',
            component: PropsComponent,
        });

        setPathname('/notur/acme/test/props-test');

        act(() => {
            ReactDOM.render(
                React.createElement(RouteRenderer, { area: 'server', registry }),
                container,
            );
        });

        expect(receivedProps).toMatchObject({
            extensionId: 'acme/test',
            currentPath: '/notur/acme/test/props-test',
        });
    });
});

describe('useRoutes', () => {
    let container: HTMLDivElement;
    let registry: PluginRegistry;

    function createHookRenderer(area: string) {
        const result: { current: any[] | null } = { current: null };

        function TestComponent() {
            result.current = useRoutes(area as any);
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

    it('returns area routes', () => {
        const Component = () => React.createElement('div');

        registry.registerRoute('server', {
            extensionId: 'acme/test',
            path: '/route1',
            name: 'Route 1',
            component: Component,
        });

        registry.registerRoute('server', {
            extensionId: 'acme/test',
            path: '/route2',
            name: 'Route 2',
            component: Component,
        });

        const { result, TestComponent } = createHookRenderer('server');

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        expect(result.current).toHaveLength(2);
        expect(result.current![0].path).toBe('/route1');
        expect(result.current![1].path).toBe('/route2');
    });
});
