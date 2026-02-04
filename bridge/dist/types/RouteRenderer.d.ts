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
export declare class RouteRenderer extends React.Component<RouteRendererProps, RouteRendererState> {
    private unsubscribeRoute?;
    private unsubscribeExt?;
    constructor(props: RouteRendererProps);
    componentDidMount(): void;
    componentWillUnmount(): void;
    private handlePopState;
    /**
     * Build the full route path for an extension route.
     */
    private buildRoutePath;
    /**
     * Simple path matching — checks if currentPath starts with the route path.
     * Supports exact match and prefix match for nested routes.
     */
    private matchRoute;
    render(): React.ReactNode;
}
/**
 * Functional hook for accessing registered routes in an area.
 */
export declare function useRoutes(area: RouteRegistration['area']): RouteRegistration[];
export {};
