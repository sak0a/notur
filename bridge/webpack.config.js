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
    // React and ReactDOM are provided by the panel's bundle as globals.
    // The index.tsx.patch exposes them on window before bridge.js loads.
    externals: {
        react: 'React',
        'react-dom': 'ReactDOM',
    },
};
