#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const yaml = require('yaml');

const KNOWN_SLOTS = new Set([
    'navbar', 'navbar.left', 'navbar.before', 'navbar.after',
    'server.subnav', 'server.subnav.before', 'server.subnav.after', 'server.header', 'server.page', 'server.footer',
    'server.terminal.buttons', 'server.console.header', 'server.console.info.before', 'server.console.info.after',
    'server.console.sidebar', 'server.console.command', 'server.console.footer', 'server.files.actions',
    'server.files.header', 'server.files.footer', 'server.files.dropdown', 'server.files.edit.before',
    'server.files.edit.after', 'server.databases.before', 'server.databases.after', 'server.schedules.before',
    'server.schedules.after', 'server.schedules.edit.before', 'server.schedules.edit.after', 'server.users.before',
    'server.users.after', 'server.backups.before', 'server.backups.after', 'server.backups.dropdown',
    'server.network.before', 'server.network.after', 'server.startup.before', 'server.startup.after',
    'server.settings.before', 'server.settings.after', 'dashboard.header', 'dashboard.widgets',
    'dashboard.serverlist.before', 'dashboard.serverlist.after', 'dashboard.serverrow.name.before',
    'dashboard.serverrow.name.after', 'dashboard.serverrow.description.before', 'dashboard.serverrow.description.after',
    'dashboard.serverrow.limits', 'dashboard.footer', 'dashboard.page', 'account.header', 'account.page',
    'account.footer', 'account.subnav', 'account.subnav.before', 'account.subnav.after',
    'account.overview.before', 'account.overview.after', 'account.api.before', 'account.api.after',
    'account.ssh.before', 'account.ssh.after', 'auth.container.before', 'auth.container.after',
]);

function parseArgs() {
    const args = process.argv.slice(2);
    const options = { path: '.', strict: false };
    for (let i = 0; i < args.length; i++) {
        const arg = args[i];
        if (arg === '--strict') {
            options.strict = true;
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
  npx notur-validate [path]
  npx @notur/sdk validate [path]

Options:
  --strict   Treat warnings as errors`);
    process.exit(code);
}

function readManifest(basePath, result) {
    const manifestPath = ['extension.yaml', 'extension.yml']
        .map(file => path.join(basePath, file))
        .find(file => fs.existsSync(file));
    if (!manifestPath) {
        result.errors.push('extension.yaml not found.');
        return null;
    }

    try {
        return yaml.parse(fs.readFileSync(manifestPath, 'utf8'));
    } catch (error) {
        result.errors.push(`extension.yaml could not be parsed: ${error.message}`);
        return null;
    }
}

function validateManifest(basePath, manifest, result) {
    if (!manifest) return;

    for (const field of ['id', 'name', 'version']) {
        if (!manifest[field]) {
            result.errors.push(`extension.yaml is missing required field: ${field}`);
        }
    }

    if (manifest.id && !/^[a-z0-9-]+\/[a-z0-9-]+$/.test(manifest.id)) {
        result.errors.push(`extension id "${manifest.id}" must use vendor/name with lowercase letters, numbers, and hyphens.`);
    }

    if (manifest.frontend?.bundle) {
        const bundlePath = path.join(basePath, manifest.frontend.bundle);
        if (!fs.existsSync(bundlePath)) {
            result.warnings.push(`frontend.bundle does not exist yet: ${manifest.frontend.bundle}. Run your build before packaging.`);
        }
    }

    const routes = manifest.backend?.routes;
    if (routes && typeof routes === 'object') {
        for (const [group, routeFile] of Object.entries(routes)) {
            if (typeof routeFile !== 'string') {
                result.errors.push(`backend.routes.${group} must be a string path.`);
                continue;
            }
            if (!fs.existsSync(path.join(basePath, routeFile))) {
                result.errors.push(`backend.routes.${group} points to missing file: ${routeFile}`);
            }
        }
    }
}

function readPackage(basePath, result) {
    const packagePath = path.join(basePath, 'package.json');
    if (!fs.existsSync(packagePath)) {
        return null;
    }
    try {
        return JSON.parse(fs.readFileSync(packagePath, 'utf8'));
    } catch (error) {
        result.errors.push(`package.json could not be parsed: ${error.message}`);
        return null;
    }
}

function validatePackage(manifest, pkg, result) {
    if (!manifest || !pkg) return;

    const expectedName = manifest.id?.replace('/', '-');
    if (expectedName && pkg.name && pkg.name !== expectedName) {
        result.warnings.push(`package.json name "${pkg.name}" differs from manifest-derived "${expectedName}". Run npx notur-sync.`);
    }

    if (manifest.version && pkg.version && pkg.version !== manifest.version) {
        result.warnings.push(`package.json version "${pkg.version}" differs from extension.yaml version "${manifest.version}". Run npx notur-sync.`);
    }

    for (const script of ['build', 'pack', 'push', 'sync']) {
        if (!pkg.scripts?.[script]) {
            result.warnings.push(`package.json is missing "${script}" script.`);
        }
    }
}

function validateFrontendSource(basePath, manifest, result) {
    const indexPath = path.join(basePath, 'resources/frontend/src/index.tsx');
    if (!fs.existsSync(indexPath)) {
        if (manifest?.frontend) {
            result.warnings.push('frontend is configured but resources/frontend/src/index.tsx was not found.');
        }
        return;
    }

    const source = fs.readFileSync(indexPath, 'utf8');
    if (!source.includes('createExtension')) {
        result.warnings.push('frontend entry does not appear to call createExtension().');
    }

    if (manifest?.id && !source.includes(manifest.id)) {
        result.warnings.push(`frontend entry does not appear to reference manifest id "${manifest.id}".`);
    }

    const slotMatches = source.matchAll(/slot:\s*['"`]([^'"`]+)['"`]/g);
    for (const match of slotMatches) {
        if (!KNOWN_SLOTS.has(match[1])) {
            result.warnings.push(`unknown slot id "${match[1]}". Check docs/slot-reference.md or SDK SlotId.`);
        }
    }
}

function printResult(result, strict) {
    for (const warning of result.warnings) {
        console.warn(`warning: ${warning}`);
    }
    for (const error of result.errors) {
        console.error(`error: ${error}`);
    }

    const failed = result.errors.length > 0 || (strict && result.warnings.length > 0);
    if (failed) {
        console.error(`Notur validation failed (${result.errors.length} errors, ${result.warnings.length} warnings).`);
        process.exit(1);
    }

    console.log(`Notur validation passed (${result.warnings.length} warnings).`);
}

function main() {
    const options = parseArgs();
    const basePath = path.resolve(options.path);
    const result = { errors: [], warnings: [] };

    if (!fs.existsSync(basePath) || !fs.statSync(basePath).isDirectory()) {
        result.errors.push(`path does not exist or is not a directory: ${basePath}`);
        printResult(result, options.strict);
        return;
    }

    const manifest = readManifest(basePath, result);
    validateManifest(basePath, manifest, result);
    const pkg = readPackage(basePath, result);
    validatePackage(manifest, pkg, result);
    validateFrontendSource(basePath, manifest, result);
    printResult(result, options.strict);
}

main();
