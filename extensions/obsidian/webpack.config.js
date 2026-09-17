const path = require('path');

module.exports = {
    context: __dirname,
    entry: './resources/frontend/src/index.tsx',
    output: {
        filename: 'obsidian.js',
        path: path.resolve(__dirname, 'resources/frontend/dist'),
        library: { type: 'umd' },
        clean: true,
    },
    resolve: {
        extensions: ['.ts', '.tsx', '.js', '.jsx'],
        alias: {
            '@notur/sdk': path.resolve(__dirname, '../../sdk/dist'),
        },
    },
    module: {
        rules: [
            { test: /\.css$/, type: "asset/source" },
            {
                test: /\.tsx?$/,
                use: 'ts-loader',
                exclude: /node_modules/,
            },
        ],
    },
    externals: {
        react: 'React',
        'react-dom': 'ReactDOM',
    },
};
