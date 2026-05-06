# Remote development push

Notur supports pushing locally-built extensions to a running panel for live testing without going through the marketplace flow. This is for development; production extensions still ship through the registry.

## For panel admins

1. Go to **Admin → Notur → Developer Push** (`/admin/notur/dev-push`).
2. Click **Create key**, give it a recognisable name (e.g. the developer or device using it), and save.
3. Copy the full key from the one-time alert. The panel hashes it and never displays the full value again.
4. Hand the key + your panel URL to the developer.
5. Revoke keys when developers leave or rotate devices. Use **Regenerate** to issue a new key with the same name and revoke the old one in one step.

The page also shows extensions that arrived via remote push, including which key was used, the package checksum, and any errors from the most recent push.

### Backwards compatibility

Keys configured via the `NOTUR_REMOTE_PUSH_KEYS` environment variable still work, but they are not tracked, not revocable through the UI, and pushes that use them will not record a `Pushed via` value. We recommend migrating to DB-backed keys.

## For extension developers

In your extension project, set:

```
NOTUR_REMOTE_HOST=https://panel.example.com
NOTUR_REMOTE_KEY=notur_xxx
```

Then iterate locally with:

```
npx notur-create        # scaffold (one-time)
npm run validate        # lint manifest + structure
npm run pack            # build the .notur archive
npm run push            # upload to NOTUR_REMOTE_HOST using NOTUR_REMOTE_KEY
npx notur-doctor        # diagnose env / panel
```

`npm run push` posts the `.notur` archive to `/api/notur/dev/push` on the configured host. The panel installs (or updates) the extension and returns the install output. Errors are visible in the admin **Developer Push** page under the affected extension's row.

### Security notes

- Treat keys like passwords — don't commit them to the repo.
- Use a separate key per developer / device. Easier to rotate without disrupting others.
- Revoke keys as soon as they're no longer needed.
- Remote push is gated by `NOTUR_REMOTE_PUSH_ENABLED=true` on the panel side; if that env is false, the endpoint returns 404.
