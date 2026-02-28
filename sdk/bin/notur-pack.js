#!/usr/bin/env node

/**
 * notur-pack - Package a Notur extension into a .notur archive
 *
 * Usage:
 *   npx notur-pack [path]           # Pack extension at path (default: current dir)
 *   npx notur-pack --output foo.notur
 *   npx notur-pack --sign           # Sign using NOTUR_SECRET_KEY env var
 *   npx notur-pack --sign --secret-key xxx
 *   bunx notur-pack
 *
 * A .notur file is a tar.gz archive containing:
 * - All extension files (excluding node_modules, .git, vendor, .idea, .vscode)
 * - A checksums.json with SHA-256 hashes of all included files
 */

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { spawnSync } = require('child_process');
const yaml = require('yaml');

const EXCLUDE_PATTERNS = [
    'node_modules',
    '.git',
    'vendor',
    '.idea',
    '.vscode',
    '.DS_Store',
    'checksums.json',
];

function parseArgs() {
    const args = process.argv.slice(2);
    const options = {
        path: '.',
        output: null,
        sign: false,
        secretKey: null,
        dryRun: false,
    };

    for (let i = 0; i < args.length; i++) {
        if (args[i] === '--output' || args[i] === '-o') {
            options.output = args[++i];
        } else if (args[i] === '--sign' || args[i] === '-s') {
            options.sign = true;
        } else if (args[i] === '--secret-key') {
            options.secretKey = args[++i];
        } else if (args[i] === '--dry-run') {
            options.dryRun = true;
        } else if (!args[i].startsWith('-')) {
            options.path = args[i];
        }
    }

    return options;
}

function isGnuTarAvailable() {
    const result = spawnSync('tar', ['--version'], {
        stdio: 'pipe',
        encoding: 'utf8',
    });

    if (result.error || result.status !== 0) {
        return false;
    }

    const stdout = `${result.stdout || ''}${result.stderr || ''}`;
    return /gnu tar/i.test(stdout);
}

function loadManifest(dir) {
    const manifestPath = path.join(dir, 'extension.yaml');
    if (!fs.existsSync(manifestPath)) {
        console.error('Error: extension.yaml not found in', dir);
        process.exit(1);
    }

    const content = fs.readFileSync(manifestPath, 'utf8');

    // Try to parse YAML, fall back to simple regex if yaml package not available
    try {
        const parsed = yaml.parse(content);
        return {
            id: parsed.id,
            version: parsed.version,
            name: parsed.name,
        };
    } catch {
        // Fallback: simple regex parsing
        const idMatch = content.match(/^id:\s*["']?([^"'\n]+)["']?/m);
        const versionMatch = content.match(/^version:\s*["']?([^"'\n]+)["']?/m);
        const nameMatch = content.match(/^name:\s*["']?([^"'\n]+)["']?/m);

        return {
            id: idMatch ? idMatch[1].trim() : null,
            version: versionMatch ? versionMatch[1].trim() : null,
            name: nameMatch ? nameMatch[1].trim() : null,
        };
    }
}

function shouldExclude(relativePath) {
    for (const pattern of EXCLUDE_PATTERNS) {
        if (relativePath === pattern || relativePath.startsWith(pattern + '/') || relativePath.startsWith(pattern + path.sep)) {
            return true;
        }
    }
    return false;
}

function collectFiles(dir, baseDir = dir) {
    const files = [];
    const entries = fs.readdirSync(dir, { withFileTypes: true });

    for (const entry of entries) {
        const fullPath = path.join(dir, entry.name);
        const relativePath = path.relative(baseDir, fullPath);

        if (shouldExclude(relativePath)) {
            continue;
        }

        if (entry.isDirectory()) {
            files.push(...collectFiles(fullPath, baseDir));
        } else if (entry.isFile()) {
            files.push(relativePath);
        }
    }

    return files.sort();
}

function computeChecksum(filePath) {
    const content = fs.readFileSync(filePath);
    return crypto.createHash('sha256').update(content).digest('hex');
}

function computeChecksums(dir, files) {
    const checksums = {};
    for (const file of files) {
        const fullPath = path.join(dir, file);
        checksums[file] = computeChecksum(fullPath);
    }
    return checksums;
}

async function signArchive(archivePath, secretKeyHex) {
    const sodium = require('libsodium-wrappers');
    await sodium.ready;

    const content = fs.readFileSync(archivePath);
    const secretKey = sodium.from_hex(secretKeyHex);
    const signature = sodium.crypto_sign_detached(content, secretKey);

    return sodium.to_hex(signature);
}

function getSecretKey(options) {
    // Check --secret-key argument first, then environment variable
    const secretKey = options.secretKey || process.env.NOTUR_SECRET_KEY;

    if (!secretKey) {
        console.error('Error: --sign requires a secret key.');
        console.error('');
        console.error('Provide it via:');
        console.error('  NOTUR_SECRET_KEY=xxx npx notur-pack --sign');
        console.error('  npx notur-pack --sign --secret-key xxx');
        console.error('');
        console.error('Generate a keypair with: npx notur-keygen');
        process.exit(1);
    }

    // Validate format: Ed25519 secret keys are 64 bytes = 128 hex chars
    if (!/^[0-9a-fA-F]{128}$/.test(secretKey)) {
        console.error('Error: Invalid secret key format.');
        console.error('Expected 128 hexadecimal characters (64 bytes).');
        console.error('');
        console.error('Generate a valid keypair with: npx notur-keygen');
        process.exit(1);
    }

    return secretKey;
}

async function pack(sourceDir, outputPath, options = {}) {
    const resolvedDir = path.resolve(sourceDir);

    if (!fs.existsSync(resolvedDir)) {
        console.error('Error: Directory does not exist:', resolvedDir);
        process.exit(1);
    }

    const manifest = loadManifest(resolvedDir);

    if (!manifest.id || !manifest.version) {
        console.error('Error: extension.yaml must contain "id" and "version"');
        process.exit(1);
    }

    console.log(`Packing ${manifest.name || manifest.id} v${manifest.version}...`);

    // Collect files and compute checksums
    const files = collectFiles(resolvedDir);
    if (files.length === 0) {
        console.error('Error: no packageable files found in extension directory.');
        process.exit(1);
    }
    const checksums = computeChecksums(resolvedDir, files);

    console.log(`  Found ${files.length} files`);

    // Write checksums.json temporarily
    const checksumsPath = path.join(resolvedDir, 'checksums.json');
    const checksumsExisted = fs.existsSync(checksumsPath);
    fs.writeFileSync(checksumsPath, JSON.stringify(checksums, null, 2) + '\n');

    // Determine output filename
    const filename = outputPath || `${manifest.id.replace('/', '-')}-${manifest.version}.notur`;
    const outputFullPath = path.resolve(filename);

    // Build safe argument list for tar (no shell interpolation).
    const deterministicArgs = isGnuTarAvailable()
        ? ['--sort=name', '--mtime=UTC 1970-01-01', '--owner=0', '--group=0', '--numeric-owner']
        : [];

    const tarArgs = [
        ...deterministicArgs,
        ...EXCLUDE_PATTERNS.flatMap(p => ['--exclude', p]),
        '-czf',
        outputFullPath,
        '.',
    ];

    if (options.dryRun) {
        console.log('\nDry run mode (no archive written).');
        console.log(`Would create: ${outputFullPath}`);
        console.log(`Would include: ${files.length} files`);
        if (deterministicArgs.length > 0) {
            console.log('Deterministic archive mode: enabled (GNU tar flags).');
        } else {
            console.log('Deterministic archive mode: not available (GNU tar not detected).');
        }
        return;
    }

    try {
        const tarResult = spawnSync('tar', tarArgs, {
            cwd: resolvedDir,
            stdio: 'pipe',
            encoding: 'utf8',
        });

        if (tarResult.error) {
            throw new Error(`Failed to execute tar: ${tarResult.error.message}`);
        }

        if (tarResult.status !== 0) {
            const stderr = (tarResult.stderr || '').trim();
            throw new Error(`tar failed with exit code ${tarResult.status}${stderr ? `: ${stderr}` : ''}`);
        }

        // Compute archive checksum
        const archiveChecksum = computeChecksum(outputFullPath);
        fs.writeFileSync(
            outputFullPath + '.sha256',
            `${archiveChecksum}  ${path.basename(outputFullPath)}\n`
        );

        console.log(`\nCreated: ${outputFullPath}`);
        console.log(`Checksum: ${archiveChecksum}`);

        // Sign the archive if requested
        if (options.sign) {
            const secretKey = getSecretKey(options);
            const signature = await signArchive(outputFullPath, secretKey);
            const sigPath = outputFullPath + '.sig';
            fs.writeFileSync(sigPath, signature + '\n');
            console.log(`Signature: ${sigPath}`);
        }

        console.log(`\nUpload this file to your Pterodactyl admin panel at /admin/notur/extensions`);

    } finally {
        // Clean up checksums.json if we created it
        if (!checksumsExisted && fs.existsSync(checksumsPath)) {
            fs.unlinkSync(checksumsPath);
        }
    }
}

// Main
const options = parseArgs();
pack(options.path, options.output, options).catch(err => {
    console.error('Error:', err.message);
    process.exit(1);
});
