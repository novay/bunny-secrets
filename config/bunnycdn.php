<?php

use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNRegion;

return [
    /*
    |--------------------------------------------------------------------------
    | Bunny.net Storage Disk
    |--------------------------------------------------------------------------
    |
    | The package automatically registers a Laravel filesystem disk named
    | "bunnycdn". You may still override these values from config/filesystems.php
    | if your application needs a custom setup.
    |
    */

    'disk' => [
        'driver' => 'bunnycdn',
        'storage_zone' => env('BUNNYCDN_STORAGE_ZONE'),
        'api_key' => env('BUNNYCDN_API_KEY'),
        'region' => env('BUNNYCDN_REGION', BunnyCDNRegion::SINGAPORE),

        // The flysystem-bunnycdn package uses "pull_zone". "cdn_url" is kept
        // for backward compatibility with older versions of this package.
        'pull_zone' => env('BUNNYCDN_PULL_ZONE', env('BUNNYCDN_CDN_URL')),
        'cdn_url' => env('BUNNYCDN_CDN_URL', env('BUNNYCDN_PULL_ZONE')),
        'token_auth_key' => env('BUNNYCDN_TOKEN_AUTH_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bunny API Key Resolution
    |--------------------------------------------------------------------------
    |
    | Resolution order for the Bunny API key:
    | 1. BUNNYCDN_API_KEY / bunnycdn.disk.api_key
    | 2. storage/app/{secret_file}
    | 3. Borneo Secrets Manager, only when secret_api.enabled is true
    |
    */

    'secret_file' => env('BUNNY_SECRET_FILE'),
    'secret_key_name' => env('BUNNY_SECRET_KEY'),
    'cache_secret_file' => env('BUNNY_SECRET_CACHE_FILE', true),

    /*
    |--------------------------------------------------------------------------
    | Optional Borneo Secrets Manager API
    |--------------------------------------------------------------------------
    |
    | This integration is intentionally optional. Keep BORNEO_SECRET_ENABLED=false
    | when you want to use BUNNYCDN_API_KEY directly. Turn it on only when the
    | package should resolve BUNNYCDN_API_KEY from your Borneo Secrets Manager.
    |
    | Legacy SECRET_* env names are still supported for backward compatibility.
    |
    */

    'secret_api' => [
        'enabled' => env('BORNEO_SECRET_ENABLED', env('SECRET_ENABLED', false)),
        'base_uri' => env('BORNEO_SECRET_URI', env('SECRET_URI')),
        'api_key' => env('BORNEO_SECRET_API_KEY', env('SECRET_KEY')),
        'timeout' => (int) env('BORNEO_SECRET_TIMEOUT', env('SECRET_TIMEOUT', 10)),
        'retries' => (int) env('BORNEO_SECRET_RETRIES', env('SECRET_RETRIES', 1)),
        'retry_sleep_ms' => (int) env('BORNEO_SECRET_RETRY_SLEEP_MS', env('SECRET_RETRY_SLEEP_MS', 200)),
    ],

    /*
    |--------------------------------------------------------------------------
    | URL Helpers
    |--------------------------------------------------------------------------
    */

    'imagekit' => [
        'enabled' => env('BUNNY_IMAGEKIT_ENABLED', true),
        'endpoint' => env('BUNNY_IMAGEKIT_ENDPOINT', 'https://ik.imagekit.io/enterwind'),
        'cache_ttl' => (int) env('BUNNY_IMAGEKIT_CACHE_TTL', 3600),
    ],
];
