import { recordDiagnosticError, MAX_DIAGNOSTIC_ERRORS } from '../../bridge/src/diagnostics';

describe('recordDiagnosticError', () => {
    beforeEach(() => {
        (window as any).__NOTUR__ = {
            diagnostics: { errors: [] },
        };
    });

    afterEach(() => {
        delete (window as any).__NOTUR__;
    });

    it('records an error to the diagnostics array', () => {
        recordDiagnosticError({
            extensionId: 'acme/test',
            message: 'Test error',
            time: '2026-01-01T00:00:00.000Z',
        });

        const errors = (window as any).__NOTUR__.diagnostics.errors;
        expect(errors).toHaveLength(1);
        expect(errors[0].extensionId).toBe('acme/test');
        expect(errors[0].message).toBe('Test error');
    });

    it('bounds the array to MAX_DIAGNOSTIC_ERRORS entries', () => {
        const errors = (window as any).__NOTUR__.diagnostics.errors;

        // Fill to max
        for (let i = 0; i < MAX_DIAGNOSTIC_ERRORS + 10; i++) {
            recordDiagnosticError({
                extensionId: 'acme/test',
                message: `Error ${i}`,
                time: '2026-01-01T00:00:00.000Z',
            });
        }

        expect(errors.length).toBe(MAX_DIAGNOSTIC_ERRORS);
        // Oldest entries should have been spliced — first entry should be "Error 10"
        expect(errors[0].message).toBe('Error 10');
        expect(errors[errors.length - 1].message).toBe(`Error ${MAX_DIAGNOSTIC_ERRORS + 9}`);
    });

    it('handles missing window.__NOTUR__ gracefully', () => {
        delete (window as any).__NOTUR__;

        expect(() => {
            recordDiagnosticError({
                extensionId: 'acme/test',
                message: 'Should not throw',
                time: '2026-01-01T00:00:00.000Z',
            });
        }).not.toThrow();
    });

    it('handles missing diagnostics.errors gracefully', () => {
        (window as any).__NOTUR__ = {};

        expect(() => {
            recordDiagnosticError({
                extensionId: 'acme/test',
                message: 'Should not throw',
                time: '2026-01-01T00:00:00.000Z',
            });
        }).not.toThrow();
    });
});
