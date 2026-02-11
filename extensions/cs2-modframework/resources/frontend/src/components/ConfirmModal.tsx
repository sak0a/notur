import * as React from 'react';

interface ConfirmModalProps {
    open: boolean;
    title: string;
    message: string;
    warning?: string;
    confirmLabel?: string;
    confirmColor?: string;
    loading?: boolean;
    onConfirm: () => void;
    onCancel: () => void;
}

const overlayStyle: React.CSSProperties = {
    position: 'fixed',
    inset: 0,
    background: 'rgba(0, 0, 0, 0.6)',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    zIndex: 9999,
    backdropFilter: 'blur(2px)',
};

const modalStyle: React.CSSProperties = {
    background: 'var(--notur-bg-secondary, #1a1f2e)',
    borderRadius: 'var(--notur-radius-md, 12px)',
    border: '1px solid var(--notur-border, rgba(255, 255, 255, 0.08))',
    padding: '1.5rem',
    maxWidth: '440px',
    width: '90%',
    boxShadow: '0 20px 60px rgba(0, 0, 0, 0.4)',
};

const titleStyle: React.CSSProperties = {
    color: 'var(--notur-text-primary, #f0f0f0)',
    fontSize: '1.1rem',
    fontWeight: 600,
    margin: '0 0 0.75rem 0',
};

const messageStyle: React.CSSProperties = {
    color: 'var(--notur-text-secondary, #a0a8b4)',
    fontSize: '0.9rem',
    lineHeight: 1.5,
    margin: '0 0 1rem 0',
};

const warningStyle: React.CSSProperties = {
    background: 'rgba(245, 158, 11, 0.1)',
    border: '1px solid rgba(245, 158, 11, 0.3)',
    borderRadius: '8px',
    padding: '0.75rem',
    color: '#f59e0b',
    fontSize: '0.85rem',
    lineHeight: 1.4,
    margin: '0 0 1rem 0',
};

const buttonRowStyle: React.CSSProperties = {
    display: 'flex',
    justifyContent: 'flex-end',
    gap: '0.75rem',
};

const cancelBtnStyle: React.CSSProperties = {
    padding: '0.5rem 1rem',
    borderRadius: '8px',
    border: '1px solid var(--notur-border, rgba(255, 255, 255, 0.1))',
    background: 'transparent',
    color: 'var(--notur-text-secondary, #a0a8b4)',
    cursor: 'pointer',
    fontSize: '0.875rem',
    fontWeight: 500,
};

const confirmBtnBase: React.CSSProperties = {
    padding: '0.5rem 1.25rem',
    borderRadius: '8px',
    border: 'none',
    color: '#fff',
    cursor: 'pointer',
    fontSize: '0.875rem',
    fontWeight: 600,
    minWidth: '100px',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    gap: '0.5rem',
};

export const ConfirmModal: React.FC<ConfirmModalProps> = ({
    open,
    title,
    message,
    warning,
    confirmLabel = 'Confirm',
    confirmColor = '#ef4444',
    loading = false,
    onConfirm,
    onCancel,
}) => {
    if (!open) return null;

    const confirmBtnStyle: React.CSSProperties = {
        ...confirmBtnBase,
        background: confirmColor,
        opacity: loading ? 0.7 : 1,
        pointerEvents: loading ? 'none' : 'auto',
    };

    return React.createElement('div', { style: overlayStyle, onClick: loading ? undefined : onCancel },
        React.createElement('div', {
            style: modalStyle,
            onClick: (e: React.MouseEvent) => e.stopPropagation(),
        },
            React.createElement('h3', { style: titleStyle }, title),
            React.createElement('p', { style: messageStyle }, message),
            warning ? React.createElement('div', { style: warningStyle }, warning) : null,
            React.createElement('div', { style: buttonRowStyle },
                React.createElement('button', {
                    style: cancelBtnStyle,
                    onClick: onCancel,
                    disabled: loading,
                }, 'Cancel'),
                React.createElement('button', {
                    style: confirmBtnStyle,
                    onClick: onConfirm,
                    disabled: loading,
                },
                    loading ? React.createElement('span', null, 'Working...') : confirmLabel,
                ),
            ),
        ),
    );
};
