export const MAX_DIAGNOSTIC_ERRORS = 100;

export interface DiagnosticError {
    extensionId: string;
    message: string;
    stack?: string;
    componentStack?: string;
    time: string;
}

/**
 * Record a diagnostic error to the Notur diagnostics array.
 * Keeps the array bounded to MAX_DIAGNOSTIC_ERRORS entries,
 * dropping the oldest when full.
 */
export function recordDiagnosticError(error: DiagnosticError): void {
    const notur = (window as any).__NOTUR__;
    if (!notur?.diagnostics?.errors) return;

    notur.diagnostics.errors.push(error);

    if (notur.diagnostics.errors.length > MAX_DIAGNOSTIC_ERRORS) {
        notur.diagnostics.errors.splice(0, notur.diagnostics.errors.length - MAX_DIAGNOSTIC_ERRORS);
    }
}
