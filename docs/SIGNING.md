# Extension Signing

Notur supports Ed25519 digital signatures on `.notur` extension archives. Signing lets panel administrators verify that an archive was produced by a known author and has not been modified since it was signed.

## Overview

The signing system uses Ed25519, a modern elliptic-curve signature scheme provided by PHP's `sodium` extension. The workflow is:

1. The extension author generates an Ed25519 keypair (once).
2. When exporting, the author signs the `.notur` archive with their secret key. This produces a detached `.sig` file alongside the archive.
3. Panel administrators configure the author's public key and optionally enable `require_signatures` to reject unsigned or tampered archives at install time.

Signatures complement the SHA-256 checksums that are already embedded inside every `.notur` archive. Checksums protect against accidental corruption; signatures protect against intentional tampering.

## Prerequisites

The `sodium` PHP extension must be loaded. It ships with PHP 8.2+ by default. Verify with:

```bash
php -m | grep sodium
```

If sodium is not listed, install or enable it for your PHP installation before using any signing commands.

## Generating a Keypair

Run the built-in keygen command to create a new Ed25519 keypair:

```bash
php artisan notur:keygen
```

This outputs two hex-encoded keys:

- **Public Key** -- Share this with panel administrators who install your extensions. It is safe to publish openly.
- **Secret Key** -- Keep this private. Anyone with the secret key can produce valid signatures for your extensions.

Example output:

```
Ed25519 keypair generated successfully.

Public Key:
a1b2c3d4e5f6...  (64 hex characters)

Secret Key:
f6e5d4c3b2a1...  (128 hex characters)

Store the secret key securely. It is used to sign extension archives.
Add the public key to your panel configuration:

  NOTUR_PUBLIC_KEY=a1b2c3d4e5f6...

To sign an archive, set the secret key as an environment variable:

  NOTUR_SECRET_KEY=f6e5d4c3b2a1...
  php artisan notur:export --sign
```

Store the secret key in a password manager, secrets vault, or CI/CD secret store. Do not commit it to version control.

## Signing an Archive

To produce a signed archive, set the `NOTUR_SECRET_KEY` environment variable and pass the `--sign` flag to `notur:export`:

```bash
NOTUR_SECRET_KEY=your_secret_key_hex php artisan notur:export --sign
```

You can also set `NOTUR_SECRET_KEY` in your `.env` file (for local development only -- never commit `.env` to version control).

The command produces three files:

| File | Purpose |
|------|---------|
| `vendor-name-1.0.0.notur` | The extension archive (gzipped tar with embedded `checksums.json`) |
| `vendor-name-1.0.0.notur.sha256` | Standalone SHA-256 checksum of the archive file |
| `vendor-name-1.0.0.notur.sig` | Detached Ed25519 signature (hex-encoded) |

The `.sig` file must be distributed alongside the `.notur` archive. Without it, panels that enforce signatures will reject the archive.

## Using the Node.js CLI (Alternative)

If you don't have access to a PHP/Laravel environment, the `@notur/sdk` package provides equivalent CLI tools for keypair generation and signing.

### Generating a Keypair (Node.js)

```bash
npx notur-keygen
```

This produces the same output format as `php artisan notur:keygen` -- a public key (64 hex characters) and a secret key (128 hex characters).

### Signing an Archive (Node.js)

```bash
# Using environment variable
NOTUR_SECRET_KEY=your_secret_key npx notur-pack --sign

# Or with --secret-key flag
npx notur-pack --sign --secret-key your_secret_key
```

This produces the same `.notur`, `.sha256`, and `.sig` files as `php artisan notur:export --sign`. The signature format is fully compatible with the PHP verification system.

### Signing in CI/CD

A typical GitHub Actions step:

```yaml
- name: Export and sign extension
  env:
    NOTUR_SECRET_KEY: ${{ secrets.NOTUR_SECRET_KEY }}
  run: php artisan notur:export --sign --output dist/my-extension.notur
```

Store `NOTUR_SECRET_KEY` as a repository secret. Never log or echo it.

## How Verification Works

When `notur:install` processes a `.notur` archive and `require_signatures` is enabled, the following happens:

1. The installer looks for a `.sig` file next to the archive (e.g., `my-extension.notur.sig`).
2. If the `.sig` file is missing, installation is rejected with: `Signature file not found and signatures are required.`
3. The hex-encoded signature is read from the `.sig` file.
4. The configured `NOTUR_PUBLIC_KEY` is loaded from the panel's environment/config.
5. `sodium_crypto_sign_verify_detached()` verifies the signature against the full archive contents.
6. If verification fails, installation is rejected with: `Signature verification failed.`
7. If verification passes, the installer continues with archive extraction and checksum validation.

This means both the cryptographic signature and the internal file checksums must pass before any extension code is placed on the server.

## Enabling Signature Enforcement (Panel Admins)

Panel administrators control signature verification through two configuration values.

### 1. Set the public key

Add the extension author's public key to the panel `.env` file:

```
NOTUR_PUBLIC_KEY=a1b2c3d4e5f6...
```

This value is read by `config('notur.public_key')` and used for all signature checks.

### 2. Enable enforcement

In `config/notur.php`, set `require_signatures` to `true`:

```php
'require_signatures' => true,
```

Or override it via environment variable if your config supports env-based overrides.

When `require_signatures` is `false` (the default), signatures are not checked at all -- unsigned archives install normally. When `true`, every archive must have a valid `.sig` file signed by the key matching `NOTUR_PUBLIC_KEY`.

### Current limitation: single public key

The current implementation supports a single public key configured globally. All signed extensions must be signed by the same keypair. If you install extensions from multiple authors, you would need to either:

- Disable `require_signatures` and verify signatures manually before installation.
- Have a trusted party (e.g., a registry operator) re-sign archives with a single key after review.

Multi-key support (e.g., per-extension or keyring-based) is not yet implemented.

## Key Distribution Strategy

### For extension authors

- Publish your public key in your extension's README, repository, and registry listing.
- Use a single keypair across all your extensions for consistency.
- If your secret key is compromised, generate a new keypair with `notur:keygen`, publish the new public key, and re-sign all published archives.

### For panel administrators

- Obtain public keys directly from the extension author's official repository or website.
- Verify key fingerprints out-of-band (e.g., compare the hex string over a separate channel) when security is critical.
- Keep `require_signatures` disabled during development and enable it in production.

### For registry operators

- Consider requiring signed archives for registry inclusion.
- Publish a mapping of extension IDs to author public keys to help administrators verify they have the correct key.

## Troubleshooting

### "The sodium extension is required for signature verification/generation"

The PHP `sodium` extension is not loaded. On most PHP 8.2+ installations it is enabled by default. Check:

```bash
php -m | grep sodium
```

If missing, install it via your package manager (e.g., `apt install php-sodium`) or enable it in `php.ini`.

### "NOTUR_SECRET_KEY environment variable is not set"

You passed `--sign` to `notur:export` but the `NOTUR_SECRET_KEY` variable is not available. Set it inline:

```bash
NOTUR_SECRET_KEY=your_key_hex php artisan notur:export --sign
```

Or add it to your `.env` file.

### "Signature file not found and signatures are required"

The panel has `require_signatures` enabled but the `.sig` file is missing. Ensure the `.sig` file is in the same directory as the `.notur` archive and has the exact name `<archive>.sig`.

### "Signature verification failed"

The signature does not match the archive contents for the configured public key. Possible causes:

- The archive was modified after signing (re-download or re-export and sign again).
- The wrong public key is configured. Verify that `NOTUR_PUBLIC_KEY` in `.env` matches the author's published public key.
- The `.sig` file was corrupted or truncated. It should contain a single line of hex characters (128 hex characters for an Ed25519 signature).

### "Checksum verification failed"

This is separate from signature verification. After extraction, the archive's internal `checksums.json` is validated against the extracted files. If this fails, the archive itself is corrupt -- re-download it from the source.

### Signatures pass but checksums fail (or vice versa)

These are independent checks. The signature covers the `.notur` archive as a whole (the compressed tar). The checksums cover individual files inside the archive. Both must pass for installation to succeed when signatures are required.
