import { PluginRegistry } from '../../bridge/src/PluginRegistry';
import { createDevTools } from '../../bridge/src/DevTools';
import * as React from 'react';

describe('DevTools', () => {
    let registry: PluginRegistry;

    beforeEach(() => {
        registry = new PluginRegistry();
        (window as any).__NOTUR__ = {
            SLOT_IDS: { NAVBAR: 'navbar', DASHBOARD_WIDGETS: 'dashboard.widgets' },
            registry,
        };
    });

    afterEach(() => {
        delete (window as any).__NOTUR__;
        jest.restoreAllMocks();
    });

    it('creates a callable debug function', () => {
        const debug = createDevTools(registry);
        expect(typeof debug).toBe('function');
        expect(typeof debug.extensions).toBe('function');
        expect(typeof debug.slots).toBe('function');
        expect(typeof debug.routes).toBe('function');
        expect(typeof debug.theme).toBe('function');
        expect(typeof debug.events).toBe('function');
    });

    it('debug() logs a summary without throwing', () => {
        jest.spyOn(console, 'group').mockImplementation();
        jest.spyOn(console, 'groupEnd').mockImplementation();
        jest.spyOn(console, 'log').mockImplementation();

        const debug = createDevTools(registry);
        expect(() => debug()).not.toThrow();
    });

    it('debug.extensions() lists registered extensions', () => {
        const consoleSpy = jest.spyOn(console, 'log').mockImplementation();
        jest.spyOn(console, 'group').mockImplementation();
        jest.spyOn(console, 'groupEnd').mockImplementation();

        registry.registerExtension({
            id: 'test/ext',
            name: 'Test',
            version: '1.0.0',
            slots: [],
            routes: [],
        });

        const debug = createDevTools(registry);
        debug.extensions();

        expect(consoleSpy).toHaveBeenCalledWith(
            expect.stringContaining('test/ext'),
        );
    });

    it('debug.extensions() shows "(none)" when empty', () => {
        const consoleSpy = jest.spyOn(console, 'log').mockImplementation();
        jest.spyOn(console, 'group').mockImplementation();
        jest.spyOn(console, 'groupEnd').mockImplementation();

        const debug = createDevTools(registry);
        debug.extensions();

        expect(consoleSpy).toHaveBeenCalledWith(
            expect.stringContaining('(none)'),
        );
    });

    it('debug.slots() lists registered slots', () => {
        jest.spyOn(console, 'log').mockImplementation();
        jest.spyOn(console, 'group').mockImplementation();
        jest.spyOn(console, 'groupEnd').mockImplementation();

        const TestComp: React.FC = () => React.createElement('div');
        TestComp.displayName = 'TestWidget';

        registry.registerSlot({
            extensionId: 'test/ext',
            slot: 'navbar' as any,
            component: TestComp,
            order: 5,
        });

        const debug = createDevTools(registry);
        debug.slots();

        expect(console.log).toHaveBeenCalledWith(
            expect.stringContaining('test/ext'),
        );
    });

    it('debug.routes() lists registered routes', () => {
        jest.spyOn(console, 'log').mockImplementation();
        jest.spyOn(console, 'group').mockImplementation();
        jest.spyOn(console, 'groupEnd').mockImplementation();

        registry.registerRoute('server', {
            extensionId: 'test/ext',
            path: '/analytics',
            name: 'Analytics',
            component: () => React.createElement('div'),
        });

        const debug = createDevTools(registry);
        debug.routes();

        expect(console.log).toHaveBeenCalledWith(
            expect.stringContaining('/analytics'),
        );
    });

    it('debug.theme() lists theme overrides', () => {
        jest.spyOn(console, 'log').mockImplementation();
        jest.spyOn(console, 'group').mockImplementation();
        jest.spyOn(console, 'groupEnd').mockImplementation();

        registry.registerTheme({
            extensionId: 'test/ext',
            variables: { '--primary': '#ff0000' },
        });

        const debug = createDevTools(registry);
        debug.theme();

        expect(console.log).toHaveBeenCalledWith(
            expect.stringContaining('--primary'),
        );
    });

    it('debug.events() returns unsubscribe function', () => {
        jest.spyOn(console, 'log').mockImplementation();

        const debug = createDevTools(registry);
        const unsubscribe = debug.events();
        expect(typeof unsubscribe).toBe('function');
        unsubscribe(); // Should not throw
    });

    it('debug.events() logs registry events', () => {
        const consoleSpy = jest.spyOn(console, 'log').mockImplementation();
        jest.spyOn(console, 'group').mockImplementation();
        jest.spyOn(console, 'groupEnd').mockImplementation();

        const debug = createDevTools(registry);
        debug.events();

        // Trigger a change event by registering an extension
        registry.registerExtension({
            id: 'test/ext',
            name: 'Test',
            version: '1.0.0',
            slots: [],
            routes: [],
        });

        // Should have logged the event
        expect(consoleSpy).toHaveBeenCalledWith(
            expect.stringContaining('[Notur Event]'),
            expect.any(String),
        );
    });

    it('debug.events() stops logging after unsubscribe', () => {
        const consoleSpy = jest.spyOn(console, 'log').mockImplementation();
        jest.spyOn(console, 'group').mockImplementation();
        jest.spyOn(console, 'groupEnd').mockImplementation();

        const debug = createDevTools(registry);
        const unsubscribe = debug.events();

        // Unsubscribe before triggering events
        unsubscribe();
        consoleSpy.mockClear();

        // Trigger a change event
        registry.registerExtension({
            id: 'test/ext',
            name: 'Test',
            version: '1.0.0',
            slots: [],
            routes: [],
        });

        // Should NOT have logged any [Notur Event] messages
        const eventCalls = consoleSpy.mock.calls.filter(
            call => typeof call[0] === 'string' && call[0].includes('[Notur Event]'),
        );
        expect(eventCalls).toHaveLength(0);
    });
});
