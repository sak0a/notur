import * as React from 'react';
import { createExtension, createScopedEventChannel } from '@notur/sdk';

const channel = createScopedEventChannel('notur/full-extension');

const Widget: React.FC = () => {
    React.useEffect(() => {
        channel.emit('widget-mounted', { at: new Date().toISOString() });
    }, []);

    return (
        <div style={{ padding: '1rem', border: '1px solid var(--notur-border)' }}>
            <h3>Full Example Widget</h3>
            <p>Rendered from notur/full-extension.</p>
        </div>
    );
};

createExtension({
    id: 'notur/full-extension',
    slots: [
        {
            slot: 'dashboard.widgets',
            component: Widget,
            order: 50,
        },
    ],
    routes: [
        {
            area: 'dashboard',
            path: '/full-example',
            name: 'Full Example',
            component: Widget,
            permission: 'notur.full-example.view',
        },
    ],
});
