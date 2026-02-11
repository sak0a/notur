import * as React from 'react';
import { useServerContext } from '@notur/sdk';
import { useModFramework, FrameworkName, InstallResult } from '../api/useModFramework';
import { FrameworkCard } from './FrameworkCard';
import { ConfirmModal } from './ConfirmModal';

const { useState, useCallback } = React;

interface ModalState {
    open: boolean;
    action: 'install' | 'uninstall';
    framework: FrameworkName;
}

const FRAMEWORKS: Array<{
    key: FrameworkName;
    label: string;
    description: string;
    icon: string;
    dependency?: FrameworkName;
}> = [
    {
        key: 'swiftly',
        label: 'SwiftlyS2',
        description: 'High-performance CS2 plugin framework. Standalone — no Metamod required.',
        icon: '\u26A1',
    },
    {
        key: 'counterstrikesharp',
        label: 'CounterStrikeSharp',
        description: 'C# plugin framework for CS2 servers. Requires Metamod:Source.',
        icon: '\uD83D\uDD27',
        dependency: 'metamod' as FrameworkName,
    },
    {
        key: 'metamod',
        label: 'Metamod:Source',
        description: 'C++ plugin API for Source 2 engine. Required by CounterStrikeSharp.',
        icon: '\uD83D\uDCE6',
    },
];

const pageStyle: React.CSSProperties = {
    padding: '1.5rem',
    maxWidth: '900px',
    margin: '0 auto',
};

const headerStyle: React.CSSProperties = {
    marginBottom: '1.5rem',
};

const titleStyle: React.CSSProperties = {
    color: 'var(--notur-text-primary, #f0f0f0)',
    fontSize: '1.4rem',
    fontWeight: 700,
    margin: '0 0 0.35rem 0',
};

const subtitleStyle: React.CSSProperties = {
    color: 'var(--notur-text-secondary, #a0a8b4)',
    fontSize: '0.9rem',
    margin: 0,
};

const gridStyle: React.CSSProperties = {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))',
    gap: '1rem',
};

const spinnerContainerStyle: React.CSSProperties = {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    padding: '4rem 1rem',
    color: 'var(--notur-text-secondary, #a0a8b4)',
    fontSize: '0.9rem',
};

const errorBoxStyle: React.CSSProperties = {
    background: 'rgba(239, 68, 68, 0.1)',
    border: '1px solid rgba(239, 68, 68, 0.25)',
    borderRadius: '10px',
    padding: '1rem',
    color: '#ef4444',
    fontSize: '0.875rem',
    marginBottom: '1rem',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'space-between',
};

const dismissBtnStyle: React.CSSProperties = {
    background: 'transparent',
    border: 'none',
    color: '#ef4444',
    cursor: 'pointer',
    fontSize: '1.1rem',
    padding: '0 0.25rem',
};

const successBoxStyle: React.CSSProperties = {
    background: 'rgba(34, 197, 94, 0.1)',
    border: '1px solid rgba(34, 197, 94, 0.25)',
    borderRadius: '10px',
    padding: '1rem',
    color: '#22c55e',
    fontSize: '0.875rem',
    marginBottom: '1rem',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'space-between',
};

// Global keyframe style (injected once)
const STYLE_ID = 'notur-cs2-modframework-styles';

function ensureStyles(): void {
    if (document.getElementById(STYLE_ID)) return;
    const style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = `@keyframes notur-spin { to { transform: rotate(360deg); } }`;
    document.head.appendChild(style);
}

export const ModFrameworkPage: React.FC = () => {
    React.useEffect(() => { ensureStyles(); }, []);

    const server = useServerContext();

    if (!server) {
        return React.createElement('div', { style: spinnerContainerStyle }, 'Loading server context...');
    }

    return React.createElement(ModFrameworkPageInner, { serverUuid: server.uuid });
};

const ModFrameworkPageInner: React.FC<{ serverUuid: string }> = ({ serverUuid }) => {
    const {
        status,
        versions,
        statusLoading,
        actionLoading,
        error,
        fetchStatus,
        installFramework,
        uninstallFramework,
    } = useModFramework(serverUuid);

    const [modal, setModal] = useState<ModalState | null>(null);
    const [successMsg, setSuccessMsg] = useState<string | null>(null);

    const openModal = useCallback((action: 'install' | 'uninstall', framework: FrameworkName) => {
        setSuccessMsg(null);
        setModal({ open: true, action, framework });
    }, []);

    const closeModal = useCallback(() => setModal(null), []);

    const handleConfirm = useCallback(async () => {
        if (!modal) return;

        let result: InstallResult;
        if (modal.action === 'install') {
            result = await installFramework(modal.framework);
        } else {
            result = await uninstallFramework(modal.framework);
        }

        if (result.success) {
            setSuccessMsg(result.message);
        }
        closeModal();
    }, [modal, installFramework, uninstallFramework, closeModal]);

    const getModalTitle = (): string => {
        if (!modal) return '';
        const fw = FRAMEWORKS.find(f => f.key === modal.framework);
        const label = fw?.label || modal.framework;
        return modal.action === 'install' ? `Install ${label}` : `Uninstall ${label}`;
    };

    const getModalMessage = (): string => {
        if (!modal) return '';
        const fw = FRAMEWORKS.find(f => f.key === modal.framework);
        const label = fw?.label || modal.framework;

        if (modal.action === 'install') {
            return `This will download and install the latest version of ${label} on your server. The server should be stopped before installing.`;
        }
        return `This will remove ${label} and its files from your server. Any plugins or configurations within ${label} will be lost.`;
    };

    const getModalWarning = (): string | undefined => {
        if (!modal) return undefined;

        if (modal.action === 'install' && modal.framework === 'counterstrikesharp') {
            const metamodInstalled = status?.metamod?.installed ?? false;
            if (!metamodInstalled) {
                return 'Metamod:Source is not installed and will be automatically installed first, as CounterStrikeSharp requires it.';
            }
        }

        if (modal.action === 'uninstall' && modal.framework === 'metamod') {
            const cssInstalled = status?.counterstrikesharp?.installed ?? false;
            if (cssInstalled) {
                return 'CounterStrikeSharp is still installed and depends on Metamod. Please uninstall CounterStrikeSharp first.';
            }
        }

        return undefined;
    };

    if (statusLoading) {
        return React.createElement('div', { style: pageStyle },
            React.createElement('div', { style: headerStyle },
                React.createElement('h2', { style: titleStyle }, 'Mod Frameworks'),
                React.createElement('p', { style: subtitleStyle }, 'Manage CS2 modding frameworks on your server'),
            ),
            React.createElement('div', { style: spinnerContainerStyle }, 'Loading framework status...'),
        );
    }

    return React.createElement('div', { style: pageStyle },
        React.createElement('div', { style: headerStyle },
            React.createElement('h2', { style: titleStyle }, 'Mod Frameworks'),
            React.createElement('p', { style: subtitleStyle },
                'One-click install and manage CS2 modding frameworks. Stop your server before installing.',
            ),
        ),

        // Error banner
        error ? React.createElement('div', { style: errorBoxStyle },
            React.createElement('span', null, error),
            React.createElement('button', {
                style: dismissBtnStyle,
                onClick: fetchStatus,
                title: 'Retry',
            }, '\u21BB'),
        ) : null,

        // Success banner
        successMsg ? React.createElement('div', { style: successBoxStyle },
            React.createElement('span', null, successMsg),
            React.createElement('button', {
                style: { ...dismissBtnStyle, color: '#22c55e' },
                onClick: () => setSuccessMsg(null),
            }, '\u2715'),
        ) : null,

        // Framework cards
        React.createElement('div', { style: gridStyle },
            FRAMEWORKS.map(fw => {
                const fwStatus = status ? status[fw.key] : null;
                const fwVersion = versions ? versions[fw.key] : null;
                const depInstalled = fw.dependency ? (status?.[fw.dependency]?.installed ?? false) : undefined;
                const gameInfoOk = fw.key === 'counterstrikesharp' ? undefined
                    : status?.gameinfo_entries?.[fw.key];

                return React.createElement(FrameworkCard, {
                    key: fw.key,
                    framework: fw.key,
                    label: fw.label,
                    description: fw.description,
                    icon: fw.icon,
                    status: fwStatus,
                    version: fwVersion,
                    gameInfoOk,
                    dependency: fw.dependency ? (FRAMEWORKS.find(f => f.key === fw.dependency)?.label) : undefined,
                    dependencyInstalled: depInstalled,
                    isLoading: actionLoading === fw.key || (fw.key === 'metamod' && actionLoading === 'counterstrikesharp'),
                    onInstall: () => openModal('install', fw.key),
                    onUninstall: () => openModal('uninstall', fw.key),
                });
            }),
        ),

        // Confirmation modal
        React.createElement(ConfirmModal, {
            open: modal?.open ?? false,
            title: getModalTitle(),
            message: getModalMessage(),
            warning: getModalWarning(),
            confirmLabel: modal?.action === 'install' ? 'Install' : 'Uninstall',
            confirmColor: modal?.action === 'install' ? '#22c55e' : '#ef4444',
            loading: actionLoading !== null,
            onConfirm: handleConfirm,
            onCancel: closeModal,
        }),
    );
};
