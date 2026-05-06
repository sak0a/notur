#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const yaml = require('yaml');

function parseArgs() {
    const args = process.argv.slice(2);
    const options = {
        path: '.',
        forceWebpack: false,
    };

    for (let i = 0; i < args.length; i++) {
        const arg = args[i];
        if (arg === '--force-webpack') {
            options.forceWebpack = true;
        } else if (arg === '--help' || arg === '-h') {
            usage(0);
        } else if (!arg.startsWith('-')) {
            options.path = arg;
        } else {
            console.error(`Unknown argument: ${arg}`);
            usage(1);
        }
    }

    return options;
}

function usage(code) {
    console.log(`Usage:
  npx notur-sync [path]
  npx @notur/sdk sync [path]

Options:
  --force-webpack   Regenerate webpack.config.js from extension.yaml`);
    process.exit(code);
}

function readManifest(extensionPath) {
    const manifestPath = ['extension.yaml', 'extension.yml']
        .map(file => path.join(extensionPath, file))
        .find(file => fs.existsSync(file));

    if (!manifestPath) {
        console.error(`Error: extension.yaml not found in ${extensionPath}`);
        process.exit(1);
    }

    const manifest = yaml.parse(fs.readFileSync(manifestPath, 'utf8'));
    if (!manifest?.id || !manifest?.version) {
        console.error('Error: extension.yaml must contain id and version.');
        process.exit(1);
    }

    return manifest;
}

function packageName(id) {
    return id.replace('/', '-');
}

function studly(value) {
    return value
        .split('-')
        .filter(Boolean)
        .map(part => part.charAt(0).toUpperCase() + part.slice(1))
        .join('');
}

function libraryName(id) {
    return id
        .split(/[\/-]/)
        .filter(Boolean)
        .map(studly)
        .join('');
}

function bundlePath(manifest) {
    return manifest?.frontend?.bundle || 'resources/frontend/dist/extension.js';
}

function syncPackageJson(extensionPath, manifest) {
    const packagePath = path.join(extensionPath, 'package.json');
    const current = fs.existsSync(packagePath)
        ? JSON.parse(fs.readFileSync(packagePath, 'utf8'))
        : {};

    const next = {
        ...current,
        name: packageName(manifest.id),
        version: manifest.version,
        private: current.private ?? true,
        scripts: {
            ...(current.scripts || {}),
            build: current.scripts?.build || 'webpack-cli --mode production --config webpack.config.js',
            dev: current.scripts?.dev || 'webpack-cli --mode development --watch --config webpack.config.js',
            pack: current.scripts?.pack || 'notur-pack',
            push: current.scripts?.push || 'notur-push',
            sync: current.scripts?.sync || 'notur-sync',
            validate: current.scripts?.validate || 'notur-validate',
            doctor: current.scripts?.doctor || 'notur-doctor',
        },
        peerDependencies: {
            react: '^16.14.0',
            'react-dom': '^16.14.0',
            ...(current.peerDependencies || {}),
        },
        devDependencies: {
            '@notur/sdk': '^1.4.7',
            '@types/react': '^16.14.0',
            '@types/react-dom': '^16.9.0',
            react: '^16.14.0',
            'react-dom': '^16.14.0',
            'ts-loader': '^9.5.0',
            typescript: '^5.3.0',
            webpack: '^5.90.0',
            'webpack-cli': '^6.0.0',
            ...(current.devDependencies || {}),
        },
    };

    fs.writeFileSync(packagePath, JSON.stringify(next, null, 2) + '\n');
    console.log(`  synced ${path.relative(process.cwd(), packagePath)}`);
}

function webpackTemplate(manifest) {
    const bundle = bundlePath(manifest);
    const filename = path.basename(bundle);
    const outputDir = path.dirname(bundle);

    return `const path = require('path');
const base = require('@notur/sdk/webpack.extension.config');

module.exports = {
    ...base,
    entry: './resources/frontend/src/index.tsx',
    output: {
        ...base.output,
        filename: '${filename}',
        path: path.resolve(__dirname, '${outputDir.replace(/\\/g, '/')}'),
        library: {
            ...base.output.library,
            name: '__NOTUR_EXT_${libraryName(manifest.id)}__',
            type: 'umd',
        },
    },
};
`;
}

function syncWebpack(extensionPath, manifest, force) {
    if (!manifest.frontend) {
        return;
    }

    const webpackPath = path.join(extensionPath, 'webpack.config.js');
    if (fs.existsSync(webpackPath) && !force) {
        return;
    }

    fs.writeFileSync(webpackPath, webpackTemplate(manifest));
    console.log(`  synced ${path.relative(process.cwd(), webpackPath)}`);
}

function syncEnvExample(extensionPath) {
    const envExamplePath = path.join(extensionPath, '.env.example');
    if (fs.existsSync(envExamplePath)) {
        return;
    }

    fs.writeFileSync(envExamplePath, `NOTUR_HOST=https://panel.example.com
NOTUR_PUSH_KEY=notur_xxx
`);
    console.log(`  synced ${path.relative(process.cwd(), envExamplePath)}`);
}

function main() {
    const options = parseArgs();
    const extensionPath = path.resolve(options.path);
    if (!fs.existsSync(extensionPath) || !fs.statSync(extensionPath).isDirectory()) {
        console.error(`Error: extension path does not exist: ${extensionPath}`);
        process.exit(1);
    }

    const manifest = readManifest(extensionPath);
    console.log(`Syncing ${manifest.id} from extension.yaml`);

    if (manifest.frontend) {
        syncPackageJson(extensionPath, manifest);
        syncWebpack(extensionPath, manifest, options.forceWebpack);
        syncEnvExample(extensionPath);
    }

    console.log('Done.');
}

main();
