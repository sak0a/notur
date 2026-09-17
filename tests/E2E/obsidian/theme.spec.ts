import { expect, test } from '@playwright/test';
import { resolve } from 'node:path';

test.beforeEach(async ({ page }) => {
    await page.goto('/preview/');
    await expect(page.locator('html')).toHaveAttribute('data-obsidian', 'dark');
});

test('appearance persists, colors retain meaning, and the desktop layout fits', async ({ page }) => {
    await expect(page.locator('[data-ob-nav]')).toHaveCSS('position', 'fixed');
    await expect(page.locator('.ButtonStyle.danger')).toHaveCSS('color', 'rgb(244, 160, 165)');
    await page.getByRole('button', { name: 'Switch to light mode' }).click();
    await expect(page.locator('html')).toHaveAttribute('data-obsidian', 'light');
    await expect(page.locator('body')).toHaveCSS('background-color', 'rgb(240, 240, 243)');
    await expect(page.locator('.ButtonStyle.danger')).toHaveCSS('color', 'rgb(166, 42, 57)');
    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('data-obsidian', 'light');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
});

test('mobile drawer closes with Escape, restores focus and has no horizontal overflow', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const menu = page.getByRole('button', { name: 'Menu', exact: false });
    await expect(page.locator('[data-ob-nav]')).toBeHidden();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await menu.click();
    await expect(menu).toHaveAttribute('aria-expanded', 'true');
    await expect(page.locator('[data-ob-nav]')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Notur.' })).toBeFocused();
    await page.keyboard.press('Shift+Tab');
    await expect(menu).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(menu).toHaveAttribute('aria-expanded', 'false');
    await expect(menu).toBeFocused();
    await menu.click();
    await page.getByRole('link', { name: 'Files', exact: true }).click();
    await expect(menu).toHaveAttribute('aria-expanded', 'false');
});

test('existing click handlers survive and search is keyboard accessible', async ({ page }) => {
    const search = page.getByRole('button', { name: 'Search servers' });
    await search.focus();
    await page.keyboard.press('Enter');
    await expect(page.locator('#preview-status')).toContainText('original panel handler');
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
    await expect(page.locator('body')).toHaveCSS('background-color', 'rgb(11, 11, 13)');
    for (const selector of ['.style-module_V4CSEpa4', '.style-module_j35sQtg2', '.style-module_HHpjDvv7', '.style-module_tpzh9TL4']) {
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
