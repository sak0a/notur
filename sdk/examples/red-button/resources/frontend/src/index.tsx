import * as React from 'react';
import { createExtension } from '@notur/sdk';

const RedButton: React.FC = () => {
    return (
        <button
            style={{
                background: '#dc2626',
                color: '#fff',
                border: 0,
                borderRadius: '6px',
                padding: '8px 12px',
                fontWeight: 600,
                cursor: 'pointer',
            }}
            onClick={() => alert('Hello from Notur')}
        >
            Red Button
        </button>
    );
};

createExtension({
    id: 'acme/red-button',
    slots: [
        {
            slot: 'server.header',
            component: RedButton,
            order: 10,
        },
    ],
});
