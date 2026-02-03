import * as React from 'react';
import { PluginRegistry, RouteRegistration } from './PluginRegistry';

interface RouteRendererProps {
    area: RouteRegistration['area'];
    registry: PluginRegistry;
    /** Base path prefix for all routes in this area */
    basePath?: string;
}

interface RouteRendererState {
    routes: RouteRegistration[];
    currentPath: string;
}

/**
 * Renders the matching route component from registered extension routes.
 *
 * Listens for route registration changes and URL changes (popstate).
 * Matches the current path against registered route paths with a
 * `/notur/{extensionId}/{path}` prefix convention.
 *
 * This component does NOT depend on react-router-dom — it performs its
 * own simple path matching so the bridge stays dependency-free.
 */
export class RouteRenderer extends React.Component<RouteRendererProps, RouteRendererState> {
    private unsubscribeRoute?: () => void;
    private unsubscribeExt?: () => void;

    constructor(props: RouteRendererProps) {
        super(props);
        this.state = {
            routes: props.registry.getRoutes(props.area),
            currentPath: window.location.pathname,
        };
    }

    componentDidMount(): void {
        const { registry, area } = this.props;

        this.unsubscribeRoute = registry.on('route:' + area, () => {
            this.setState({ routes: registry.getRoutes(area) });
        });

        this.unsubscribeExt = registry.on('extension:registered', () => {
            this.setState({ routes: registry.getRoutes(area) });
        });

        window.addEventListener('popstate', this.handlePopState);
    }

    componentWillUnmount(): void {
        this.unsubscribeRoute?.();
        this.unsubscribeExt?.();
        window.removeEventListener('popstate', this.handlePopState);
    }

    private handlePopState = (): void => {
        this.setState({ currentPath: window.location.pathname });
    };

    /**
     * Build the full route path for an extension route.
     */
    private buildRoutePath(route: RouteRegistration): string {
        const base = this.props.basePath || '';
        const extPath = route.path.startsWith('/') ? route.path : '/' + route.path;
        return `${base}/notur/${route.extensionId}${extPath}`;
    }

    /**
     * Simple path matching — checks if currentPath starts with the route path.
     * Supports exact match and prefix match for nested routes.
     */
    private matchRoute(route: RouteRegistration): boolean {
        const routePath = this.buildRoutePath(route);
        const { currentPath } = this.state;

        return currentPath === routePath || currentPath.startsWith(routePath + '/');
    }

    render(): React.ReactNode {
        const { routes, currentPath } = this.state;

        for (const route of routes) {
            if (this.matchRoute(route)) {
                const Component = route.component;
                return React.createElement(
                    'div',
                    { className: 'notur-surface notur-surface--page notur-surface--route' },
                    React.createElement(Component, {
                        extensionId: route.extensionId,
                        currentPath,
                    }),
                );
            }
        }

        return null;
    }
}

/**
 * Functional hook for accessing registered routes in an area.
 */
export function useRoutes(area: RouteRegistration['area']): RouteRegistration[] {
    const [routes, setRoutes] = React.useState<RouteRegistration[]>([]);

    React.useEffect(() => {
        const notur = (window as any).__NOTUR__;
        if (!notur?.registry) return;

        setRoutes(notur.registry.getRoutes(area));

        const unsub1 = notur.registry.on('route:' + area, () => {
            setRoutes(notur.registry.getRoutes(area));
        });
        const unsub2 = notur.registry.on('extension:registered', () => {
            setRoutes(notur.registry.getRoutes(area));
        });

        return () => {
            unsub1();
            unsub2();
        };
    }, [area]);

    return routes;
}
