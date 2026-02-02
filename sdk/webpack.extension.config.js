/**
 * Base webpack configuration for Notur extensions.
 *
 * Extension developers can import and extend this config:
 *
 * ```js
 * const base = require('@notur/sdk/webpack.extension.config');
 *
 * module.exports = {
 *     ...base,
 *     output: {
 *         ...base.output,
 *         library: { name: '__NOTUR_EXT_MyExtension__', type: 'umd' },
 *     },
 * };
 * ```
 */
const path = require('path');

module.exports = {
    entry: './src/index.ts',
    output: {
        filename: 'bundle.js',
        path: path.resolve(process.cwd(), 'dist'),
        library: {
            name: '__NOTUR_EXT__',
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
            {
                test: /\.css$/,
                use: ['style-loader', 'css-loader'],
            },
        ],
    },
    externals: {
        react: 'React',
        'react-dom': 'ReactDOM',
        '@notur/sdk': '__NOTUR__',
    },
};
