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
export declare class SlotErrorBoundary extends React.Component<SlotErrorBoundaryProps, SlotErrorBoundaryState> {
    constructor(props: SlotErrorBoundaryProps);
    static getDerivedStateFromError(error: Error): SlotErrorBoundaryState;
    componentDidCatch(error: Error, errorInfo: React.ErrorInfo): void;
    render(): React.ReactNode;
}
export {};
