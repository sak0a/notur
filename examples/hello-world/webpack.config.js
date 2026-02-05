const path = require('path');

module.exports = {
    entry: './resources/frontend/src/index.tsx',
    output: {
        filename: 'hello-world.js',
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
