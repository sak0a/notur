import { PluginRegistry } from '../../bridge/src/PluginRegistry';
import * as React from 'react';

describe('PluginRegistry', () => {
    let registry: PluginRegistry;

    beforeEach(() => {
        registry = new PluginRegistry();
    });

    describe('registerSlot', () => {
        it('registers a slot with a component', () => {
            const component = () => React.createElement('div', null, 'test');

            registry.registerSlot({
                extensionId: 'acme/test',
                slot: 'dashboard.widgets' as any,
                component,
                order: 10,
            });

            const slots = registry.getSlot('dashboard.widgets' as any);
            expect(slots).toHaveLength(1);
            expect(slots[0].extensionId).toBe('acme/test');
            expect(slots[0].component).toBe(component);
            expect(slots[0].order).toBe(10);
        });

        it('sorts slots by order', () => {
            const comp1 = () => React.createElement('div', null, '1');
            const comp2 = () => React.createElement('div', null, '2');
            const comp3 = () => React.createElement('div', null, '3');

            registry.registerSlot({ extensionId: 'a', slot: 'navbar' as any, component: comp1, order: 30 });
            registry.registerSlot({ extensionId: 'b', slot: 'navbar' as any, component: comp2, order: 10 });
            registry.registerSlot({ extensionId: 'c', slot: 'navbar' as any, component: comp3, order: 20 });

            const slots = registry.getSlot('navbar' as any);
            expect(slots[0].extensionId).toBe('b');
            expect(slots[1].extensionId).toBe('c');
            expect(slots[2].extensionId).toBe('a');
        });

        it('notifies listeners on slot registration', () => {
            const listener = jest.fn();
            registry.on('slot:dashboard.widgets', listener);

            registry.registerSlot({
                extensionId: 'acme/test',
                slot: 'dashboard.widgets' as any,
                component: () => React.createElement('div'),
            });

            expect(listener).toHaveBeenCalledTimes(1);
        });
    });

    describe('registerRoute', () => {
        it('registers a route in an area', () => {
            const component = () => React.createElement('div', null, 'page');

            registry.registerRoute('server', {
                extensionId: 'acme/test',
                path: '/analytics',
                name: 'Analytics',
                component,
            });

            const routes = registry.getRoutes('server');
            expect(routes).toHaveLength(1);
            expect(routes[0].path).toBe('/analytics');
            expect(routes[0].name).toBe('Analytics');
        });

        it('filters routes by area', () => {
            const component = () => React.createElement('div');

            registry.registerRoute('server', { path: '/a', name: 'A', component });
            registry.registerRoute('dashboard', { path: '/b', name: 'B', component });

            expect(registry.getRoutes('server')).toHaveLength(1);
            expect(registry.getRoutes('dashboard')).toHaveLength(1);
            expect(registry.getRoutes('account')).toHaveLength(0);
        });
    });

    describe('registerExtension', () => {
        it('registers a complete extension', () => {
            const component = () => React.createElement('div');

            registry.registerExtension({
                id: 'acme/full',
                name: 'Full Extension',
                version: '1.0.0',
                slots: [
                    { extensionId: 'acme/full', slot: 'navbar' as any, component, order: 5 },
                ],
                routes: [
                    { extensionId: 'acme/full', area: 'server', path: '/test', name: 'Test', component },
                ],
            });

            const extensions = registry.getExtensions();
            expect(extensions).toHaveLength(1);
            expect(extensions[0].id).toBe('acme/full');

            expect(registry.getSlot('navbar' as any)).toHaveLength(1);
            expect(registry.getRoutes('server')).toHaveLength(1);
        });
    });

    describe('event system', () => {
        it('unsubscribes correctly', () => {
            const listener = jest.fn();
            const unsubscribe = registry.on('change', listener);

            registry.registerSlot({
                extensionId: 'a',
                slot: 'navbar' as any,
                component: () => React.createElement('div'),
            });

            expect(listener).toHaveBeenCalledTimes(1);

            unsubscribe();

            registry.registerSlot({
                extensionId: 'b',
                slot: 'navbar' as any,
                component: () => React.createElement('div'),
            });

            expect(listener).toHaveBeenCalledTimes(1);
        });
    });

    describe('getSlot', () => {
        it('returns empty array for unknown slot', () => {
            expect(registry.getSlot('nonexistent' as any)).toEqual([]);
        });
    });
});
