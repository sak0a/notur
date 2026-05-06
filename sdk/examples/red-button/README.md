# Red Button Example

A minimal Notur frontend extension that renders a red button in the server header.

## Local Build

```bash
npm install
npm run build
```

## Package

```bash
npx notur-pack
```

## Push To A Remote Panel

Create `.env` from `.env.example`, then run:

```bash
npm run push
```

The remote panel must have Notur remote push enabled:

```bash
php artisan notur:remote-key
```
