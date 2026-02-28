import * as React from 'react';
import * as ReactDOM from 'react-dom';
import { act } from 'react-dom/test-utils';
import { useExtensionApi } from '../../../bridge/src/hooks/useExtensionApi';

// Mock fetch
const mockFetch = jest.fn();
(globalThis as any).fetch = mockFetch;

// Helper to capture hook result via a wrapper component
function createHookRenderer(extensionId: string) {
    const result: { current: ReturnType<typeof useExtensionApi> | null } = { current: null };

    function TestComponent() {
        result.current = useExtensionApi({ extensionId });
        return null;
    }

    return { result, TestComponent };
}

describe('useExtensionApi', () => {
    let container: HTMLDivElement;

    beforeEach(() => {
        container = document.createElement('div');
        document.body.appendChild(container);

        // Add CSRF meta tag
        const meta = document.createElement('meta');
        meta.setAttribute('name', 'csrf-token');
        meta.setAttribute('content', 'test-csrf-token');
        document.head.appendChild(meta);

        mockFetch.mockReset();
    });

    afterEach(() => {
        ReactDOM.unmountComponentAtNode(container);
        container.remove();

        const meta = document.querySelector('meta[name="csrf-token"]');
        meta?.remove();
    });

    it('makes GET requests to the correct URL', async () => {
        mockFetch.mockResolvedValue({
            ok: true,
            status: 200,
            json: async () => ({ data: 'test' }),
        });

        const { result, TestComponent } = createHookRenderer('test-ext');

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        await act(async () => {
            await result.current!.get('/endpoint');
        });

        expect(mockFetch).toHaveBeenCalledWith(
            '/api/client/notur/test-ext/endpoint',
            expect.objectContaining({ method: 'GET' }),
        );
    });

    it('includes CSRF token for POST requests', async () => {
        mockFetch.mockResolvedValue({
            ok: true,
            status: 200,
            json: async () => ({ success: true }),
        });

        const { result, TestComponent } = createHookRenderer('test-ext');

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        await act(async () => {
            await result.current!.post('/endpoint', { key: 'value' });
        });

        const [, options] = mockFetch.mock.calls[0];
        expect(options.headers['X-CSRF-TOKEN']).toBe('test-csrf-token');
    });

    it('does not include CSRF token for GET requests', async () => {
        mockFetch.mockResolvedValue({
            ok: true,
            status: 200,
            json: async () => ({ data: 'test' }),
        });

        const { result, TestComponent } = createHookRenderer('test-ext');

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        await act(async () => {
            await result.current!.get('/endpoint');
        });

        const [, options] = mockFetch.mock.calls[0];
        expect(options.headers['X-CSRF-TOKEN']).toBeUndefined();
    });

    it('handles error responses', async () => {
        mockFetch.mockResolvedValue({
            ok: false,
            status: 422,
            statusText: 'Unprocessable Entity',
            json: async () => ({ message: 'Validation failed' }),
        });

        const { result, TestComponent } = createHookRenderer('test-ext');

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        await act(async () => {
            try {
                await result.current!.get('/endpoint');
            } catch (e) {
                // Expected — the hook re-throws after setting state
            }
        });

        expect(result.current!.error).toBe('Validation failed');
        expect(result.current!.loading).toBe(false);
    });

    it('handles 204 No Content', async () => {
        mockFetch.mockResolvedValue({
            ok: true,
            status: 204,
        });

        const { result, TestComponent } = createHookRenderer('test-ext');

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        await act(async () => {
            await result.current!.delete('/endpoint');
        });

        expect(result.current!.loading).toBe(false);
        expect(result.current!.error).toBeNull();
    });

    it('passes AbortController signal to fetch', async () => {
        mockFetch.mockResolvedValue({
            ok: true,
            status: 200,
            json: async () => ({}),
        });

        const { result, TestComponent } = createHookRenderer('test-ext');

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        await act(async () => {
            await result.current!.get('/endpoint');
        });

        const [, options] = mockFetch.mock.calls[0];
        expect(options.signal).toBeInstanceOf(AbortSignal);
    });

    it('sets loading state during request', async () => {
        let resolvePromise: (value: any) => void;
        const pendingPromise = new Promise(resolve => {
            resolvePromise = resolve;
        });

        mockFetch.mockReturnValue(pendingPromise);

        const { result, TestComponent } = createHookRenderer('test-ext');

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        expect(result.current!.loading).toBe(false);

        let requestPromise: Promise<any>;
        act(() => {
            requestPromise = result.current!.get('/endpoint');
        });

        // While fetch is pending, loading should be true
        expect(result.current!.loading).toBe(true);

        await act(async () => {
            resolvePromise!({
                ok: true,
                status: 200,
                json: async () => ({ data: 'result' }),
            });
            await requestPromise;
        });

        expect(result.current!.loading).toBe(false);
        expect(result.current!.data).toEqual({ data: 'result' });
    });

    it('sends JSON body on POST', async () => {
        mockFetch.mockResolvedValue({
            ok: true,
            status: 200,
            json: async () => ({ created: true }),
        });

        const { result, TestComponent } = createHookRenderer('test-ext');

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        await act(async () => {
            await result.current!.post('/items', { name: 'test' });
        });

        const [, options] = mockFetch.mock.calls[0];
        expect(options.body).toBe(JSON.stringify({ name: 'test' }));
        expect(options.headers['Content-Type']).toBe('application/json');
    });

    it('aborts in-flight requests on unmount', async () => {
        let resolvePromise: (value: any) => void;
        const pendingPromise = new Promise(resolve => {
            resolvePromise = resolve;
        });
        mockFetch.mockReturnValue(pendingPromise);

        const { result, TestComponent } = createHookRenderer('test-ext');

        act(() => {
            ReactDOM.render(React.createElement(TestComponent), container);
        });

        act(() => {
            result.current!.get('/slow-endpoint');
        });

        // Capture the signal before unmount
        const [, options] = mockFetch.mock.calls[0];
        const signal = options.signal as AbortSignal;

        expect(signal.aborted).toBe(false);

        // Unmount to trigger cleanup
        act(() => {
            ReactDOM.unmountComponentAtNode(container);
        });

        expect(signal.aborted).toBe(true);
    });
});
