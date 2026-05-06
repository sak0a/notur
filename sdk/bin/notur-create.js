#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const readline = require('readline');
const { spawnSync } = require('child_process');

function parseArgs() {
    const args = process.argv.slice(2);
    const options = {
        id: null,
        path: process.cwd(),
        preset: null,
        displayName: null,
        description: null,
        withFrontend: true,
        withApiRoutes: false,
        force: false,
        slot: 'dashboard.widgets',
        packageManager: null,
        install: null,
        createEnv: null,
    };

    for (let i = 0; i < args.length; i++) {
        const arg = args[i];
        if (arg === '--path') {
            options.path = args[++i];
        } else if (arg === '--preset') {
            options.preset = args[++i];
        } else if (arg === '--name' || arg === '--display-name') {
            options.displayName = args[++i];
        } else if (arg === '--description') {
            options.description = args[++i];
        } else if (arg === '--with-frontend') {
            options.withFrontend = true;
        } else if (arg === '--no-frontend') {
            options.withFrontend = false;
        } else if (arg === '--with-api-routes') {
            options.withApiRoutes = true;
        } else if (arg === '--no-api-routes') {
            options.withApiRoutes = false;
        } else if (arg === '--force') {
            options.force = true;
        } else if (arg === '--slot') {
            options.slot = args[++i];
        } else if (arg === '--package-manager') {
            options.packageManager = args[++i];
        } else if (arg === '--install') {
            options.install = true;
        } else if (arg === '--no-install') {
            options.install = false;
        } else if (arg === '--env') {
            options.createEnv = true;
        } else if (arg === '--no-env') {
            options.createEnv = false;
        } else if (arg === '--help' || arg === '-h') {
            usage(0);
        } else if (!arg.startsWith('-') && !options.id) {
            options.id = arg;
        } else {
            console.error(`Unknown argument: ${arg}`);
            usage(1);
        }
    }

    if (options.preset && !['frontend', 'backend', 'full', 'minimal'].includes(options.preset)) {
        console.error('Error: preset must be one of frontend, backend, full, or minimal.');
        process.exit(1);
    }

    if (options.packageManager && !['npm', 'pnpm', 'yarn', 'bun'].includes(options.packageManager)) {
        console.error('Error: package manager must be one of npm, pnpm, yarn, or bun.');
        process.exit(1);
    }

    return options;
}

function usage(code) {
    console.log(`Usage:
  npx notur-create acme/red-button [options]
  npx @notur/sdk create acme/red-button [options]

Options:
  --path <dir>        Parent directory for the generated extension
  --preset <name>     frontend, backend, full, or minimal
  --name <name>       Display name for extension.yaml
  --description <txt> Description for extension.yaml
  --slot <slot>       Initial frontend slot (default: dashboard.widgets)
  --package-manager   npm, pnpm, yarn, or bun
  --install           Install frontend dependencies after scaffolding
  --env               Create .env from .env.example
  --no-frontend       Generate PHP/manifest only
  --with-api-routes   Include a client API route stub
  --force             Allow writing into an existing empty directory`);
    process.exit(code);
}

function isInteractive() {
    return process.stdin.isTTY && process.stdout.isTTY;
}

function prompt(question, defaultValue = '') {
    const suffix = defaultValue ? ` (${defaultValue})` : '';
    const rl = readline.createInterface({
        input: process.stdin,
        output: process.stdout,
    });

    return new Promise(resolve => {
        rl.question(`${question}${suffix}: `, answer => {
            rl.close();
            resolve(answer.trim() || defaultValue);
        });
    });
}

async function select(question, choices, defaultChoice) {
    const labels = choices.map(choice => choice.value).join('/');
    while (true) {
        const answer = await prompt(`${question} [${labels}]`, defaultChoice);
        const match = choices.find(choice => choice.value === answer);
        if (match) {
            return match.value;
        }
        console.log(`Choose one of: ${labels}`);
    }
}

async function confirm(question, defaultValue = false) {
    const answer = await prompt(`${question} [${defaultValue ? 'Y/n' : 'y/N'}]`, defaultValue ? 'y' : 'n');
    return ['y', 'yes'].includes(answer.toLowerCase());
}

function validateId(id) {
    return /^[a-z0-9-]+\/[a-z0-9-]+$/.test(id);
}

function applyPreset(options) {
    const preset = options.preset || 'frontend';
    const features = {
        frontend: true,
        apiRoutes: false,
    };

    if (preset === 'minimal') {
        features.frontend = false;
        features.apiRoutes = false;
    } else if (preset === 'backend') {
        features.frontend = false;
        features.apiRoutes = true;
    } else if (preset === 'full') {
        features.frontend = true;
        features.apiRoutes = true;
    }

    if (options.withFrontend === false) {
        features.frontend = false;
    } else if (options.withFrontend === true && options.preset === null) {
        features.frontend = true;
    }

    if (options.withApiRoutes === true) {
        features.apiRoutes = true;
    } else if (options.withApiRoutes === false && options.preset === null) {
        features.apiRoutes = false;
    }

    options.withFrontend = features.frontend;
    options.withApiRoutes = features.apiRoutes;
    options.preset = preset;

    return options;
}

async function resolveOptions(options) {
    if (!options.id && !isInteractive()) {
        console.error('Error: extension id is required in non-interactive mode.');
        usage(1);
    }

    if (options.id && !validateId(options.id)) {
        console.error('Error: extension id must use vendor/name format with lowercase letters, numbers, and hyphens.');
        process.exit(1);
    }

    if (!options.id) {
        console.log('Notur extension setup');
        while (!options.id) {
            const id = await prompt('Extension id', 'acme/red-button');
            if (validateId(id)) {
                options.id = id;
            } else {
                console.log('Use vendor/name format with lowercase letters, numbers, and hyphens.');
            }
        }
    }

    const [, name] = options.id.split('/');
    if (!options.displayName && isInteractive()) {
        options.displayName = await prompt('Display name', displayName(name));
    }
    if (!options.description && isInteractive()) {
        options.description = await prompt('Description', 'A Notur extension');
    }
    if (!options.preset && isInteractive()) {
        options.preset = await select('Preset', [
            { value: 'frontend' },
            { value: 'backend' },
            { value: 'full' },
            { value: 'minimal' },
        ], 'frontend');
    }
    if (isInteractive() && ['frontend', 'full'].includes(options.preset) && options.withFrontend !== false) {
        options.slot = await prompt('Initial frontend slot', options.slot);
    }
    if (!options.packageManager && isInteractive() && options.withFrontend !== false && options.preset !== 'minimal' && options.preset !== 'backend') {
        options.packageManager = await select('Package manager', [
            { value: 'npm' },
            { value: 'pnpm' },
            { value: 'yarn' },
            { value: 'bun' },
        ], 'npm');
    }
    if (options.createEnv === null && isInteractive()) {
        options.createEnv = await confirm('Create .env from .env.example?', false);
    }
    if (options.install === null && isInteractive() && options.withFrontend !== false && options.preset !== 'minimal' && options.preset !== 'backend') {
        options.install = await confirm('Install frontend dependencies now?', false);
    }

    return applyPreset(options);
}

function studly(value) {
    return value
        .split('-')
        .filter(Boolean)
        .map(part => part.charAt(0).toUpperCase() + part.slice(1))
        .join('');
}

function displayName(value) {
    return value
        .split('-')
        .filter(Boolean)
        .map(part => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function libraryName(id) {
    return id
        .split(/[\/-]/)
        .filter(Boolean)
        .map(studly)
        .join('');
}

function yamlString(value) {
    return String(value).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
}

function writeFile(filePath, content) {
    fs.mkdirSync(path.dirname(filePath), { recursive: true });
    fs.writeFileSync(filePath, content);
    console.log(`  created ${path.relative(process.cwd(), filePath)}`);
}

function ensureTarget(target, force) {
    if (!fs.existsSync(target)) {
        fs.mkdirSync(target, { recursive: true });
        return;
    }

    const entries = fs.readdirSync(target);
    if (entries.length > 0 || !force) {
        console.error(`Error: target directory already exists: ${target}`);
        console.error('Use --force only for an existing empty directory.');
        process.exit(1);
    }
}

function manifestTemplate({ id, vendor, name, className, namespace, frontend, apiRoutes, display, description }) {
    const frontendSection = frontend
        ? `
frontend:
  bundle: "resources/frontend/dist/extension.js"
`
        : '';
    const backendSection = apiRoutes
        ? `
backend:
  routes:
    api-client: "src/routes/api-client.php"
`
        : '';

    return `notur: "1.0"
id: "${id}"
name: "${yamlString(display)}"
version: "1.0.0"
description: "${yamlString(description)}"
license: "MIT"

requires:
  notur: "^1.0"
  pterodactyl: "^1.12"
  php: "^8.2"

entrypoint: "${namespace.replace(/\\/g, '\\\\')}\\\\${className}"
autoload:
  psr-4:
    "${namespace.replace(/\\/g, '\\\\')}\\\\": "src/"
${backendSection}
${frontendSection}`;
}

function phpTemplate({ namespace, className }) {
    return `<?php

declare(strict_types=1);

namespace ${namespace};

use Notur\\Support\\NoturExtension;

class ${className} extends NoturExtension
{
}
`;
}

function apiRouteTemplate() {
    return `<?php

use Illuminate\\Support\\Facades\\Route;

Route::get('/ping', function () {
    return response()->json([
        'message' => 'pong',
    ]);
});
`;
}

function frontendTemplate({ id, slot }) {
    return `import * as React from 'react';
import { createExtension } from '@notur/sdk';

const ExampleButton: React.FC = () => {
    return (
        <button
            style={{
                background: '#dc2626',
                color: '#fff',
                border: 0,
                borderRadius: '6px',
                padding: '8px 12px',
                fontWeight: 600,
                cursor: 'pointer',
            }}
            onClick={() => alert('Hello from Notur')}
        >
            Red Button
        </button>
    );
};

createExtension({
    id: '${id}',
    slots: [
        {
            slot: '${slot}',
            component: ExampleButton,
            order: 10,
        },
    ],
});
`;
}

function packageTemplate(id) {
    return `${JSON.stringify({
        name: id.replace('/', '-'),
        version: '1.0.0',
        private: true,
        scripts: {
            build: 'webpack-cli --mode production --config webpack.config.js',
            dev: 'webpack-cli --mode development --watch --config webpack.config.js',
            pack: 'notur-pack',
            push: 'notur-push',
        },
        peerDependencies: {
            react: '^16.14.0',
            'react-dom': '^16.14.0',
        },
        devDependencies: {
            '@notur/sdk': '^1.4.5',
            '@types/react': '^16.14.0',
            '@types/react-dom': '^16.9.0',
            react: '^16.14.0',
            'react-dom': '^16.14.0',
            'ts-loader': '^9.5.0',
            typescript: '^5.3.0',
            webpack: '^5.90.0',
            'webpack-cli': '^6.0.0',
        },
    }, null, 2)}
`;
}

function tsconfigTemplate() {
    return `{
  "compilerOptions": {
    "target": "ES2019",
    "module": "ESNext",
    "moduleResolution": "Node",
    "jsx": "react",
    "strict": true,
    "esModuleInterop": true,
    "skipLibCheck": true,
    "forceConsistentCasingInFileNames": true
  },
  "include": ["resources/frontend/src/**/*"]
}
`;
}

function webpackTemplate(libName) {
    return `const path = require('path');
const base = require('@notur/sdk/webpack.extension.config');

module.exports = {
    ...base,
    entry: './resources/frontend/src/index.tsx',
    output: {
        ...base.output,
        filename: 'extension.js',
        path: path.resolve(__dirname, 'resources/frontend/dist'),
        library: {
            ...base.output.library,
            name: '__NOTUR_EXT_${libName}__',
            type: 'umd',
        },
    },
};
`;
}

function readmeTemplate(id) {
    return `# ${id}

Development:

\`\`\`bash
npm install
npm run build
npx notur-pack
\`\`\`

Remote push to a Notur-enabled panel:

\`\`\`bash
cp .env.example .env
npm run push
\`\`\`

Or pass values directly:

\`\`\`bash
npx notur-push --host https://panel.example.com --key notur_xxx
\`\`\`
`;
}

function installCommand(packageManager) {
    if (packageManager === 'pnpm') return ['pnpm', ['install']];
    if (packageManager === 'yarn') return ['yarn', ['install']];
    if (packageManager === 'bun') return ['bun', ['install']];
    return ['npm', ['install']];
}

function runScriptCommand(packageManager, script) {
    if (packageManager === 'bun') return `bun run ${script}`;
    if (packageManager === 'pnpm') return `pnpm run ${script}`;
    if (packageManager === 'yarn') return `yarn ${script}`;
    return `npm run ${script}`;
}

function runInstall(target, packageManager) {
    const [command, args] = installCommand(packageManager || 'npm');
    console.log(`\nRunning ${command} ${args.join(' ')}...`);
    const result = spawnSync(command, args, {
        cwd: target,
        stdio: 'inherit',
    });

    if (result.status !== 0) {
        console.warn(`Dependency install failed. Run ${command} ${args.join(' ')} manually in ${target}.`);
    }
}

async function main() {
    const options = await resolveOptions(parseArgs());
    const [vendor, name] = options.id.split('/');
    const namespace = `${studly(vendor)}\\${studly(name)}`;
    const className = `${studly(name)}Extension`;
    const target = path.resolve(options.path, name);

    ensureTarget(target, options.force);

    console.log(`Scaffolding ${options.id} in ${target}`);

    writeFile(path.join(target, 'extension.yaml'), manifestTemplate({
        id: options.id,
        vendor,
        name,
        namespace,
        className,
        frontend: options.withFrontend,
        apiRoutes: options.withApiRoutes,
        display: options.displayName || displayName(name),
        description: options.description || 'A Notur extension',
    }));
    writeFile(path.join(target, 'src', `${className}.php`), phpTemplate({ namespace, className }));
    if (options.withApiRoutes) {
        writeFile(path.join(target, 'src/routes/api-client.php'), apiRouteTemplate());
    }
    writeFile(path.join(target, 'README.md'), readmeTemplate(options.id));
    writeFile(path.join(target, '.env.example'), `NOTUR_HOST=https://panel.example.com
NOTUR_PUSH_KEY=notur_xxx
`);
    if (options.createEnv) {
        writeFile(path.join(target, '.env'), `NOTUR_HOST=https://panel.example.com
NOTUR_PUSH_KEY=notur_xxx
`);
    }
    writeFile(path.join(target, '.gitignore'), `node_modules/
vendor/
resources/frontend/dist/
.env
*.notur
*.notur.sha256
*.notur.sig
`);

    if (options.withFrontend) {
        writeFile(path.join(target, 'resources/frontend/src/index.tsx'), frontendTemplate({
            id: options.id,
            slot: options.slot,
        }));
        writeFile(path.join(target, 'package.json'), packageTemplate(options.id));
        writeFile(path.join(target, 'tsconfig.json'), tsconfigTemplate());
        writeFile(path.join(target, 'webpack.config.js'), webpackTemplate(libraryName(options.id)));
    }

    if (options.install && options.withFrontend) {
        runInstall(target, options.packageManager);
    }

    console.log('\nNext steps:');
    if (options.withFrontend) {
        console.log(`  cd ${target}`);
        if (!options.install) {
            const [command, args] = installCommand(options.packageManager || 'npm');
            console.log(`  ${command} ${args.join(' ')}`);
        }
        console.log(`  ${runScriptCommand(options.packageManager || 'npm', 'build')}`);
        console.log('  npx notur-pack');
    } else {
        console.log(`  cd ${target}`);
        console.log('  npx notur-pack');
    }
}

main().catch(error => {
    console.error(error?.message || String(error));
    process.exit(1);
});
