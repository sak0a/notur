import { expect, test } from '@playwright/test';

test.beforeEach(async ({ page }) => {
    await page.goto('/preview/');
    await expect(page.locator('html')).toHaveAttribute('data-obsidian', 'dark');
});

test('appearance persists, colors retain meaning, and the desktop layout fits', async ({ page }) => {
    await expect(page.locator('[data-ob-nav]')).toHaveCSS('position', 'fixed');
    await expect(page.locator('.ButtonStyle.danger')).toHaveCSS('background-color', 'rgb(179, 61, 66)');
    await page.getByRole('button', { name: 'Switch to light mode' }).click();
    await expect(page.locator('html')).toHaveAttribute('data-obsidian', 'light');
    await expect(page.locator('body')).toHaveCSS('background-color', 'rgb(237, 242, 238)');
    await expect(page.locator('.ButtonStyle.danger')).toHaveCSS('background-color', 'rgb(179, 61, 66)');
    await expect(page.locator('.ButtonStyle.danger')).toHaveCSS('color', 'rgb(255, 255, 255)');
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
    await expect(page.locator('.lazy-card')).toHaveCSS('background-color', 'color(srgb 0.117647 0.172549 0.141176)');
    await page.evaluate(() => (window as any).destroyPreviewTheme());
    await expect(page.locator('html')).not.toHaveAttribute('data-obsidian');
    await expect(page.locator('[data-obsidian-style], .ob-controls, [data-ob-nav]')).toHaveCount(0);
    await expect(page.locator('.preview-nav')).toHaveCSS('position', 'static');
    await expect(page.locator('body')).toHaveCSS('padding-left', '0px');
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
