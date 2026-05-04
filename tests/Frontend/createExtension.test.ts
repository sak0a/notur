import { createExtension } from '../../sdk/src/createExtension';

// Mock the window.__NOTUR__ API
const mockRegisterExtension = vi.fn();
const mockRegisterDestroyCallback = vi.fn();

beforeEach(() => {
    (window as any).__NOTUR__ = {
        registry: {
            registerExtension: mockRegisterExtension,
            registerDestroyCallback: mockRegisterDestroyCallback,
        },
    };
    vi.clearAllMocks();
});

afterEach(() => {
    delete (window as any).__NOTUR__;
});

function DummyComponent() {
    return null;
}

describe('createExtension', () => {
    it('registers slots with the registry', () => {
        createExtension({
            config: { id: 'test/ext', name: 'Test', version: '1.0.0' },
            slots: [{ slot: 'navbar', component: DummyComponent, order: 5 }],
        });

        expect(mockRegisterExtension).toHaveBeenCalledWith(
            expect.objectContaining({
                slots: expect.arrayContaining([
                    expect.objectContaining({
                        slot: 'navbar',
                        extensionId: 'test/ext',
                        order: 5,
                    }),
                ]),
            }),
        );
    });

    it('registers multiple slots', () => {
        createExtension({
            config: { id: 'test/ext', name: 'Test', version: '1.0.0' },
            slots: [
                { slot: 'navbar', component: DummyComponent, order: 5 },
                { slot: 'dashboard.widgets', component: DummyComponent, order: 10 },
            ],
        });

        const call = mockRegisterExtension.mock.calls[0]?.[0];
        expect(call?.slots).toHaveLength(2);
    });

    it('registers routes with the registry', () => {
        createExtension({
            config: { id: 'test/ext', name: 'Test', version: '1.0.0' },
            routes: [{ area: 'server', path: '/stats', name: 'Stats', component: DummyComponent }],
        });

        expect(mockRegisterExtension).toHaveBeenCalledWith(
            expect.objectContaining({
                routes: expect.arrayContaining([
                    expect.objectContaining({
                        path: '/stats',
                        extensionId: 'test/ext',
                    }),
                ]),
            }),
        );
    });

    it('calls onInit callback', () => {
        const onInit = vi.fn();
        createExtension({
            config: { id: 'test/ext', name: 'Test', version: '1.0.0' },
            onInit,
        });

        expect(onInit).toHaveBeenCalled();
    });

    it('handles onInit errors gracefully', () => {
        const consoleSpy = vi.spyOn(console, 'error').mockImplementation();
        // Also suppress the console.log from createExtension
        vi.spyOn(console, 'log').mockImplementation();

        createExtension({
            config: { id: 'test/ext', name: 'Test', version: '1.0.0' },
            onInit: () => { throw new Error('Init failed'); },
        });

        expect(consoleSpy).toHaveBeenCalledWith(
            expect.stringContaining('test/ext'),
            expect.any(Error),
        );
        consoleSpy.mockRestore();
        vi.restoreAllMocks();
    });

    it('registers onDestroy callback', () => {
        const onDestroy = vi.fn();
        createExtension({
            config: { id: 'test/ext', name: 'Test', version: '1.0.0' },
            onDestroy,
        });

        expect(mockRegisterDestroyCallback).toHaveBeenCalledWith('test/ext', onDestroy);
    });

    it('does not register destroy callback when not provided', () => {
        // Suppress log output
        vi.spyOn(console, 'log').mockImplementation();

        createExtension({
            config: { id: 'test/ext', name: 'Test', version: '1.0.0' },
        });

        expect(mockRegisterDestroyCallback).not.toHaveBeenCalled();
        vi.restoreAllMocks();
    });

    it('registers the extension itself', () => {
        createExtension({
            config: { id: 'test/ext', name: 'Test', version: '1.0.0' },
        });

        expect(mockRegisterExtension).toHaveBeenCalledWith(
            expect.objectContaining({
                id: 'test/ext',
                name: 'Test',
                version: '1.0.0',
            }),
        );
    });

    it('throws when bridge runtime is not loaded', () => {
        delete (window as any).__NOTUR__;

        expect(() => {
            createExtension({
                config: { id: 'test/ext', name: 'Test', version: '1.0.0' },
            });
        }).toThrow('Bridge runtime not found');
    });

    it('defaults to empty slots and routes', () => {
        // Suppress log output
        vi.spyOn(console, 'log').mockImplementation();

        createExtension({
            config: { id: 'test/ext', name: 'Test', version: '1.0.0' },
        });

        expect(mockRegisterExtension).toHaveBeenCalledWith(
            expect.objectContaining({
                slots: [],
                routes: [],
            }),
        );
        vi.restoreAllMocks();
    });

    it('warns for invalid extension id format and duplicate route paths', () => {
        const warnSpy = vi.spyOn(console, 'warn').mockImplementation();
        vi.spyOn(console, 'log').mockImplementation();

        createExtension({
            config: { id: 'INVALID_ID', name: 'Test', version: '1.0.0' },
            routes: [
                { area: 'server', path: 'stats', name: 'Stats', component: DummyComponent, permission: 'stats' },
                { area: 'server', path: 'stats', name: 'Stats 2', component: DummyComponent },
            ],
        });

        expect(warnSpy).toHaveBeenCalledWith(expect.stringContaining('does not match vendor/name format'));
        expect(warnSpy).toHaveBeenCalledWith(expect.stringContaining('should start with "/"'));
        expect(warnSpy).toHaveBeenCalledWith(expect.stringContaining('without namespace'));
        expect(warnSpy).toHaveBeenCalledWith(expect.stringContaining('duplicate route path'));
        vi.restoreAllMocks();
    });
});
