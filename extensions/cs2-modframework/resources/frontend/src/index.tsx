import * as React from 'react';
import { createExtension } from '@notur/sdk';
import { ModFrameworkPage } from './components/ModFrameworkPage';

// Sub-navigation link component for the server sidebar
const ModFrameworkNavLink: React.FC = () => {
    return React.createElement('a', {
        href: '#',
        onClick: (e: React.MouseEvent) => {
            e.preventDefault();
            // Navigate using Pterodactyl's router — the route is registered below
            const match = window.location.pathname.match(/\/server\/([a-f0-9-]+)/);
            if (match) {
                window.location.href = `/server/${match[1]}/mod-frameworks`;
            }
        },
        style: {
            display: 'flex',
            alignItems: 'center',
            gap: '0.5rem',
            padding: '0.5rem 0.75rem',
            color: 'var(--notur-text-secondary, #a0a8b4)',
            textDecoration: 'none',
            fontSize: '0.875rem',
            borderRadius: '6px',
            transition: 'color 0.15s, background 0.15s',
        },
    },
        React.createElement('span', { style: { fontSize: '1rem' } }, '\uD83E\uDDE9'),
        'Mod Frameworks',
    );
};

createExtension({
    id: 'notur/cs2-modframework',
    slots: [
        {
            slot: 'server.subnav.after',
            component: ModFrameworkNavLink,
            order: 50,
            when: { server: true },
        },
    ],
    routes: [
        {
            area: 'server',
            path: '/mod-frameworks',
            name: 'Mod Frameworks',
            component: ModFrameworkPage,
        },
    ],
});
