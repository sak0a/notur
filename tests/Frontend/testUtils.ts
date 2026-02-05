import * as React from 'react';
import * as ReactDOM from 'react-dom';
import { act } from 'react-dom/test-utils';
import { PluginRegistry } from '../../bridge/src/PluginRegistry';

/**
 * Create a test container and append to document.body.
 */
export function createTestContainer(): HTMLDivElement {
    const container = document.createElement('div');
    document.body.appendChild(container);
    return container;
}

/**
 * Cleanup a test container.
 */
export function cleanupContainer(container: HTMLDivElement): void {
    ReactDOM.unmountComponentAtNode(container);
    container.remove();
}

/**
 * Mock window.location.pathname.
 */
export function mockWindowLocation(pathname: string): void {
    Object.defineProperty(window, 'location', {
        value: { pathname },
        writable: true,
        configurable: true,
    });
}

/**
 * Create a slot container element in the DOM.
 */
export function createSlotContainer(slotId: string): HTMLDivElement {
    const container = document.createElement('div');
    container.id = `notur-slot-${slotId}`;
    document.body.appendChild(container);
    return container;
}

/**
 * Set up the __NOTUR__ global with an optional registry.
 */
export function setupNoturGlobal(registry?: PluginRegistry): void {
    (window as any).__NOTUR__ = {
        registry: registry || new PluginRegistry(),
    };
}

/**
 * Clean up the __NOTUR__ global.
 */
export function cleanupNoturGlobal(): void {
    delete (window as any).__NOTUR__;
}

/**
 * Helper to capture hook result via a wrapper component.
 */
export function createHookRenderer<T>(hookFn: () => T) {
    const result: { current: T | null } = { current: null };

    function TestComponent() {
        result.current = hookFn();
        return null;
    }

    return { result, TestComponent };
}

/**
 * Helper to render a component and return cleanup function.
 */
export function renderComponent(
    container: HTMLDivElement,
    Component: React.ComponentType<any>,
    props?: Record<string, any>,
): void {
    act(() => {
        ReactDOM.render(
            React.createElement(Component, props),
            container,
        );
    });
}

/**
 * Mock the #app element with server data.
 */
export function mockAppElement(data: { server?: object; user?: object }): HTMLDivElement {
    const appEl = document.createElement('div');
    appEl.id = 'app';
    if (data.server) {
        appEl.dataset.server = JSON.stringify(data.server);
    }
    if (data.user) {
        appEl.dataset.user = JSON.stringify(data.user);
    }
    document.body.appendChild(appEl);
    return appEl;
}

/**
 * Wait for the next tick.
 */
export function nextTick(): Promise<void> {
    return new Promise(resolve => setTimeout(resolve, 0));
}

/**
 * Create a mock React component.
 */
export function createMockComponent(name: string): React.FC {
    return () => React.createElement('div', { 'data-testid': name }, name);
}
