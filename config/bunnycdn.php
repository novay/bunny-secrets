<?php

/**
 * Konfigurasi untuk BunnyCDN dan secret storage.
 *
 * File ini digunakan untuk mengatur detail koneksi ke BunnyCDN,
 * nama file secret, serta kunci enkripsi yang digunakan.
 *
 * @package Novay\BunnySecrets
 * @author  Novay <novay@btekno.id>
 * @license https://opensource.org/licenses/MIT MIT License
 */

use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNRegion;

return [
    'disk' => [
        'driver' => 'bunnycdn',
        'storage_zone' => env('BUNNYCDN_STORAGE_ZONE', ''),
        'api_key' => env('BUNNYCDN_API_KEY'),
        'region' => env('BUNNYCDN_REGION', BunnyCDNRegion::SINGAPORE),
        'cdn_url' => env('BUNNYCDN_CDN_URL', 'https://btekno.b-cdn.net'),
    ],

    'secret_file' => env('BUNNY_SECRET_FILE', ''),
    'secret_key_name' => env('BUNNY_SECRET_KEY', '')
];