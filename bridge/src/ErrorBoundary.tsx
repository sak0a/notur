import * as React from 'react';

interface SlotErrorBoundaryProps {
    extensionId: string;
    children?: React.ReactNode;
}

interface SlotErrorBoundaryState {
    hasError: boolean;
    error: Error | null;
}

/**
 * Error boundary that catches render errors from extension slot components.
 * Displays a minimal fallback instead of crashing the entire slot.
 */
export class SlotErrorBoundary extends React.Component<SlotErrorBoundaryProps, SlotErrorBoundaryState> {
    constructor(props: SlotErrorBoundaryProps) {
        super(props);
        this.state = { hasError: false, error: null };
    }

    static getDerivedStateFromError(error: Error): SlotErrorBoundaryState {
        return { hasError: true, error };
    }

    componentDidCatch(error: Error, errorInfo: React.ErrorInfo): void {
        console.error(
            `[Notur] Extension "${this.props.extensionId}" component error:`,
            error,
            errorInfo.componentStack,
        );
    }

    render(): React.ReactNode {
        if (this.state.hasError) {
            return React.createElement('div', {
                style: {
                    padding: '8px 12px',
                    margin: '4px 0',
                    border: '1px solid rgba(239, 68, 68, 0.3)',
                    borderRadius: '4px',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    color: '#ef4444',
                    fontSize: '12px',
                    fontFamily: 'monospace',
                },
            }, `[Notur] Extension "${this.props.extensionId}" failed to render`);
        }

        return this.props.children;
    }
}
