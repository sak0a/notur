const path = require('path');

module.exports = {
    context: __dirname,
    entry: './src/index.ts',
    output: {
        filename: 'bridge.js',
        path: path.resolve(__dirname, 'dist'),
        library: {
            name: '__NOTUR_BRIDGE__',
            type: 'umd',
        },
        clean: true,
    },
    resolve: {
        extensions: ['.ts', '.tsx', '.js', '.jsx'],
    },
    module: {
        rules: [
            {
                test: /\.tsx?$/,
                use: 'ts-loader',
                exclude: /node_modules/,
            },
        ],
    },
    // Keep bridge React aligned with extension bundles by consuming the same
    // global React/ReactDOM runtime provided by the panel.
    // This avoids duplicate React instances that can break hooks.
    externals: {
        react: 'React',
        'react-dom': 'ReactDOM',
    },
};
