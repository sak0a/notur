export declare const MAX_DIAGNOSTIC_ERRORS = 100;
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
export declare function recordDiagnosticError(error: DiagnosticError): void;
