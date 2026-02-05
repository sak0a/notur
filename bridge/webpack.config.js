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
<<<<<<< claude/brutalist-admin-redesign-SiZm3
    // React and ReactDOM are bundled into bridge.js so it works on ALL pages,
    // including admin pages where the panel's React SPA doesn't load.
    // The bridge (index.ts lines 21-34) re-exposes React on window for
    // extension bundles and detects duplicate instances automatically.
=======
<<<<<<< ours
    // React and ReactDOM are provided by the panel's bundle as globals.
    // The index.tsx.patch exposes them on window before bridge.js loads.
=======
    // Keep bridge React aligned with extension bundles by consuming the same
    // global React/ReactDOM runtime provided by the panel.
    // This avoids duplicate React instances that can break hooks.
>>>>>>> theirs
    externals: {
        react: 'React',
        'react-dom': 'ReactDOM',
    },
>>>>>>> master
};
