<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Notur Version
    |--------------------------------------------------------------------------
    */
    'version' => '1.1.1',

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
    | Registry URL
    |--------------------------------------------------------------------------
    |
    | The base URL for the extension registry.
    |
    */
    'registry_url' => 'https://raw.githubusercontent.com/notur/registry/main',

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

];
