import * as React from 'react';
import { FrameworkStatus, VersionInfo, FrameworkName, FrameworkVersionCatalog } from '../api/useModFramework';

interface FrameworkCardProps {
    framework: FrameworkName;
    label: string;
    description: string;
    icon: string;
    status: FrameworkStatus | null;
    catalog: FrameworkVersionCatalog | null;
    selectedVersion?: string;
    gameInfoOk?: boolean;
    dependency?: string;
    dependencyInstalled?: boolean;
    isLoading: boolean;
    disabledReason?: string;
    onVersionChange?: (version: string) => void;
    onInstall: () => void;
    onUpgrade: () => void;
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

const updateBadge: React.CSSProperties = {
    ...baseBadgeStyle,
    background: 'rgba(245, 158, 11, 0.12)',
    color: '#f59e0b',
    border: '1px solid rgba(245, 158, 11, 0.2)',
};

const currentBadge: React.CSSProperties = {
    ...baseBadgeStyle,
    background: 'rgba(14, 165, 233, 0.1)',
    color: '#38bdf8',
    border: '1px solid rgba(14, 165, 233, 0.2)',
};

const versionTextStyle: React.CSSProperties = {
    color: 'var(--notur-text-secondary, #a0a8b4)',
    fontSize: '0.78rem',
};

const versionSelectStyle: React.CSSProperties = {
    width: '150px',
    padding: '0.4rem 0.55rem',
    borderRadius: '6px',
    border: '1px solid var(--notur-border, rgba(255, 255, 255, 0.12))',
    background: 'rgba(15, 23, 42, 0.35)',
    color: 'var(--notur-text-primary, #f0f0f0)',
    fontSize: '0.78rem',
};

const linkStyle: React.CSSProperties = {
    color: '#38bdf8',
    fontSize: '0.76rem',
    fontWeight: 600,
    textDecoration: 'none',
};

const footerStyle: React.CSSProperties = {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: '1rem',
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
    display: 'flex',
    alignItems: 'center',
    gap: '6px',
};

const upgradeBtnStyle: React.CSSProperties = {
    ...installBtnStyle,
    background: '#f59e0b',
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
    catalog,
    selectedVersion,
    gameInfoOk,
    dependency,
    dependencyInstalled,
    isLoading,
    disabledReason,
    onVersionChange,
    onInstall,
    onUpgrade,
    onUninstall,
}) => {
    const installed = status?.installed ?? false;
    const disabled = Boolean(disabledReason);
    const latest = catalog?.latest ?? null;
    const versions = catalog?.versions && catalog.versions.length > 0
        ? catalog.versions
        : (latest ? [latest] : []);
    const installedVersion = status?.installed_version ?? null;
    const selected = selectedVersion ?? latest?.version ?? versions[0]?.version ?? '';
    const updateAvailable = Boolean(installed && installedVersion && latest && installedVersion !== latest.version);
    const selectedDiffersFromInstalled = Boolean(installed && installedVersion && selected && installedVersion !== selected);

    const iconStyle: React.CSSProperties = {
        ...iconContainerStyle,
        background: ICON_COLORS[framework] || 'rgba(148, 163, 184, 0.1)',
        border: `1px solid ${ICON_BORDER_COLORS[framework] || 'rgba(148, 163, 184, 0.15)'}`,
    };

    const needsDep = dependency && !dependencyInstalled && !installed;

    return React.createElement('div', { style: cardStyle },
        React.createElement('div', { style: headerStyle },
            React.createElement('div', { style: iconStyle }, icon),
            React.createElement('div', null,
                React.createElement('h4', { style: titleStyle }, label),
                React.createElement('p', { style: descStyle }, description),
            ),
        ),

        React.createElement('div', { style: badgeRowStyle },
            installed
                ? React.createElement('span', { style: installedBadge }, '\u2713 Installed')
                : React.createElement('span', { style: notInstalledBadge }, 'Not Installed'),
            installed && installedVersion
                ? React.createElement('span', { style: updateAvailable ? updateBadge : currentBadge },
                    updateAvailable ? `Update: v${latest?.version}` : 'Up to date',
                )
                : null,
            installed && !installedVersion
                ? React.createElement('span', { style: updateBadge }, 'Version unknown')
                : null,
            dependency
                ? React.createElement('span', { style: depBadge },
                    dependencyInstalled ? '\u2713 ' : '',
                    'Requires ', dependency,
                )
                : null,
            installed && gameInfoOk === false
                ? React.createElement('span', { style: updateBadge }, 'gameinfo.gi entry missing')
                : null,
            installed && status?.restart_required
                ? React.createElement('span', { style: currentBadge }, 'Restart required')
                : null,
        ),

        React.createElement('div', { style: footerStyle },
            React.createElement('div', { style: { display: 'flex', flexDirection: 'column', gap: '0.35rem' } },
                React.createElement('span', { style: versionTextStyle },
                    installedVersion
                        ? `Installed: v${installedVersion}`
                        : installed
                            ? 'Installed: unknown'
                            : latest
                                ? `Latest: v${latest.version}`
                                : 'Latest unavailable',
                ),
                versions.length > 0
                    ? React.createElement('select', {
                        style: versionSelectStyle,
                        value: selected,
                        onChange: (e: React.ChangeEvent<HTMLSelectElement>) => onVersionChange?.(e.target.value),
                        disabled: isLoading || disabled,
                        title: installed ? 'Version to install or upgrade to' : 'Version to install',
                        'aria-label': `${label} version`,
                    }, versions.map((item: VersionInfo) => React.createElement('option', {
                        key: item.version,
                        value: item.version,
                    }, item.version === latest?.version ? `${item.version} (latest)` : item.version)))
                    : null,
                catalog?.project_url
                    ? React.createElement('a', {
                        style: linkStyle,
                        href: catalog.project_url,
                        target: '_blank',
                        rel: 'noreferrer',
                    }, 'Project link')
                    : null,
            ),
            isLoading
                ? React.createElement('button', { style: disabledBtnStyle, disabled: true }, 'Working...')
                : disabled
                    ? React.createElement('button', {
                        style: disabledBtnStyle,
                        disabled: true,
                        title: disabledReason,
                    }, installed ? 'Unavailable' : 'Install')
                : installed
                    ? React.createElement('div', { style: { display: 'flex', gap: '0.5rem', flexWrap: 'wrap', justifyContent: 'flex-end' } },
                        updateAvailable || selectedDiffersFromInstalled || !installedVersion
                            ? React.createElement('button', {
                                style: upgradeBtnStyle,
                                onClick: onUpgrade,
                                disabled: !selected,
                            }, installedVersion ? 'Upgrade' : 'Install version')
                            : null,
                        React.createElement('button', {
                            style: uninstallBtnStyle,
                            onClick: onUninstall,
                        }, 'Uninstall'),
                    )
                    : React.createElement('button', {
                        style: installBtnStyle,
                        onClick: onInstall,
                        title: needsDep ? `${dependency} will be auto-installed` : undefined,
                    }, 'Install'),
        ),
    );
};
