# Notur Full Example

A complete extension reference with:

- backend API and admin routes
- Blade admin view
- migration example
- frontend slot + route registration
- namespaced inter-extension event channel usage
- PHPUnit test scaffold

## Build Frontend

```bash
npm install
npm run build
```

## Run Backend Tests

```bash
composer install
./vendor/bin/phpunit
```

## Load In Panel

```bash
php artisan notur:dev /absolute/path/to/examples/full-extension
```
