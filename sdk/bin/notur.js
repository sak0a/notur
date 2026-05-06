#!/usr/bin/env node

const path = require('path');
const { spawnSync } = require('child_process');

const commands = {
    create: 'notur-create.js',
    pack: 'notur-pack.js',
    keygen: 'notur-keygen.js',
    push: 'notur-push.js',
};

const [command, ...args] = process.argv.slice(2);

if (!command || command === '--help' || command === '-h') {
    console.log(`Notur SDK CLI

Usage:
  notur create <vendor/name> [options]
  notur pack [path] [options]
  notur push [path] --host <url> --key <token>
  notur keygen

Standalone bins are also available:
  notur-create, notur-pack, notur-push, notur-keygen`);
    process.exit(command ? 0 : 1);
}

const script = commands[command];
if (!script) {
    console.error(`Unknown command: ${command}`);
    console.error('Run "notur --help" for usage.');
    process.exit(1);
}

const result = spawnSync(process.execPath, [path.join(__dirname, script), ...args], {
    stdio: 'inherit',
});

process.exit(result.status ?? 1);
