import { expect, test, type BrowserContext, type Locator, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import {
    copyFileSync,
    existsSync,
    mkdirSync,
    mkdtempSync,
    readdirSync,
    readFileSync,
    rmSync,
    statSync,
    writeFileSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, relative } from 'node:path';

const env = (name: string, fallback: string): string => process.env[name] ?? fallback;

const HELLO_WORLD_ID = 'notur/hello-world';
const FULL_EXTENSION_ID = 'notur/full-extension';
const BROKEN_EXTENSION_ID = 'broken/remove-fail';
const HELLO_WORLD_FIXTURE_PATH = '/opt/notur/examples/hello-world';
const NON_ADMIN_EMAIL = env('E2E_USER_EMAIL', 'user@example.com');
const NON_ADMIN_PASSWORD = env('E2E_USER_PASSWORD', 'notur-user-password');
const APP_URL = env('APP_URL', 'http://127.0.0.1');

const dbConfig = {
    host: env('DB_HOST', 'db'),
    port: env('DB_PORT', '3306'),
    database: env('DB_DATABASE', 'panel'),
    username: env('DB_USERNAME', 'pterodactyl'),
    password: env('DB_PASSWORD', 'pterodactyl'),
};

function escapeSql(value: string): string {
    return value.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

function mysqlScalar(query: string): string {
    return execFileSync(
        'mysql',
        [
            '--ssl=0',
            '-h',
            dbConfig.host,
            '-P',
            dbConfig.port,
            '-u',
            dbConfig.username,
            `-p${dbConfig.password}`,
            dbConfig.database,
            '-N',
            '-B',
            '-e',
            query,
        ],
        { encoding: 'utf8' },
    ).trim();
}

function isExcludedArchivePath(relativePath: string): boolean {
    const segments = relativePath.split('/');
    const basename = segments.at(-1) ?? '';

    return (
        segments.includes('node_modules') ||
        segments.includes('vendor') ||
        segments.includes('.git') ||
        segments.includes('.idea') ||
        segments.includes('.vscode') ||
        basename === 'checksums.json' ||
        basename.endsWith('.notur') ||
        basename.endsWith('.notur.sha256') ||
        basename.endsWith('.notur.tar.gz') ||
        basename.endsWith('.notur.sig')
    );
}

function collectArchiveFiles(sourcePath: string): string[] {
    const files: string[] = [];

    const walk = (directory: string): void => {
        for (const entry of readdirSync(directory)) {
            const fullPath = join(directory, entry);
            const relativePath = relative(sourcePath, fullPath).replaceAll('\\', '/');

            if (isExcludedArchivePath(relativePath)) {
                continue;
            }

            const stats = statSync(fullPath);
            if (stats.isDirectory()) {
                walk(fullPath);
            } else if (stats.isFile()) {
                files.push(relativePath);
            }
        }
    };

    walk(sourcePath);

    return files.sort();
}

function buildNoturArchiveFromFixture(): string {
    if (!existsSync(HELLO_WORLD_FIXTURE_PATH)) {
        throw new Error(`Fixture directory does not exist: ${HELLO_WORLD_FIXTURE_PATH}`);
    }

    const workDir = mkdtempSync(join(tmpdir(), 'notur-e2e-archive-'));
    const sourceCopy = join(workDir, 'source');
    const checksums: Record<string, string> = {};

    for (const relativePath of collectArchiveFiles(HELLO_WORLD_FIXTURE_PATH)) {
        const sourceFile = join(HELLO_WORLD_FIXTURE_PATH, relativePath);
        const targetFile = join(sourceCopy, relativePath);

        mkdirSync(dirname(targetFile), { recursive: true });
        copyFileSync(sourceFile, targetFile);
        checksums[relativePath] = createHash('sha256').update(readFileSync(sourceFile)).digest('hex');
    }

    writeFileSync(
        join(sourceCopy, 'checksums.json'),
        `${JSON.stringify(checksums, null, 2)}\n`,
    );

    const archivePath = join(workDir, 'hello-world.notur');
    execFileSync('tar', ['-czf', archivePath, '-C', sourceCopy, 'checksums.json', ...Object.keys(checksums)]);

    return archivePath;
}

function buildInvalidNoturArchive(): string {
    const workDir = mkdtempSync(join(tmpdir(), 'notur-e2e-invalid-archive-'));
    const archivePath = join(workDir, 'invalid.notur');

    writeFileSync(archivePath, 'this is not a tar archive');

    return archivePath;
}

async function loginAsAdmin(page: Page): Promise<void> {
    await loginAs(page, env('E2E_ADMIN_EMAIL', 'admin@example.com'), env('E2E_ADMIN_PASSWORD', 'notur-admin-password'));
}

async function loginAs(page: Page, user: string, password: string): Promise<void> {
    await page.goto('/auth/login');
    await expect(page.getByText('Login to Continue')).toBeVisible();
    await expect(page.locator('input[name="username"]')).toBeVisible();

    const loginResult = await page.evaluate(
        async ({ user, password }) => {
            const csrfResponse = await fetch('/sanctum/csrf-cookie', {
                credentials: 'include',
            });

            const xsrfCookie = document.cookie
                .split('; ')
                .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
                ?.split('=')
                .slice(1)
                .join('=');

            if (!xsrfCookie) {
                return {
                    ok: false,
                    status: csrfResponse.status,
                    body: 'XSRF-TOKEN cookie missing after /sanctum/csrf-cookie',
                };
            }

            const response = await fetch('/auth/login', {
                method: 'POST',
                credentials: 'include',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': decodeURIComponent(xsrfCookie),
                },
                body: JSON.stringify({
                    user,
                    password,
                }),
            });

            const body = await response.text();

            return {
                ok: response.ok,
                status: response.status,
                body,
            };
        },
        {
            user,
            password,
        },
    );

    expect(
        loginResult.ok,
        `Login request failed with HTTP ${loginResult.status}\n${loginResult.body}`,
    ).toBeTruthy();

    const payload = JSON.parse(loginResult.body);
    expect(payload?.data?.complete, `Unexpected login payload: ${loginResult.body}`).toBeTruthy();

    await page.goto('/');
}

function extensionRow(page: Page, extensionId: string): Locator {
    return page.locator(`[data-testid="extension-row"][data-extension-id="${extensionId}"]`);
}

async function openExtensionsPage(page: Page): Promise<void> {
    await page.goto('/admin/notur/extensions');
    await expect(page).toHaveURL(/\/admin\/notur\/extensions$/);
    await expect(page.locator('h1')).toContainText('Extensions');
    await expect(page.locator('[data-testid="installed-extensions-table"]')).toBeVisible();
}

async function extensionRouteStatus(page: Page, path: string): Promise<number> {
    return page.evaluate(async (targetPath) => {
        const response = await fetch(targetPath, {
            headers: { Accept: 'application/json' },
            credentials: 'include',
        });

        return response.status;
    }, path);
}

async function waitForPanel(page: Page): Promise<void> {
    await expect
        .poll(
            async () => {
                try {
                    const response = await page.request.get('/auth/login', {
                        failOnStatusCode: false,
                        timeout: 5_000,
                    });

                    return response.status();
                } catch {
                    return 0;
                }
            },
            {
                timeout: 120_000,
                intervals: [500, 1_000, 2_000, 2_000, 5_000],
            },
        )
        .toBeGreaterThan(0);
}

async function expectFlashSuccess(page: Page, expectedText: string): Promise<void> {
    try {
        await expect(page.locator('[data-testid="notur-flash-success"]')).toContainText(expectedText);
    } catch (error) {
        const errorFlash = await page.locator('[data-testid="notur-flash-error"]').textContent().catch(() => null);
        const bodyText = await page.locator('body').innerText().catch(() => '');
        const diagnostic = [
            `Expected success flash containing: ${expectedText}`,
            `URL: ${page.url()}`,
            errorFlash ? `Error flash: ${errorFlash.trim()}` : 'Error flash: <missing>',
            `Body excerpt: ${bodyText.trim().slice(0, 1200)}`,
        ].join('\n');

        throw new Error(`${diagnostic}\n\n${error instanceof Error ? error.message : String(error)}`);
    }
}

test.describe.serial('Notur admin browser E2E', () => {
    let adminContext: BrowserContext;
    let adminPage: Page;

    test.beforeAll(async ({ browser }) => {
        adminContext = await browser.newContext();
        adminPage = await adminContext.newPage();
        await waitForPanel(adminPage);
        await loginAsAdmin(adminPage);
    });

    test.afterAll(async () => {
        await adminContext.close();
    });

    test.beforeEach(async () => {
        await openExtensionsPage(adminPage);
    });

    test('install page loads and extension list renders', async () => {
        const page = adminPage;
        await expect(extensionRow(page, HELLO_WORLD_ID)).toContainText('Hello World');
        await expect(extensionRow(page, FULL_EXTENSION_ID)).toContainText('Notur Full Example');
        await expect(extensionRow(page, BROKEN_EXTENSION_ID)).toContainText('Broken Remove Fixture');
    });

    test('enabled extension renders through the runtime bridge on panel pages', async () => {
        const page = adminPage;

        await page.goto('/');

        await expect(page.locator('#notur-slot-dashboard\\.widgets')).toBeAttached();
        await expect
            .poll(() =>
                page.evaluate(() => Boolean((window as any).__NOTUR__?.diagnostics?.bridgeScriptLoaded)),
            )
            .toBe(true);
        await expect
            .poll(() =>
                page.evaluate(() =>
                    ((window as any).__NOTUR__?.registry?.getExtensions?.() ?? [])
                        .map((extension: { id: string }) => extension.id)
                        .includes('notur/hello-world'),
                ),
            )
            .toBe(true);
        await expect
            .poll(() =>
                page.evaluate(() =>
                    ((window as any).__NOTUR__?.registry?.getRoutes?.('dashboard') ?? [])
                        .some((route: { extensionId: string; path: string }) =>
                            route.extensionId === 'notur/hello-world' && route.path === '/hello',
                        ),
                ),
            )
            .toBe(true);

        await expect(page.getByText('Hello World Extension')).toBeVisible({ timeout: 20_000 });
        await expect(page.getByText('Hello from Notur!')).toBeVisible();
    });

    test('extension API rejects guests and responds for authenticated panel users', async ({ browser }) => {
        const guestContext = await browser.newContext();

        try {
            const guestResponse = await guestContext.request.get(
                new URL('/api/client/notur/notur/hello-world/greet', APP_URL).toString(),
                {
                    failOnStatusCode: false,
                    maxRedirects: 0,
                },
            );

            expect(guestResponse.status()).not.toBe(200);
        } finally {
            await guestContext.close();
        }

        const response = await adminPage.request.get('/api/client/notur/notur/hello-world/greet', {
            failOnStatusCode: false,
        });
        const payload = await response.json();

        expect(response.status()).toBe(200);
        expect(payload.message).toBe('Hello from Notur!');
        expect(payload.extension).toBe(HELLO_WORLD_ID);
    });

    test('extension details page loads', async () => {
        const page = adminPage;
        await extensionRow(page, FULL_EXTENSION_ID).getByTestId('extension-details-link').click();

        await expect(page.getByRole('heading', { name: /Notur Full Example/ })).toBeVisible();
        await expect(page.locator('tr', { hasText: 'Extension ID' }).getByText(FULL_EXTENSION_ID, { exact: true })).toBeVisible();
        await expect(page.getByText('Extension Details')).toBeVisible();
    });

    test('settings form saves values and survives reload', async () => {
        const page = adminPage;
        await extensionRow(page, FULL_EXTENSION_ID).getByTestId('extension-details-link').click();

        await expect(page.getByTestId('extension-settings-form')).toBeVisible();
        await page.locator('input[name="settings[feature_enabled]"]').setChecked(false);
        await page.locator('input[name="settings[request_limit]"]').fill('42');
        await page.getByRole('button', { name: 'Save Settings' }).click();

        await expect(page.locator('[data-testid="notur-flash-success"]')).toContainText('Settings saved.');
        await page.reload();

        await expect(page.locator('input[name="settings[feature_enabled]"]')).not.toBeChecked();
        await expect(page.locator('input[name="settings[request_limit]"]')).toHaveValue('42');
        await expect
            .poll(() =>
                mysqlScalar(
                    `SELECT JSON_UNQUOTE(value) FROM notur_settings WHERE extension_id='${escapeSql(FULL_EXTENSION_ID)}' AND \`key\`='request_limit' LIMIT 1;`,
                ),
            )
            .toBe('42');
    });

    test('invalid settings submission shows validation errors without changing saved values', async () => {
        const page = adminPage;
        await extensionRow(page, FULL_EXTENSION_ID).getByTestId('extension-details-link').click();

        await page.locator('input[name="settings[request_limit]"]').fill('');
        await page.getByRole('button', { name: 'Save Settings' }).click();

        const requestLimitField = page.locator('[data-testid="extension-setting-field"][data-setting-key="request_limit"]');
        await expect(requestLimitField).toContainText('This field is required.');
        await expect
            .poll(() =>
                mysqlScalar(
                    `SELECT JSON_UNQUOTE(value) FROM notur_settings WHERE extension_id='${escapeSql(FULL_EXTENSION_ID)}' AND \`key\`='request_limit' LIMIT 1;`,
                ),
            )
            .toBe('42');
    });

    test('settings preview JSON reflects saved backend state', async () => {
        const page = adminPage;
        const response = await page.request.get(`/admin/notur/extensions/${FULL_EXTENSION_ID}/settings/preview`);
        const payload = await response.json();

        expect(response?.ok()).toBeTruthy();
        expect(payload.data.extension.id).toBe(FULL_EXTENSION_ID);
        expect(payload.data.values.request_limit).toBe(42);
        expect(payload.data.values.feature_enabled).toBe(false);
    });

    test('health page loads and extension health checks render', async () => {
        const page = adminPage;
        await page.goto('/admin/notur/health');

        await expect(page.locator('h1')).toContainText('Notur Health');
        const healthCard = page.locator(`[data-testid="extension-health-card"][data-extension-id="${FULL_EXTENSION_ID}"]`);
        await expect(healthCard).toContainText('Notur Full Example');
        await expect(healthCard.locator('[data-testid="extension-health-check"][data-check-id="configuration"]')).toContainText(
            'Configuration',
        );
    });

    test('diagnostics page loads and reports admin bridge skip state', async () => {
        const page = adminPage;
        await page.goto('/admin/notur/diagnostics');

        await expect(page.locator('h1')).toContainText('Notur Diagnostics');
        await expect(page.getByText('General System Info')).toBeVisible();
        await expect(page.locator('#notur-diagnostics-json')).toContainText('"status": "error"');
        await expect(page.locator('#notur-diagnostics-json')).toContainText('"reason": "bridge_script_load_failed"');
        await expect(page.locator('#notur-diagnostics-json')).toContainText('Bridge bootstrap skipped on admin routes.');
    });

    test('slots page filter shows and clears no-results state', async () => {
        const page = adminPage;
        await page.goto('/admin/notur/slots');

        await expect(page.locator('h1')).toContainText('Notur Slots');
        await expect(page.locator('code', { hasText: 'server.settings.before' }).first()).toBeVisible();

        await page.locator('#slot-search').fill('no-slot-should-match-this-query');
        await expect(page.locator('#slot-no-results')).toBeVisible();

        await page.locator('#slot-search-clear').click();
        await expect(page.locator('#slot-no-results')).toBeHidden();
        await expect(page.locator('code', { hasText: 'server.settings.before' }).first()).toBeVisible();
    });

    test('empty install submit surfaces an error without changing extension state', async () => {
        const page = adminPage;

        await page.locator('form', { has: page.locator('input[name="registry_id"]') }).getByRole('button', { name: 'Install' }).click();

        await expect(page.locator('[data-testid="notur-flash-error"]')).toContainText(
            'Please provide a registry ID or upload a .notur file.',
        );
        await expect
            .poll(() =>
                mysqlScalar(
                    `SELECT COUNT(*) FROM notur_extensions WHERE extension_id='vendor/extension-name';`,
                ),
            )
            .toBe('0');
    });

    test('bad registry id validation is visible and does not create state', async () => {
        const page = adminPage;

        await page.locator('input[name="registry_id"]').fill('bad-id');
        await page.locator('form', { has: page.locator('input[name="registry_id"]') }).getByRole('button', { name: 'Install' }).click();

        await expect(page.locator('[data-testid="notur-validation-errors"]')).toContainText(
            'Registry ID must be in vendor/name format.',
        );
        await expect
            .poll(() =>
                mysqlScalar(
                    `SELECT COUNT(*) FROM notur_extensions WHERE extension_id='bad-id';`,
                ),
            )
            .toBe('0');
    });

    test('invalid archive upload surfaces an error without partial install state', async () => {
        const page = adminPage;
        const archivePath = buildInvalidNoturArchive();

        try {
            await page.locator('input[name="archive"]').setInputFiles(archivePath);
            await page.locator('form', { has: page.locator('input[name="archive"]') }).getByRole('button', { name: 'Install' }).click();

            await expect(page.locator('[data-testid="notur-flash-error"]')).toContainText('Installation failed:');
            await expect(page.locator('[data-testid="notur-flash-error"]')).toContainText('Archive extraction failed:');
            await expect
                .poll(() =>
                    mysqlScalar(
                        `SELECT COUNT(*) FROM notur_extensions WHERE extension_id='invalid/archive';`,
                    ),
                )
                .toBe('0');
        } finally {
            rmSync(dirname(archivePath), { recursive: true, force: true });
        }
    });

    test('enable extension from admin UI', async () => {
        const page = adminPage;
        const row = extensionRow(page, FULL_EXTENSION_ID);

        await expect(row.getByTestId('extension-status')).toContainText('Disabled');

        await row.getByRole('button', { name: `Enable ${FULL_EXTENSION_ID}` }).click();

        await expect(page.locator('[data-testid="notur-flash-success"]')).toContainText(
            `Extension '${FULL_EXTENSION_ID}' has been enabled.`,
        );

        await page.reload();
        await expect(extensionRow(page, FULL_EXTENSION_ID).getByTestId('extension-status')).toContainText('Enabled');

        await expect.poll(() =>
            mysqlScalar(
                `SELECT enabled FROM notur_extensions WHERE extension_id='${escapeSql(FULL_EXTENSION_ID)}' LIMIT 1;`,
            ),
        ).toBe('1');

        await expect.poll(async () =>
            extensionRouteStatus(page, '/api/client/notur/notur/full-extension/status'),
        ).toBe(200);
    });

    test('disable extension from admin UI', async () => {
        const page = adminPage;
        const row = extensionRow(page, FULL_EXTENSION_ID);

        await expect(row.getByTestId('extension-status')).toContainText('Enabled');

        await row.getByRole('button', { name: `Disable ${FULL_EXTENSION_ID}` }).click();

        await expect(page.locator('[data-testid="notur-flash-success"]')).toContainText(
            `Extension '${FULL_EXTENSION_ID}' has been disabled.`,
        );

        await page.reload();
        await expect(extensionRow(page, FULL_EXTENSION_ID).getByTestId('extension-status')).toContainText('Disabled');

        await expect.poll(() =>
            mysqlScalar(
                `SELECT enabled FROM notur_extensions WHERE extension_id='${escapeSql(FULL_EXTENSION_ID)}' LIMIT 1;`,
            ),
        ).toBe('0');

        await expect.poll(async () =>
            extensionRouteStatus(page, '/api/client/notur/notur/full-extension/status'),
        ).toBe(404);
    });

    test('failed removal surfaces an error notification instead of false success', async () => {
        const page = adminPage;
        const row = extensionRow(page, BROKEN_EXTENSION_ID);

        page.once('dialog', (dialog) => dialog.accept());
        await row.getByRole('button', { name: `Remove ${BROKEN_EXTENSION_ID}` }).click();

        await expect(page.locator('[data-testid="notur-flash-error"]')).toContainText('Removal failed:');
        await page.reload();

        await expect(extensionRow(page, BROKEN_EXTENSION_ID)).toBeVisible();
        await expect.poll(() =>
            mysqlScalar(
                `SELECT COUNT(*) FROM notur_extensions WHERE extension_id='${escapeSql(BROKEN_EXTENSION_ID)}';`,
            ),
        ).toBe('1');
    });

    test('remove extension via admin UI', async () => {
        const page = adminPage;
        const row = extensionRow(page, HELLO_WORLD_ID);

        await expect(row).toBeVisible();
        await expect(row.getByTestId('extension-status')).toContainText('Enabled');

        await expect.poll(async () =>
            extensionRouteStatus(page, '/api/client/notur/notur/hello-world/greet'),
        ).toBe(200);

        page.once('dialog', (dialog) => dialog.accept());
        await row.getByRole('button', { name: `Remove ${HELLO_WORLD_ID}` }).click();

        await expect(page.locator('[data-testid="notur-flash-success"]')).toContainText(
            `Extension '${HELLO_WORLD_ID}' has been removed.`,
        );

        await page.reload();
        await expect(extensionRow(page, HELLO_WORLD_ID)).toHaveCount(0);

        await expect.poll(() =>
            mysqlScalar(
                `SELECT COUNT(*) FROM notur_extensions WHERE extension_id='${escapeSql(HELLO_WORLD_ID)}';`,
            ),
        ).toBe('0');

        await expect.poll(async () =>
            extensionRouteStatus(page, '/api/client/notur/notur/hello-world/greet'),
        ).toBe(404);
    });

    test('install extension from uploaded archive via admin UI', async () => {
        const page = adminPage;
        const archivePath = buildNoturArchiveFromFixture();

        try {
            await page.locator('input[name="archive"]').setInputFiles(archivePath);
            await page.locator('form', { has: page.locator('input[name="archive"]') }).getByRole('button', { name: 'Install' }).click();

            await expectFlashSuccess(page, 'Extension installed successfully.');
            await expect(extensionRow(page, HELLO_WORLD_ID)).toContainText('Hello World');
            await expect
                .poll(() =>
                    mysqlScalar(
                        `SELECT COUNT(*) FROM notur_extensions WHERE extension_id='${escapeSql(HELLO_WORLD_ID)}';`,
                    ),
                )
                .toBe('1');
            await expect.poll(async () =>
                extensionRouteStatus(page, '/api/client/notur/notur/hello-world/greet'),
            ).toBe(200);

            page.once('dialog', (dialog) => dialog.accept());
            await extensionRow(page, HELLO_WORLD_ID).getByRole('button', { name: `Remove ${HELLO_WORLD_ID}` }).click();
            await expect(page.locator('[data-testid="notur-flash-success"]')).toContainText(
                `Extension '${HELLO_WORLD_ID}' has been removed.`,
            );
        } finally {
            rmSync(dirname(archivePath), { recursive: true, force: true });
        }
    });
});

test('non-admin cannot access Notur admin UI', async ({ page }) => {
    await waitForPanel(page);
    await loginAs(page, NON_ADMIN_EMAIL, NON_ADMIN_PASSWORD);

    const previewResponse = await page.request.get(`/admin/notur/extensions/${FULL_EXTENSION_ID}/settings/preview`, {
        failOnStatusCode: false,
    });
    const response = await page.goto('/admin/notur/extensions');

    expect(previewResponse.status()).toBe(403);
    expect(response?.status()).toBe(403);
    await expect(page.locator('body')).toContainText('403');
});
