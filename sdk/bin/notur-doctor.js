#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

function parseArgs() {
    const args = process.argv.slice(2);
    const options = { path: '.', remote: true };
    for (let i = 0; i < args.length; i++) {
        const arg = args[i];
        if (arg === '--no-remote') {
            options.remote = false;
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
  npx notur-doctor [path]
  npx @notur/sdk doctor [path]

Options:
  --no-remote   Skip remote host/key checks`);
    process.exit(code);
}

function parseEnv(basePath) {
    const envPath = path.join(basePath, '.env');
    if (!fs.existsSync(envPath)) return {};
    const values = {};
    for (const line of fs.readFileSync(envPath, 'utf8').split(/\r?\n/)) {
        const trimmed = line.trim();
        if (!trimmed || trimmed.startsWith('#')) continue;
        const match = trimmed.match(/^(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)=(.*)$/);
        if (!match) continue;
        values[match[1]] = match[2].trim().replace(/^['"]|['"]$/g, '');
    }
    return values;
}

function check(label, ok, detail = '') {
    const mark = ok ? 'ok' : 'fail';
    console.log(`${mark}: ${label}${detail ? ` - ${detail}` : ''}`);
    return ok;
}

function warn(label, ok, detail = '') {
    const mark = ok ? 'ok' : 'warn';
    console.log(`${mark}: ${label}${detail ? ` - ${detail}` : ''}`);
    return ok;
}

function commandExists(command) {
    const result = spawnSync(command, ['--version'], { stdio: 'ignore' });
    return !result.error && result.status === 0;
}

function main() {
    const options = parseArgs();
    const basePath = path.resolve(options.path);
    let ok = true;

    console.log(`Notur doctor for ${basePath}`);

    ok = check('extension directory exists', fs.existsSync(basePath) && fs.statSync(basePath).isDirectory()) && ok;
    ok = check('extension.yaml exists', fs.existsSync(path.join(basePath, 'extension.yaml')) || fs.existsSync(path.join(basePath, 'extension.yml'))) && ok;

    const packagePath = path.join(basePath, 'package.json');
    const hasPackage = fs.existsSync(packagePath);
    check('package.json exists', hasPackage);
    if (hasPackage) {
        const pkg = JSON.parse(fs.readFileSync(packagePath, 'utf8'));
        ok = check('build script', Boolean(pkg.scripts?.build)) && ok;
        check('push script', Boolean(pkg.scripts?.push));
        check('@notur/sdk dependency', Boolean(pkg.dependencies?.['@notur/sdk'] || pkg.devDependencies?.['@notur/sdk']));
    }

    check('node available', Boolean(process.version), process.version);
    check('npm available', commandExists('npm'));
    check('webpack config exists', fs.existsSync(path.join(basePath, 'webpack.config.js')));

    const bundleCandidates = [
        'resources/frontend/dist/extension.js',
        'resources/frontend/dist/bundle.js',
        'dist/extension.js',
        'dist/bundle.js',
    ];
    warn('frontend bundle exists', bundleCandidates.some(file => fs.existsSync(path.join(basePath, file))), 'run npm run build before packaging');

    const validateResult = spawnSync(process.execPath, [path.join(__dirname, 'notur-validate.js'), basePath], {
        stdio: 'pipe',
        encoding: 'utf8',
    });
    ok = check('notur validate', validateResult.status === 0) && ok;
    if (validateResult.stdout.trim()) console.log(validateResult.stdout.trim());
    if (validateResult.stderr.trim()) console.log(validateResult.stderr.trim());

    if (options.remote) {
        const env = parseEnv(basePath);
        const host = process.env.NOTUR_HOST || env.NOTUR_HOST;
        const key = process.env.NOTUR_PUSH_KEY || process.env.NOTUR_API_KEY || env.NOTUR_PUSH_KEY || env.NOTUR_API_KEY;
        check('remote host configured', Boolean(host), host ? new URL(host).origin : '');
        check('remote push key configured', Boolean(key));
    }

    if (!ok) {
        console.error('Notur doctor found blocking issues.');
        process.exit(1);
    }

    console.log('Notur doctor completed.');
}

main();
