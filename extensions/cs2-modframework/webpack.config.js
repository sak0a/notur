const path = require('path');
const base = require('../../sdk/webpack.extension.config');

module.exports = {
    ...base,
    entry: './resources/frontend/src/index.tsx',
    output: {
        ...base.output,
        filename: 'cs2-modframework.js',
        path: path.resolve(__dirname, 'resources/frontend/dist'),
        library: {
            ...base.output.library,
            type: 'umd',
        },
    },
    resolve: {
        ...base.resolve,
        alias: {
            '@notur/sdk': path.resolve(__dirname, '../../sdk/src'),
        },
    },
};
