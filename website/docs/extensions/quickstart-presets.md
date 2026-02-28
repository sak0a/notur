# Extension Preset Quickstart

`notur:new` supports four presets designed for different extension scope levels.

## Presets

| Preset | Includes |
|---|---|
| `minimal` | PHP extension class + manifest only |
| `backend` | `minimal` + API route scaffold |
| `standard` | `backend` + frontend scaffold |
| `full` | `standard` + admin route/view, migrations, PHPUnit scaffold |

## Create Commands

```bash
# Minimal
php artisan notur:new acme/minimal-ext --preset=minimal

# Backend-only
php artisan notur:new acme/backend-ext --preset=backend

# Standard (frontend + backend)
php artisan notur:new acme/standard-ext --preset=standard

# Full-stack
php artisan notur:new acme/full-ext --preset=full
```

## Frontend Build Commands By Package Manager

```bash
# npm
npm install
npm run build

# yarn
yarn install
yarn run build

# pnpm
pnpm install
pnpm run build

# bun
bun install
bun run build
```

## Local Development Loop

```bash
php artisan notur:dev /absolute/path/to/your-extension --link
```

Use `--copy` if symlinks are not available in your environment.
