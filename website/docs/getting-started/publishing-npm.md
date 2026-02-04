# Publishing to npm

How to publish **@notur/sdk** to npm and how the registry-validate workflow works.

## What gets published

| Package        | Published to npm? | Purpose |
|----------------|-------------------|--------|
| **@notur/sdk** | Yes               | Extension developers install it: `bun add @notur/sdk`. Provides `createExtension()`, hooks, types, webpack config. |
| **@notur/bridge** | No (optional)  | Runtime bundled into the panel (`public/notur/bridge.js`). Not consumed from npm by end users. |
| **hello-world** | No               | Example extension in this repo. It is not an npm package; it can be packaged as a `.notur` archive or listed in the registry. |

Only **@notur/sdk** is published by the `npm-publish` workflow. The repo is at **sak0a/notur**; the npm org is **@notur**.

---

## Step 1: Create the npm org and token

1. **Create the @notur org** (if not done): [npm → Organizations → Create](https://www.npmjs.com/org/create). Name: `notur`.
2. **Add your npm user** to the org with permission to publish (e.g. “Admin” or “Developer”).
3. **Create an Automation (or “Publish”) token**:
   - npm → **Access Tokens** → **Generate New Token** → **Classic** (or **Granular** with “Packages: read and write” for `@notur`).
   - Choose **Automation** (no 2FA prompt in CI) or **Publish**.
   - Copy the token (starts with `npm_`); you won’t see it again.

---

## Step 2: Add the token to GitHub

1. Open **sak0a/notur** on GitHub → **Settings** → **Secrets and variables** → **Actions**.
2. **New repository secret**:
   - Name: `NPM_TOKEN`
   - Value: paste the npm token.
3. Save.

---

## Step 3: Confirm @notur/sdk is publishable

In **sdk/package.json** you should have:

- `"name": "@notur/sdk"`
- `"publishConfig": { "access": "public" }`

This is already set in this repo. Scoped packages like `@notur/sdk` default to restricted; `access: public` makes the package installable by anyone.

---

## Step 4: Create a release (which triggers npm publish)

1. **Actions** → **Release** workflow → **Run workflow**.
2. Enter **version** (e.g. `1.0.0`). Optionally check **prerelease**.
3. Run.

The Release workflow will:

- Bump version in `config/notur.php`, root and workspace `package.json`s, installer, changelog.
- Commit, push, create tag `vX.Y.Z`, and **publish a GitHub Release**.

When that release is **published**, the **Publish SDK to npm** workflow runs automatically. It:

- Checks out the release tag.
- Installs deps, builds the SDK, runs `npm publish` in `sdk/` using `NPM_TOKEN`.

So after a successful release you get:

- GitHub Release **vX.Y.Z**
- **@notur/sdk@X.Y.Z** on npm.

---

## If the release already existed before NPM_TOKEN was set

- Open **Actions** → find the **“Publish SDK to npm”** run for that release (it will be failed or skipped).
- Click **Re-run all jobs** (after `NPM_TOKEN` is set),  
  **or**
- Create a new release (e.g. **1.0.1**) and let it trigger a fresh publish.

---

## Installing @notur/sdk after publish

Extension authors (and the hello-world example, if you switch it to the SDK) use:

```bash
bun add @notur/sdk
# or
npm install @notur/sdk
```

Docs already reference `@notur/sdk` (e.g. [Creating Extensions](/extensions/guide), [Frontend SDK](/extensions/frontend-sdk)). The **hello-world** example currently uses `window.__NOTUR__` directly; it can stay that way as a minimal example, or you can later add a `package.json` and use `@notur/sdk` there.

---

## Registry-validate workflow

- **Trigger:** Pull requests that change `registry/registry.json` or `registry/extensions/**`.
- **No npm token needed.** It only:
  - Validates `registry/registry.json` (structure, duplicate IDs, etc.).
- No extra setup; it runs automatically on such PRs.

---

## Summary

| Step | Action |
|------|--------|
| 1 | Create @notur org on npm, add yourself, create Automation (or Publish) token. |
| 2 | In sak0a/notur: Settings → Actions → add secret `NPM_TOKEN`. |
| 3 | sdk/package.json already has `"name": "@notur/sdk"` and `publishConfig.access: "public"`. |
| 4 | Create a release via **Actions → Release** (e.g. version `1.0.0`). After the release is published, **Publish SDK to npm** runs and publishes @notur/sdk. |
| 5 | If the release was created before `NPM_TOKEN` existed, re-run the npm publish workflow for that release or create a new release. |

Result: **@notur/sdk** is on npm under the **@notur** org; hello-world stays an example in the repo (no separate npm package).
