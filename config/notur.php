<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Notur Version
    |--------------------------------------------------------------------------
    */
    'version' => '1.4.6',

    /*
    |--------------------------------------------------------------------------
    | Extensions Directory
    |--------------------------------------------------------------------------
    |
    | The directory where extensions are stored, relative to the panel root.
    |
    */
    'extensions_path' => 'notur/extensions',

    /*
    |--------------------------------------------------------------------------
    | Require Signatures
    |--------------------------------------------------------------------------
    |
    | When enabled, only extensions with valid Ed25519 signatures will be
    | installed. Set to false for development or trusted environments.
    |
    */
    'require_signatures' => false,

    /*
    |--------------------------------------------------------------------------
    | GitHub Repository
    |--------------------------------------------------------------------------
    |
    | The GitHub owner/repo for the Notur framework source code.
    | Used by notur:dev:pull to download unreleased commits.
    |
    */
    'repository' => 'sak0a/notur',

    /*
    |--------------------------------------------------------------------------
    | Registry URL
    |--------------------------------------------------------------------------
    |
    | The base URL for the extension registry.
    |
    */
    'registry_url' => 'https://raw.githubusercontent.com/sak0a/notur/master/registry',

    /*
    |--------------------------------------------------------------------------
    | Registry Cache Path
    |--------------------------------------------------------------------------
    */
    'registry_cache_path' => storage_path('notur/registry-cache.json'),

    /*
    |--------------------------------------------------------------------------
    | Registry Cache TTL
    |--------------------------------------------------------------------------
    |
    | Time-to-live for the registry cache in seconds.
    | Set to 0 to disable cache expiry checks.
    |
    */
    'registry_cache_ttl' => 3600,

    /*
    |--------------------------------------------------------------------------
    | Public Key
    |--------------------------------------------------------------------------
    |
    | The Ed25519 public key used for verifying extension signatures.
    |
    */
    'public_key' => env('NOTUR_PUBLIC_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Remote Development Push
    |--------------------------------------------------------------------------
    |
    | Enables authenticated package uploads from the SDK CLI. Keep disabled in
    | production unless you explicitly trust the holder of each configured key.
    |
    */
    'remote_push' => [
        'enabled' => env('NOTUR_REMOTE_PUSH_ENABLED', false),
        'keys' => array_values(array_filter(array_map(
            static fn ($key) => trim((string) $key),
            explode(',', (string) env('NOTUR_REMOTE_PUSH_KEYS', '')),
        ))),
        'max_upload_mb' => (int) env('NOTUR_REMOTE_PUSH_MAX_UPLOAD_MB', 50),
    ],

];
