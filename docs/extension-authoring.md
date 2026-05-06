# Extension Authoring

## Frontend-Only Extension

Use this for buttons, widgets, tabs, route pages, toolbar actions, and UI-only behavior.

```bash
npx notur-create acme/red-button --preset frontend --slot server.header
cd red-button
npm install
npm run build
npx notur-validate
npx notur-pack
```

Minimal structure:

```text
extension.yaml
package.json
webpack.config.js
tsconfig.json
resources/frontend/src/index.tsx
resources/frontend/dist/extension.js
```

No PHP entrypoint is required.

## Backend Extension

Use this when the extension needs client API routes, database access, migrations, commands, or admin pages.

```bash
npx notur-create acme/tools --preset backend
```

Backend route files are declared in `extension.yaml`:

```yaml
backend:
  routes:
    api-client: "src/routes/api-client.php"
```

Routes are mounted under:

```text
/api/client/notur/{vendor/name}/...
```

## Full Extension

Use this for a combined PHP backend and React frontend.

```bash
npx notur-create acme/tools --preset full
```

## Frontend Registration

```tsx
import * as React from 'react';
import { createExtension } from '@notur/sdk';

const Widget = () => <div>Hello</div>;

createExtension({
  id: 'acme/tools',
  slots: [{ slot: 'dashboard.widgets', component: Widget }],
});
```

The `id` must match `extension.yaml`.

## Remote Development

Remote panel:

```bash
php artisan notur:remote-key
php artisan config:clear
```

Local `.env`:

```env
NOTUR_HOST=https://panel.example.com
NOTUR_PUSH_KEY=notur_xxx
```

Push:

```bash
npm run push
```

## Local Checks

```bash
npx notur-sync
npx notur-validate
npx notur-doctor
```

Use `notur-sync` after changing `extension.yaml`. Use `notur-validate` before packaging. Use `notur-doctor` when the build, bundle path, or remote config is unclear.
