#!/usr/bin/env node

/**
 * notur-keygen - Generate Ed25519 keypair for signing Notur extensions
 *
 * Usage:
 *   npx notur-keygen
 *   bunx notur-keygen
 *
 * Generates a new Ed25519 keypair and outputs both keys in hex format.
 * The output format matches the PHP `php artisan notur:keygen` command.
 */

const sodium = require('libsodium-wrappers');

async function main() {
    await sodium.ready;

    const keypair = sodium.crypto_sign_keypair();

    const publicKeyHex = sodium.to_hex(keypair.publicKey);
    const secretKeyHex = sodium.to_hex(keypair.privateKey);

    console.log('Ed25519 keypair generated successfully.');
    console.log('');
    console.log('Public Key:');
    console.log(publicKeyHex);
    console.log('');
    console.log('Secret Key:');
    console.log(secretKeyHex);
    console.log('');
    console.log('Store the secret key securely. It is used to sign extension archives.');
    console.log('Add the public key to your panel configuration:');
    console.log('');
    console.log(`  NOTUR_PUBLIC_KEY=${publicKeyHex}`);
    console.log('');
    console.log('To sign an archive, set the secret key as an environment variable:');
    console.log('');
    console.log(`  NOTUR_SECRET_KEY=${secretKeyHex}`);
    console.log('  npx notur-pack --sign');
}

main().catch(err => {
    console.error('Error:', err.message);
    process.exit(1);
});
