const path = require('path');
const { defineConfig } = require('vitest/config');

module.exports = defineConfig({
    test: {
        environment: 'jsdom',
        globals: true,
        include: ['tests/Frontend/**/*.{test,spec}.{ts,tsx}'],
        exclude: ['node_modules', 'bridge/dist'],
        coverage: {
            provider: 'v8',
            reporter: ['text', 'html'],
            reportsDirectory: 'coverage/frontend',
            include: ['bridge/src/**/*.{ts,tsx}', 'sdk/src/**/*.{ts,tsx}'],
            exclude: ['**/*.d.ts'],
        },
    },
    resolve: {
        alias: {
            '@bridge': path.resolve(__dirname, '../../bridge/src'),
            react: path.resolve(__dirname, '../../node_modules/react'),
            'react-dom': path.resolve(__dirname, '../../node_modules/react-dom'),
        },
    },
    esbuild: {
        jsx: 'transform',
        jsxFactory: 'React.createElement',
        jsxFragment: 'React.Fragment',
        target: 'es2018',
    },
});
