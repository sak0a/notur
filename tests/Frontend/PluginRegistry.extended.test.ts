import { PluginRegistry } from '../../bridge/src/PluginRegistry';
import * as React from 'react';

/**
 * Extended tests for PluginRegistry focusing on theme system and event bus.
 * Complements the existing PluginRegistry.test.ts
 */
describe('PluginRegistry Extended', () => {
    let registry: PluginRegistry;

    beforeEach(() => {
        registry = new PluginRegistry();
    });

    describe('theme system', () => {
        it('registers theme with priority', () => {
            registry.registerTheme({
                extensionId: 'acme/theme',
                priority: 100,
                variables: {
                    '--primary-color': '#ff0000',
                },
            });

            const themes = registry.getThemeOverrides();
            expect(themes['--primary-color']).toBe('#ff0000');
        });

        it('higher priority themes override lower priority', () => {
            registry.registerTheme({
                extensionId: 'low-priority',
                priority: 10,
                variables: { '--color': 'blue' },
            });

            registry.registerTheme({
                extensionId: 'high-priority',
                priority: 100,
                variables: { '--color': 'red' },
            });

            // Themes are sorted by priority ascending, applied in order
            // so higher priority comes last and wins
            const merged = registry.getThemeOverrides();
            expect(merged['--color']).toBe('red');
        });

        it('merges theme variables', () => {
            registry.registerTheme({
                extensionId: 'theme-a',
                priority: 50,
                variables: {
                    '--color-a': 'red',
                    '--shared': 'from-a',
                },
            });

            registry.registerTheme({
                extensionId: 'theme-b',
                priority: 100, // Higher priority, applied after
                variables: {
                    '--color-b': 'blue',
                    '--shared': 'from-b', // Should win due to higher priority
                },
            });

            const merged = registry.getThemeOverrides();
            expect(merged['--color-a']).toBe('red');
            expect(merged['--color-b']).toBe('blue');
            expect(merged['--shared']).toBe('from-b'); // Higher priority wins
        });
    });

    describe('event bus', () => {
        it('emits and receives events', () => {
            const handler = jest.fn();

            registry.onEvent('test-event', handler);
            registry.emitEvent('test-event', { data: 'test-payload' });

            expect(handler).toHaveBeenCalledTimes(1);
            expect(handler).toHaveBeenCalledWith({ data: 'test-payload' });
        });

        it('supports multiple listeners', () => {
            const handler1 = jest.fn();
            const handler2 = jest.fn();

            registry.onEvent('multi-event', handler1);
            registry.onEvent('multi-event', handler2);
            registry.emitEvent('multi-event', { value: 42 });

            expect(handler1).toHaveBeenCalledWith({ value: 42 });
            expect(handler2).toHaveBeenCalledWith({ value: 42 });
        });

        it('unsubscribes correctly', () => {
            const handler = jest.fn();

            const unsubscribe = registry.onEvent('unsub-event', handler);
            registry.emitEvent('unsub-event', {});

            expect(handler).toHaveBeenCalledTimes(1);

            unsubscribe();
            registry.emitEvent('unsub-event', {});

            expect(handler).toHaveBeenCalledTimes(1); // Still 1, not called again
        });

        it('handles events with no listeners', () => {
            // Should not throw
            expect(() => {
                registry.emitEvent('no-listeners', { data: 'test' });
            }).not.toThrow();
        });

        it('isolates events by name', () => {
            const handler1 = jest.fn();
            const handler2 = jest.fn();

            registry.onEvent('event-a', handler1);
            registry.onEvent('event-b', handler2);

            registry.emitEvent('event-a', { from: 'a' });

            expect(handler1).toHaveBeenCalledWith({ from: 'a' });
            expect(handler2).not.toHaveBeenCalled();
        });
    });

    describe('extension cleanup', () => {
        it('cleans up themes on extension unregister', () => {
            const component = () => React.createElement('div');

            registry.registerExtension({
                id: 'acme/cleanup-test',
                name: 'Cleanup Test',
                version: '1.0.0',
                slots: [{ extensionId: 'acme/cleanup-test', slot: 'navbar' as any, component }],
                routes: [],
            });

            registry.registerTheme({
                extensionId: 'acme/cleanup-test',
                priority: 50,
                variables: { '--test': 'value' },
            });

            expect(registry.getThemeOverrides()['--test']).toBe('value');

            // Unregister extension
            registry.unregisterExtension('acme/cleanup-test');

            // Theme should be removed - merged object should be empty
            expect(Object.keys(registry.getThemeOverrides())).toHaveLength(0);
        });

        it('cleans up slots on extension unregister', () => {
            const component = () => React.createElement('div');

            registry.registerExtension({
                id: 'acme/slot-cleanup',
                name: 'Slot Cleanup',
                version: '1.0.0',
                slots: [
                    { extensionId: 'acme/slot-cleanup', slot: 'navbar' as any, component },
                    { extensionId: 'acme/slot-cleanup', slot: 'sidebar' as any, component },
                ],
                routes: [],
            });

            expect(registry.getSlot('navbar' as any)).toHaveLength(1);
            expect(registry.getSlot('sidebar' as any)).toHaveLength(1);

            registry.unregisterExtension('acme/slot-cleanup');

            expect(registry.getSlot('navbar' as any)).toHaveLength(0);
            expect(registry.getSlot('sidebar' as any)).toHaveLength(0);
        });

        it('cleans up routes on extension unregister', () => {
            const component = () => React.createElement('div');

            registry.registerExtension({
                id: 'acme/route-cleanup',
                name: 'Route Cleanup',
                version: '1.0.0',
                slots: [],
                routes: [
                    { extensionId: 'acme/route-cleanup', area: 'server', path: '/route1', name: 'Route 1', component },
                ],
            });

            expect(registry.getRoutes('server')).toHaveLength(1);

            registry.unregisterExtension('acme/route-cleanup');

            expect(registry.getRoutes('server')).toHaveLength(0);
        });
    });
});
