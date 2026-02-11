import * as React from 'react';
import { FrameworkStatus, VersionInfo, FrameworkName } from '../api/useModFramework';

interface FrameworkCardProps {
    framework: FrameworkName;
    label: string;
    description: string;
    icon: string;
    status: FrameworkStatus | null;
    version: VersionInfo | null;
    gameInfoOk?: boolean;
    dependency?: string;
    dependencyInstalled?: boolean;
    isLoading: boolean;
    onInstall: () => void;
    onUninstall: () => void;
}

const cardStyle: React.CSSProperties = {
    background: 'var(--notur-bg-secondary, #1a1f2e)',
    borderRadius: 'var(--notur-radius-md, 12px)',
    border: '1px solid var(--notur-border, rgba(255, 255, 255, 0.08))',
    padding: '1.25rem',
    display: 'flex',
    flexDirection: 'column',
    gap: '1rem',
    transition: 'border-color 0.2s, box-shadow 0.2s',
};

const headerStyle: React.CSSProperties = {
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
};

const iconContainerStyle: React.CSSProperties = {
    width: '44px',
    height: '44px',
    borderRadius: '10px',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    fontSize: '1.5rem',
    flexShrink: 0,
};

const titleStyle: React.CSSProperties = {
    color: 'var(--notur-text-primary, #f0f0f0)',
    fontSize: '1rem',
    fontWeight: 600,
    margin: 0,
};

const descStyle: React.CSSProperties = {
    color: 'var(--notur-text-secondary, #a0a8b4)',
    fontSize: '0.8rem',
    margin: '2px 0 0 0',
    lineHeight: 1.3,
};

const badgeRowStyle: React.CSSProperties = {
    display: 'flex',
    gap: '0.5rem',
    flexWrap: 'wrap',
};

const baseBadgeStyle: React.CSSProperties = {
    fontSize: '0.75rem',
    fontWeight: 600,
    padding: '3px 10px',
    borderRadius: '6px',
    display: 'inline-flex',
    alignItems: 'center',
    gap: '4px',
};

const installedBadge: React.CSSProperties = {
    ...baseBadgeStyle,
    background: 'rgba(34, 197, 94, 0.12)',
    color: '#22c55e',
    border: '1px solid rgba(34, 197, 94, 0.2)',
};

const notInstalledBadge: React.CSSProperties = {
    ...baseBadgeStyle,
    background: 'rgba(148, 163, 184, 0.1)',
    color: '#94a3b8',
    border: '1px solid rgba(148, 163, 184, 0.15)',
};

const depBadge: React.CSSProperties = {
    ...baseBadgeStyle,
    background: 'rgba(99, 102, 241, 0.1)',
    color: '#818cf8',
    border: '1px solid rgba(99, 102, 241, 0.2)',
};

const versionTextStyle: React.CSSProperties = {
    color: 'var(--notur-text-secondary, #a0a8b4)',
    fontSize: '0.78rem',
};

const footerStyle: React.CSSProperties = {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: 'auto',
};

const installBtnStyle: React.CSSProperties = {
    padding: '0.5rem 1.25rem',
    borderRadius: '8px',
    border: 'none',
    background: '#22c55e',
    color: '#fff',
    cursor: 'pointer',
    fontSize: '0.85rem',
    fontWeight: 600,
    transition: 'opacity 0.15s',
    display: 'flex',
    alignItems: 'center',
    gap: '6px',
};

const uninstallBtnStyle: React.CSSProperties = {
    ...installBtnStyle,
    background: 'rgba(239, 68, 68, 0.15)',
    color: '#ef4444',
    border: '1px solid rgba(239, 68, 68, 0.25)',
};

const disabledBtnStyle: React.CSSProperties = {
    ...installBtnStyle,
    opacity: 0.5,
    cursor: 'not-allowed',
    background: 'rgba(148, 163, 184, 0.15)',
    color: '#94a3b8',
};

const ICON_COLORS: Record<string, string> = {
    swiftly: 'rgba(249, 115, 22, 0.15)',
    counterstrikesharp: 'rgba(99, 102, 241, 0.15)',
    metamod: 'rgba(14, 165, 233, 0.15)',
};

const ICON_BORDER_COLORS: Record<string, string> = {
    swiftly: 'rgba(249, 115, 22, 0.25)',
    counterstrikesharp: 'rgba(99, 102, 241, 0.25)',
    metamod: 'rgba(14, 165, 233, 0.25)',
};

export const FrameworkCard: React.FC<FrameworkCardProps> = ({
    framework,
    label,
    description,
    icon,
    status,
    version,
    gameInfoOk,
    dependency,
    dependencyInstalled,
    isLoading,
    onInstall,
    onUninstall,
}) => {
    const installed = status?.installed ?? false;

    const iconStyle: React.CSSProperties = {
        ...iconContainerStyle,
        background: ICON_COLORS[framework] || 'rgba(148, 163, 184, 0.1)',
        border: `1px solid ${ICON_BORDER_COLORS[framework] || 'rgba(148, 163, 184, 0.15)'}`,
    };

    const needsDep = dependency && !dependencyInstalled && !installed;

    return React.createElement('div', { style: cardStyle },
        // Header: icon + title
        React.createElement('div', { style: headerStyle },
            React.createElement('div', { style: iconStyle }, icon),
            React.createElement('div', null,
                React.createElement('h4', { style: titleStyle }, label),
                React.createElement('p', { style: descStyle }, description),
            ),
        ),

        // Badges
        React.createElement('div', { style: badgeRowStyle },
            installed
                ? React.createElement('span', { style: installedBadge }, '\u2713 Installed')
                : React.createElement('span', { style: notInstalledBadge }, 'Not Installed'),
            dependency
                ? React.createElement('span', { style: depBadge },
                    dependencyInstalled ? '\u2713 ' : '',
                    'Requires ', dependency,
                )
                : null,
            installed && gameInfoOk === false
                ? React.createElement('span', {
                    style: {
                        ...baseBadgeStyle,
                        background: 'rgba(245, 158, 11, 0.1)',
                        color: '#f59e0b',
                        border: '1px solid rgba(245, 158, 11, 0.2)',
                    },
                }, 'gameinfo.gi entry missing')
                : null,
        ),

        // Footer: version + action button
        React.createElement('div', { style: footerStyle },
            React.createElement('span', { style: versionTextStyle },
                version ? `Latest: v${version.version}` : '',
            ),
            isLoading
                ? React.createElement('button', { style: disabledBtnStyle, disabled: true },
                    React.createElement('span', {
                        style: {
                            display: 'inline-block',
                            width: '14px',
                            height: '14px',
                            border: '2px solid rgba(148, 163, 184, 0.3)',
                            borderTopColor: '#94a3b8',
                            borderRadius: '50%',
                            animation: 'notur-spin 0.6s linear infinite',
                        },
                    }),
                    'Working...',
                )
                : installed
                    ? React.createElement('button', {
                        style: uninstallBtnStyle,
                        onClick: onUninstall,
                    }, 'Uninstall')
                    : React.createElement('button', {
                        style: installBtnStyle,
                        onClick: onInstall,
                        title: needsDep ? `${dependency} will be auto-installed` : undefined,
                    }, 'Install'),
        ),
    );
};
