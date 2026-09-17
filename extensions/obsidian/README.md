# Obsidian

A frontend-only Notur theme for the Pterodactyl client panel. Charcoal and mint in dark mode; soft green-gray and white in light mode. Sidebar navigation, translucent surfaces, restrained transitions, keyboard focus indicators, and a mobile navigation drawer.

## Install

Requires Notur 1.5.3+ with the existing navigation slot patches. Install from the registry in the panel directory:

```sh
php artisan notur:registry:sync
php artisan notur:add notur/obsidian
```

For offline installation, download the `.notur` asset from the [GitHub release](https://github.com/sak0a/notur/releases/tag/obsidian-v0.1.0) and pass its absolute path to `notur:add`.

No panel rebuild or backend configuration is required. Dark appearance is the default; the toggle remembers the preference per browser and synchronizes open tabs. Disable with `php artisan notur:disable notur/obsidian`, then reload the panel.

## Scope

The client shell covers dashboard, server, account, and authentication pages. The Laravel administration area is deliberately excluded. Existing routes, permissions, navigation links, search and logout handlers are retained. The extension moves the existing navigation visually using stable Notur slot elements; it does not create a second list of routes or move React-owned DOM nodes. Server header extension content stays in the page, outside the sidebar.

A palette adapter reads same-origin host CSS and translates the stock Pterodactyl gray/blue/cyan colors into theme variables, including Tailwind opacity expressions. Semantic danger/success colors remain intact. Terminal and code-editor rules are excluded to preserve syntax colors. Shared Notur surface variables also follow the selected appearance. Disable/unregister removes styles, controls, observers and event handlers, and restores modified attributes.

This is an initial client-theme release. Custom palettes, inaccessible cross-origin stylesheets, third-party inline colors, canvas-rendered charts and custom extension UI may require additional theme integration. Modern browsers with `color-mix`, `:is`, ResizeObserver and CSS custom properties are required. Glass has an opaque fallback where backdrop filtering is unavailable.

## Develop and preview

From the repository root (uses the existing root toolchain and local SDK):

```sh
npm ci
npm run build:obsidian
python3 -m http.server 8765 --bind 127.0.0.1 --directory extensions/obsidian
```

Open `http://127.0.0.1:8765/preview/`. The preview uses representative panel markup and sample data; it is not connected to a game server. Its appearance toggle and drawer are the actual extension runtime. Server actions are disabled and navigation reports its intended destination.

```sh
npm run test:obsidian
```

The browser suite checks persistence, semantic colors, Tailwind palette translation, mobile overflow/focus/Escape, original click handlers, lazy styles, remounted navigation, reduced motion, unavailable storage and uninstall cleanup. The build and preview suite are verified; a running authenticated panel was unavailable, so live client routes and third-party extensions still need integration verification before production deployment.
