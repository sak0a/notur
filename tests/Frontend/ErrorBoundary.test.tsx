import * as React from 'react';
import * as ReactDOM from 'react-dom';
import { SlotErrorBoundary } from '../../bridge/src/ErrorBoundary';

// Suppress console.error in test output
const originalConsoleError = console.error;
beforeEach(() => {
    console.error = jest.fn();
});
afterEach(() => {
    console.error = originalConsoleError;
});

function ThrowingComponent(): React.ReactElement {
    throw new Error('Test error');
}

function GoodComponent(): React.ReactElement {
    return React.createElement('div', null, 'Hello');
}

describe('SlotErrorBoundary', () => {
    let container: HTMLDivElement;

    beforeEach(() => {
        container = document.createElement('div');
        document.body.appendChild(container);
    });

    afterEach(() => {
        ReactDOM.unmountComponentAtNode(container);
        container.remove();
    });

    it('renders children when no error occurs', () => {
        ReactDOM.render(
            React.createElement(SlotErrorBoundary, { extensionId: 'test-ext' },
                React.createElement(GoodComponent),
            ),
            container,
        );
        expect(container.textContent).toBe('Hello');
    });

    it('renders fallback UI when child throws', () => {
        ReactDOM.render(
            React.createElement(SlotErrorBoundary, { extensionId: 'broken-ext' },
                React.createElement(ThrowingComponent),
            ),
            container,
        );
        expect(container.textContent).toContain('broken-ext');
        expect(container.textContent).toContain('failed to render');
    });

    it('logs error with extension ID', () => {
        ReactDOM.render(
            React.createElement(SlotErrorBoundary, { extensionId: 'broken-ext' },
                React.createElement(ThrowingComponent),
            ),
            container,
        );
        expect(console.error).toHaveBeenCalledWith(
            expect.stringContaining('broken-ext'),
            expect.any(Error),
            expect.anything(),
        );
    });

    it('isolates errors between boundaries', () => {
        ReactDOM.render(
            React.createElement('div', null,
                React.createElement(SlotErrorBoundary, { extensionId: 'broken' },
                    React.createElement(ThrowingComponent),
                ),
                React.createElement(SlotErrorBoundary, { extensionId: 'good' },
                    React.createElement(GoodComponent),
                ),
            ),
            container,
        );
        expect(container.textContent).toContain('failed to render');
        expect(container.textContent).toContain('Hello');
    });
});
