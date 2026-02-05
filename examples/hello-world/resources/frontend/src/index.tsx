import * as React from 'react';
import { createExtension } from '@notur/sdk';

const HelloWidget: React.FC<{ extensionId: string }> = ({ extensionId }) => {
    const [greeting, setGreeting] = React.useState<string | null>(null);
    const [error, setError] = React.useState<string | null>(null);

    React.useEffect(() => {
        fetch(`/api/client/notur/${extensionId}/greet`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((r) => r.json())
            .then((data) => setGreeting(data.message))
            .catch((e) => setError(e.message));
    }, [extensionId]);

    return React.createElement(
        'div',
        {
            style: {
                padding: '1rem',
                margin: '1rem 0',
                background: 'var(--notur-bg-secondary, #323f4b)',
                borderRadius: 'var(--notur-radius-md, 8px)',
                border: '1px solid var(--notur-border, #3e4c59)',
            },
        },
        React.createElement(
            'h3',
            { style: { color: 'var(--notur-text-primary, #f5f7fa)', marginBottom: '0.5rem' } },
            'Hello World Extension'
        ),
        React.createElement(
            'p',
            { style: { color: 'var(--notur-text-secondary, #cbd2d9)' } },
            error ? `Error: ${error}` : greeting || 'Loading...'
        )
    );
};

const HelloPage: React.FC = () => {
    return React.createElement(
        'div',
        { style: { padding: '2rem', maxWidth: '800px', margin: '0 auto' } },
        React.createElement('h1', { style: { color: 'var(--notur-text-primary, #f5f7fa)' } }, 'Hello World'),
        React.createElement(
            'p',
            { style: { color: 'var(--notur-text-secondary, #cbd2d9)' } },
            'This is a full page rendered by the notur/hello-world extension.'
        )
    );
};

// Register the extension using the simplified syntax
createExtension({
    id: 'notur/hello-world',
    slots: [
        { slot: 'dashboard.widgets', component: HelloWidget, order: 100 },
    ],
    routes: [
        { area: 'dashboard', path: '/hello', name: 'Hello', component: HelloPage },
    ],
});
