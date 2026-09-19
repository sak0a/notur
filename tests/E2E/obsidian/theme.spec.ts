import { expect, test } from '@playwright/test';
import { resolve } from 'node:path';

test.beforeEach(async ({ page }) => {
    await page.goto('/preview/');
    await expect(page.locator('html')).toHaveAttribute('data-obsidian', 'dark');
});

test('appearance persists, colors retain meaning, and the desktop layout fits', async ({ page }) => {
    await expect(page.locator('[data-ob-nav]')).toHaveCSS('position', 'fixed');
    await expect(page.locator('.ButtonStyle.danger')).toHaveCSS('color', 'rgb(255, 255, 255)');
    await page.getByRole('button', { name: 'Switch to light mode' }).click();
    await expect(page.locator('html')).toHaveAttribute('data-obsidian', 'light');
    await expect(page.locator('body')).toHaveCSS('background-color', 'rgb(240, 240, 243)');
    await expect(page.locator('.ButtonStyle.danger')).toHaveCSS('color', 'rgb(255, 255, 255)');
    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('data-obsidian', 'light');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
});

test('mobile drawer closes with Escape, restores focus and has no horizontal overflow', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const menu = page.getByRole('button', { name: 'Menu', exact: false });
    await expect(page.locator('[data-ob-nav]')).toBeVisible();
    await expect(page.locator('[data-ob-subnav]')).toBeHidden();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await menu.click();
    await expect(menu).toHaveAttribute('aria-expanded', 'true');
    await expect(page.locator('[data-ob-nav]')).toBeVisible();
    await expect(page.locator('[data-ob-subnav] a').first()).toBeFocused();
    await page.keyboard.press('Shift+Tab');
    await expect(menu).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(menu).toHaveAttribute('aria-expanded', 'false');
    await expect(menu).toBeFocused();
    await menu.click();
    await page.getByRole('link', { name: 'Files', exact: true }).click();
    await expect(menu).toHaveAttribute('aria-expanded', 'false');
});

test('inline search supports results, empty and error states without a modal', async ({ page }) => {
    await page.route('**/api/client?**', route => route.fulfill({ json: { data: [{ attributes: { identifier: 'test1234', name: 'Test server', node: 'Local' } }] } }));
    const search = page.getByRole('searchbox', { name: 'Search servers' });
    await search.fill('Test');
    const result = page.locator('.ob-search-results a');
    await expect(result).toHaveText('Test serverLocal');
    await search.press('ArrowDown');
    await expect(result).toBeFocused();
    await result.press('Escape');
    await expect(page.locator('.ob-search-results')).toBeHidden();
    await expect(search).toBeFocused();
    await page.route('**/api/client?**', route => route.fulfill({ json: { data: [] } }));
    await search.fill('Missing');
    await expect(page.locator('.ob-search-results')).toContainText('No servers found');
    await page.route('**/api/client?**', route => route.fulfill({ status: 500, body: '{}' }));
    await search.fill('Failure');
    await expect(page.locator('.ob-search-results')).toContainText('Search unavailable');
    await search.fill('');
    await expect(page.locator('.ob-search-results')).toBeHidden();
    await page.getByRole('link', { name: 'Files', exact: true }).click();
    await expect(page.locator('#preview-status')).toContainText('Files');
});

test('lazy styles and replacement navigation are adapted; uninstall restores the DOM', async ({ page }) => {
    await page.evaluate(() => {
        const nav = document.querySelector('.preview-nav')!;
        const fresh = nav.cloneNode(true) as HTMLElement;
        [fresh, ...Array.from(fresh.querySelectorAll('*'))].forEach(element => {
            Array.from(element.attributes).filter(attribute => attribute.name.startsWith('data-ob-')).forEach(attribute => element.removeAttribute(attribute.name));
        });
        nav.replaceWith(fresh);
        const style = document.createElement('style');
        style.textContent = '.lazy-card {--tw-bg-opacity:1;background: hsl(209 20% 25% / var(--tw-bg-opacity)); color: hsl(216, 33%, 97%)}';
        document.head.append(style);
        const card = document.createElement('div');
        card.className = 'lazy-card'; card.textContent = 'Lazy route'; document.body.append(card);
    });
    await expect(page.locator('[data-ob-nav]')).toHaveCount(1);
    await expect(page.locator('.lazy-card')).toHaveCSS('backdrop-filter', 'blur(16px)');
    await page.evaluate(() => (window as any).destroyPreviewTheme());
    await expect(page.locator('html')).not.toHaveAttribute('data-obsidian');
    await expect(page.locator('[data-obsidian-style], .ob-controls, [data-ob-nav]')).toHaveCount(0);
    await expect(page.locator('.preview-nav')).toHaveCSS('position', 'static');
    await expect(page.locator('body')).toHaveCSS('padding-left', '0px');
});

test('actual compiled module styles receive glass surfaces without readable class names', async ({ page }) => {
    await expect(page.locator('body')).toHaveCSS('background-color', 'rgb(5, 5, 6)');
    for (const selector of ['.style-module_V4CSEpa4', '.style-module_j35sQtg2', '.style-module_HHpjDvv7']) {
        const element = page.locator(selector).first();
        await expect(element).toHaveCSS('backdrop-filter', 'blur(16px)');
        const background = await element.evaluate(node => getComputedStyle(node).backgroundColor);
        expect(background).toMatch(/(?:rgba\(.+, 0\.|\/ 0\.)/); // Translucency, not an opaque stock fill.
        await expect(element).toHaveCSS('border-top-width', '1px');
    }
    const row = page.locator('.style-module_HHpjDvv7').first();
    const before = await row.evaluate(node => getComputedStyle(node).backgroundColor);
    await row.hover();
    await expect.poll(() => row.evaluate(node => getComputedStyle(node).backgroundColor)).not.toBe(before);
    await page.getByRole('button', { name: 'Switch to light mode' }).click();
    await expect(row).toHaveCSS('backdrop-filter', 'blur(16px)');
    await page.locator('input[aria-label="Select world"]').check();
    await expect(page.locator('input[aria-label="Select world"]')).toBeChecked();
});

test('real React-owned xterm canvas uses black in both modes and restores its original theme', async ({ page }) => {
    await page.addScriptTag({ path: resolve('node_modules/react/umd/react.development.js') });
    await page.addScriptTag({ path: resolve('node_modules/react-dom/umd/react-dom.development.js') });
    await page.addScriptTag({ path: resolve('node_modules/xterm/lib/xterm.js') });
    await page.addStyleTag({ path: resolve('node_modules/xterm/css/xterm.css') });
    await page.evaluate(() => {
        const { React, ReactDOM, Terminal } = window as any;
        const target = document.createElement('div'); document.body.append(target);
        function ConsoleFixture() {
            const ref = React.useRef(null);
            const terminal = React.useMemo(() => new Terminal({ rows: 4, cols: 60, theme: { background: '#131a20', red: '#E54B4B' } }), []);
            React.useEffect(() => { terminal.open(ref.current); terminal.write('Console rendering test'); (window as any).testTerminal = terminal; return () => terminal.dispose(); }, []);
            return React.createElement('div', { className: 'relative' },
                React.createElement('div', { className: 'hashed-frame' }, React.createElement('div', null, React.createElement('div', { id: 'style-module_randomhash', ref }))),
                React.createElement('input', { 'aria-label': 'Real terminal command' }));
        }
        ReactDOM.render(React.createElement(ConsoleFixture), target);
    });
    await expect.poll(() => page.evaluate(() => (window as any).testTerminal.options.theme.background)).toBe('#09090b');
    await expect(page.locator('[data-ob-terminal-frame]')).toHaveCSS('background-color', 'rgb(9, 9, 11)');
    await expect(page.getByRole('textbox', { name: 'Real terminal command' })).toHaveCSS('background-color', 'rgb(17, 17, 20)');
    await page.getByRole('button', { name: 'Switch to light mode' }).click();
    expect(await page.evaluate(() => (window as any).testTerminal.options.theme.red)).toBe('#E54B4B');
    expect(await page.evaluate(() => (window as any).testTerminal.options.theme.background)).toBe('#09090b');
    await page.evaluate(() => (window as any).destroyPreviewTheme());
    expect(await page.evaluate(() => (window as any).testTerminal.options.theme.background)).toBe('#131a20');
});

test('blocked storage still permits toggling and reduced motion is respected', async ({ page }) => {
    await page.addInitScript(() => {
        Object.defineProperty(window, 'localStorage', { get() { throw new Error('Unavailable'); } });
    });
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.reload();
    await page.getByRole('button', { name: 'Switch to light mode' }).click();
    await expect(page.locator('html')).toHaveAttribute('data-obsidian', 'light');
    await expect(page.locator('.ob-appearance')).toHaveCSS('transition-duration', '1e-05s');
});

test('primary, outline and destructive actions have distinct high-contrast variants', async ({ page }) => {
    const start = page.getByRole('button', { name: 'Start', exact: true });
    const restart = page.getByRole('button', { name: 'Restart', exact: true });
    const stop = page.getByRole('button', { name: 'Stop', exact: true });
    await expect(start).toHaveCSS('background-color', 'rgb(250, 250, 250)');
    await expect(start).toHaveCSS('color', 'rgb(9, 9, 11)');
    await expect(restart).toHaveCSS('background-color', 'rgba(16, 16, 18, 0.84)');
    await expect(stop).toHaveCSS('background-color', 'rgb(220, 38, 38)');
    await expect(stop).toHaveCSS('color', 'rgb(255, 255, 255)');
    await start.hover();
    await expect(start).toHaveCSS('background-color', 'rgb(222, 222, 227)');
    await page.getByRole('button', { name: 'Switch to light mode' }).click();
    await expect(start).toHaveCSS('background-color', 'rgb(24, 24, 27)');
    await expect(start).toHaveCSS('color', 'rgb(250, 250, 250)');
    await start.evaluate((button: HTMLButtonElement) => { button.disabled = true; });
    await expect(start).toBeDisabled();
    await expect(start).toHaveCSS('opacity', '0.5');
});


test('shared navigation pill follows rapid hover, keyboard focus and the active destination', async ({ page }) => {
    const menu = page.locator('[data-ob-subnav]');
    const pill = menu.locator('.ob-nav-highlight');
    const files = menu.getByRole('link', { name: 'Files', exact: true });
    const settings = menu.getByRole('link', { name: 'Settings', exact: true });
    const aligned = async (target: typeof files) => {
        await expect.poll(async () => {
            const [a,b] = await Promise.all([pill.boundingBox(), target.boundingBox()]);
            return !!a && !!b && Math.abs(a.y-b.y)<1 && Math.abs(a.width-b.width)<1;
        }).toBe(true);
    };
    await files.hover();
    await settings.hover();
    await aligned(settings);
    await expect(menu.locator('.ob-nav-highlight')).toHaveCount(1);
    await page.mouse.move(500, 5);
    await aligned(menu.locator('a.active'));
    await files.focus();
    await aligned(files);
    await files.press('Enter');
    await expect(files).toHaveClass(/active/);
    await aligned(files);
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await expect(pill).toHaveCSS('transition-property', 'none');
    await page.evaluate(() => (window as any).destroyPreviewTheme());
    await expect(page.locator('.ob-nav-highlight, [data-ob-motion]')).toHaveCount(0);
});

test('legacy form headings and bodies become one consistently padded card', async ({ page }) => {
    await page.evaluate(() => {
        const card = document.createElement('section');
        card.id = 'account-card-fixture';
        card.innerHTML = '<h2 class="ContentBox___StyledH2-test">Email address</h2><div class="ContentBox___StyledDiv-test"><label>Email<input type="email"></label></div>';
        document.querySelector('main')!.append(card);
    });
    const card = page.locator('#account-card-fixture');
    await expect(card).toHaveAttribute('data-ob-card', '');
    await expect(card).toHaveCSS('padding', '24px');
    await expect(card.locator('h2')).toHaveCSS('border-top-width', '0px');
    await expect(card.locator(':scope > div')).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(card).toHaveCSS('padding', '20px');
});
