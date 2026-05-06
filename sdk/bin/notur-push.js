#!/usr/bin/env node

const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

function parseArgs() {
    const args = process.argv.slice(2);
    const options = {
        path: '.',
        archive: null,
        host: null,
        key: null,
        envFile: null,
        endpoint: '/api/notur/dev/push',
        force: true,
        noBuild: false,
        keepArchive: false,
    };

    for (let i = 0; i < args.length; i++) {
        const arg = args[i];
        if (arg === '--archive') {
            options.archive = args[++i];
        } else if (arg === '--host') {
            options.host = args[++i];
        } else if (arg === '--key') {
            options.key = args[++i];
        } else if (arg === '--env-file') {
            options.envFile = args[++i];
        } else if (arg === '--endpoint') {
            options.endpoint = args[++i];
        } else if (arg === '--no-force') {
            options.force = false;
        } else if (arg === '--no-build') {
            options.noBuild = true;
        } else if (arg === '--keep-archive') {
            options.keepArchive = true;
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
  npx notur-push [path] --host https://panel.example.com --key notur_xxx
  npx @notur/sdk push [path] --host https://panel.example.com --key notur_xxx

Options:
  --archive <file>    Upload an existing .notur archive instead of packing
  --host <url>        Remote Pterodactyl panel URL
  --key <token>       Notur remote push token
  --env-file <file>   Load values from a custom env file
  --endpoint <path>   Remote push endpoint (default: /api/notur/dev/push)
  --no-build          Skip npm/yarn/pnpm/bun build before packing
  --no-force          Do not overwrite an already installed extension
  --keep-archive      Keep the temporary archive generated for this push`);
    process.exit(code);
}

function loadManifest(dir) {
    const manifestPath = path.join(dir, 'extension.yaml');
    if (!fs.existsSync(manifestPath)) {
        return { id: path.basename(dir), version: 'dev' };
    }

    const raw = fs.readFileSync(manifestPath, 'utf8');
    const id = raw.match(/^id:\s*["']?([^"'\n]+)["']?/m)?.[1]?.trim();
    const version = raw.match(/^version:\s*["']?([^"'\n]+)["']?/m)?.[1]?.trim();

    return {
        id: id || path.basename(dir),
        version: version || 'dev',
    };
}

function detectPackageManager(dir) {
    if (fs.existsSync(path.join(dir, 'bun.lockb')) || fs.existsSync(path.join(dir, 'bun.lock'))) return 'bun';
    if (fs.existsSync(path.join(dir, 'pnpm-lock.yaml'))) return 'pnpm';
    if (fs.existsSync(path.join(dir, 'yarn.lock'))) return 'yarn';
    if (fs.existsSync(path.join(dir, 'package-lock.json')) || fs.existsSync(path.join(dir, 'package.json'))) return 'npm';
    return null;
}

function buildCommand(packageManager) {
    switch (packageManager) {
        case 'bun':
            return ['bun', ['run', 'build']];
        case 'pnpm':
            return ['pnpm', ['run', 'build']];
        case 'yarn':
            return ['yarn', ['run', 'build']];
        case 'npm':
            return ['npm', ['run', 'build']];
        default:
            return null;
    }
}

function maybeBuild(dir, noBuild) {
    if (noBuild || !fs.existsSync(path.join(dir, 'package.json'))) {
        return;
    }

    const packageJson = JSON.parse(fs.readFileSync(path.join(dir, 'package.json'), 'utf8'));
    if (!packageJson.scripts || !packageJson.scripts.build) {
        return;
    }

    const packageManager = detectPackageManager(dir);
    const command = buildCommand(packageManager);
    if (!command) {
        console.warn('No supported package manager found; skipping build.');
        return;
    }

    console.log(`Running ${command[0]} ${command[1].join(' ')}...`);
    const result = spawnSync(command[0], command[1], {
        cwd: dir,
        stdio: 'inherit',
    });

    if (result.status !== 0) {
        console.error('Build failed; aborting push.');
        process.exit(result.status ?? 1);
    }
}

function packArchive(dir) {
    const manifest = loadManifest(dir);
    const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'notur-push-'));
    const archiveName = `${manifest.id.replace('/', '-')}-${manifest.version}.notur`;
    const archivePath = path.join(tmpDir, archiveName);
    const packScript = path.join(__dirname, 'notur-pack.js');

    const result = spawnSync(process.execPath, [packScript, dir, '--output', archivePath], {
        stdio: 'inherit',
    });

    if (result.status !== 0) {
        console.error('Packaging failed; aborting push.');
        process.exit(result.status ?? 1);
    }

    return { archivePath, tmpDir };
}

function resolveUrl(host, endpoint, force) {
    const base = host.replace(/\/+$/, '');
    const pathPart = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
    const url = new URL(`${base}${pathPart}`);
    if (!force) {
        url.searchParams.set('force', '0');
    }
    return url;
}

async function pushArchive(options, archivePath) {
    if (typeof fetch !== 'function' || typeof FormData !== 'function' || typeof Blob !== 'function') {
        console.error('Error: notur-push requires Node.js 18+ for fetch/FormData support.');
        process.exit(1);
    }

    const url = resolveUrl(options.host, options.endpoint, options.force);
    const data = fs.readFileSync(archivePath);
    const form = new FormData();
    form.append('extension', new Blob([data]), path.basename(archivePath));

    const signaturePath = `${archivePath}.sig`;
    if (fs.existsSync(signaturePath)) {
        const signature = fs.readFileSync(signaturePath);
        form.append('signature', new Blob([signature]), path.basename(signaturePath));
    }

    console.log(`Uploading ${path.basename(archivePath)} to ${url.origin}...`);

    const response = await fetch(url, {
        method: 'POST',
        headers: {
            Authorization: `Bearer ${options.key}`,
            Accept: 'application/json',
        },
        body: form,
    });

    const text = await response.text();
    let payload = null;
    try {
        payload = text ? JSON.parse(text) : null;
    } catch {
        payload = null;
    }

    if (!response.ok) {
        console.error(`Remote push failed (${response.status}).`);
        if (payload?.message) {
            console.error(payload.message);
        } else if (text) {
            console.error(text);
        }
        process.exit(1);
    }

    if (payload) {
        console.log(`Pushed ${payload.id || 'extension'} v${payload.version || 'unknown'}.`);
        if (payload.output) {
            console.log(payload.output.trim());
        }
    } else {
        console.log('Push completed.');
    }
}

function parseEnvValue(value) {
    let parsed = value.trim();
    if (
        (parsed.startsWith('"') && parsed.endsWith('"')) ||
        (parsed.startsWith("'") && parsed.endsWith("'"))
    ) {
        parsed = parsed.slice(1, -1);
    }
    return parsed;
}

function loadEnvFile(filePath) {
    if (!fs.existsSync(filePath)) {
        return {};
    }

    const values = {};
    const lines = fs.readFileSync(filePath, 'utf8').split(/\r?\n/);

    for (const line of lines) {
        const trimmed = line.trim();
        if (!trimmed || trimmed.startsWith('#')) {
            continue;
        }

        const match = trimmed.match(/^(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)=(.*)$/);
        if (!match) {
            continue;
        }

        values[match[1]] = parseEnvValue(match[2]);
    }

    return values;
}

function resolvePushConfig(options, extensionPath) {
    const envFile = options.envFile
        ? path.resolve(options.envFile)
        : path.join(extensionPath, '.env');
    const fileEnv = loadEnvFile(envFile);

    return {
        ...options,
        host: options.host || process.env.NOTUR_HOST || fileEnv.NOTUR_HOST || null,
        key:
            options.key ||
            process.env.NOTUR_API_KEY ||
            process.env.NOTUR_PUSH_KEY ||
            fileEnv.NOTUR_API_KEY ||
            fileEnv.NOTUR_PUSH_KEY ||
            null,
        endpoint: options.endpoint || fileEnv.NOTUR_ENDPOINT || '/api/notur/dev/push',
    };
}

async function main() {
    let options = parseArgs();
    const extensionPath = path.resolve(options.path);

    if (!fs.existsSync(extensionPath) || !fs.statSync(extensionPath).isDirectory()) {
        console.error(`Error: extension path does not exist: ${extensionPath}`);
        process.exit(1);
    }

    options = resolvePushConfig(options, extensionPath);

    if (!options.host) {
        console.error('Error: --host is required, or set NOTUR_HOST in the environment or local .env.');
        process.exit(1);
    }

    if (!options.key) {
        console.error('Error: --key is required, or set NOTUR_PUSH_KEY / NOTUR_API_KEY in the environment or local .env.');
        process.exit(1);
    }

    if (!options.archive) {
        maybeBuild(extensionPath, options.noBuild);
    }

    let archivePath = options.archive ? path.resolve(options.archive) : null;
    let tmpDir = null;
    if (!archivePath) {
        const packed = packArchive(extensionPath);
        archivePath = packed.archivePath;
        tmpDir = packed.tmpDir;
    }

    if (!fs.existsSync(archivePath)) {
        console.error(`Error: archive does not exist: ${archivePath}`);
        process.exit(1);
    }

    try {
        await pushArchive(options, archivePath);
    } finally {
        if (tmpDir && !options.keepArchive) {
            fs.rmSync(tmpDir, { recursive: true, force: true });
        } else if (tmpDir) {
            console.log(`Kept archive at ${archivePath}`);
        }
    }
}

main().catch(error => {
    console.error(error?.message || String(error));
    process.exit(1);
});
