import * as React from 'react';
import { PluginRegistry } from '../../bridge/src/PluginRegistry';

// Note: Full SlotRenderer tests require a DOM environment with portals.
// These tests verify the registry integration that SlotRenderer depends on.

describe('SlotRenderer integration', () => {
    let registry: PluginRegistry;

    beforeEach(() => {
        registry = new PluginRegistry();
    });

    it('provides registrations for slot rendering', () => {
        const TestWidget: React.FC = () => React.createElement('div', null, 'Widget');

        registry.registerSlot({
            extensionId: 'acme/test',
            slot: 'dashboard.widgets' as any,
            component: TestWidget,
            order: 10,
        });

        const registrations = registry.getSlot('dashboard.widgets' as any);
        expect(registrations).toHaveLength(1);
        expect(registrations[0].component).toBe(TestWidget);
    });

    it('maintains order across multiple extensions', () => {
        const Widget1: React.FC = () => React.createElement('div', null, 'W1');
        const Widget2: React.FC = () => React.createElement('div', null, 'W2');
        const Widget3: React.FC = () => React.createElement('div', null, 'W3');

        registry.registerSlot({ extensionId: 'ext-c', slot: 'dashboard.widgets' as any, component: Widget3, order: 30 });
        registry.registerSlot({ extensionId: 'ext-a', slot: 'dashboard.widgets' as any, component: Widget1, order: 10 });
        registry.registerSlot({ extensionId: 'ext-b', slot: 'dashboard.widgets' as any, component: Widget2, order: 20 });

        const registrations = registry.getSlot('dashboard.widgets' as any);
        expect(registrations.map(r => r.extensionId)).toEqual(['ext-a', 'ext-b', 'ext-c']);
    });

    it('supports route-type slots', () => {
        const Page: React.FC = () => React.createElement('div', null, 'Page');

        registry.registerRoute('server', {
            extensionId: 'acme/test',
            path: '/analytics',
            name: 'Analytics',
            component: Page,
        });

        const routes = registry.getRoutes('server');
        expect(routes).toHaveLength(1);
        expect(routes[0].path).toBe('/analytics');
    });

    it('isolates slots by ID', () => {
        const Comp: React.FC = () => React.createElement('div');

        registry.registerSlot({ extensionId: 'a', slot: 'navbar' as any, component: Comp });
        registry.registerSlot({ extensionId: 'b', slot: 'dashboard.widgets' as any, component: Comp });

        expect(registry.getSlot('navbar' as any)).toHaveLength(1);
        expect(registry.getSlot('dashboard.widgets' as any)).toHaveLength(1);
        expect(registry.getSlot('server.subnav' as any)).toHaveLength(0);
    });
});
