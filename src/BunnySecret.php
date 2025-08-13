<?php

namespace Novay\BunnySecret;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string imageKit(string $url, int $resolution = 500) Mengubah URL gambar agar sesuai dengan konfigurasi ImageKit BunnyCDN.
 * @method static string|false uploadCDN($file, string $path = 'temp', ?string $filename = null, string $disk = 'bunnycdn') Mengunggah file ke BunnyCDN Storage Zone.
 * @method static string showCDN(string $path, bool $zone = true) Mendapatkan URL publik dari file yang tersimpan di BunnyCDN.
 * @method static bool deleteCDN(string $filePath, string $disk = 'bunnycdn') Menghapus file dari BunnyCDN Storage Zone.
 *
 * @see \Novay\BunnySecret\BunnySecretManager
 *
 * @package Novay\BunnySecret
 */
class BunnySecret extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * Metode ini mengembalikan kunci (key) yang terdaftar di service container
     * untuk kelas konkret yang diwakili oleh Facade ini. Laravel menggunakan kunci ini
     * untuk menemukan dan me-resolve instance dari kelas `BunnySecretManager`.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'bunnysecret';
    }
}