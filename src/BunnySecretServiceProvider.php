<?php

namespace Novay\BunnySecret;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Storage;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNAdapter;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNClient;
use League\Flysystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Log;
use Novay\BunnySecret\Helpers\Secret;
use Throwable;

/**
 * BunnySecretServiceProvider
 *
 * Service Provider ini bertanggung jawab untuk mendaftarkan dan mem-bootstrap
 * semua fungsionalitas yang terkait dengan package BunnySecret.
 * Ini termasuk konfigurasi driver BunnyCDN filesystem, pendaftaran service Secret,
 * dan publikasi aset konfigurasi.
 *
 * @package Novay\BunnySecret
 * @author Novay <novay@btekno.id>
 * @license https://opensource.org/licenses/MIT MIT License
 */
class BunnySecretServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * Metode ini digunakan untuk mendaftarkan binding service ke dalam IoC container.
     * Ini memastikan bahwa layanan SecretManager dan konfigurasi default BunnyCDN
     * tersedia untuk aplikasi.
     *
     * @return void
     */
    public function register(): void
    {
        // Daftarkan service Novay\BunnySecret\Helpers\Secret sebagai singleton
        $this->registerSecretManager();

        // Gabungkan konfigurasi package dengan konfigurasi aplikasi
        $this->mergeConfigFrom(
            __DIR__ . '/../config/bunnycdn.php', 'bunnycdn'
        );

        // Daftarkan BunnySecretManager sebagai singleton di service container
        $this->app->singleton('bunnysecret', function ($app) {
            return new BunnySecretManager($app->make(Secret::class));
        });
    }

    /**
     * Bootstrap any application services.
     *
     * Metode ini dipanggil setelah semua service provider lain telah didaftarkan.
     * Ini adalah tempat yang tepat untuk mem-bootstrap apa pun yang dibutuhkan oleh package,
     * seperti publikasi konfigurasi dan konfigurasi driver BunnyCDN filesystem.
     *
     * @return void
     */
    public function boot(): void
    {
        // Publikasikan konfigurasi package
        $this->publishConfig();

        // Konfigurasi driver BunnyCDN filesystem
        $this->configureBunnyCDNFilesystem();
    }

    /**
     * Mendaftarkan layanan Novay\BunnySecret\Helpers\Secret sebagai singleton.
     *
     * Mengikat implementasi kelas Secret ke dalam service container,
     * menginjeksikan base URI dan API key dari variabel lingkungan.
     *
     * @return void
     */
    protected function registerSecretManager(): void
    {
        $this->app->singleton(Secret::class, function ($app) {
            $baseUri = env('SECRET_URI', 'https://btekno.id');
            $apiKey = env('SECRET_KEY', 'your-api-key-here');

            return new Secret($baseUri, $apiKey);
        });
    }

    /**
     * Mempublikasikan file konfigurasi package.
     *
     * Memungkinkan pengguna aplikasi untuk menyalin file konfigurasi `bunnycdn.php`
     * dari package ke direktori `config` aplikasi, sehingga dapat dimodifikasi.
     *
     * @return void
     */
    protected function publishConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../config/bunnycdn.php' => config_path('bunnycdn.php'),
        ], 'bunny-secrets-config');
    }

    /**
     * Mengonfigurasi driver BunnyCDN Filesystem secara dinamis.
     *
     * Mengambil konfigurasi disk dari `bunnycdn.php`, mendapatkan API key secara dinamis
     * dari file sementara atau layanan Secret, dan kemudian memperluas `Storage` Facade
     * dengan driver `bunnycdn` kustom.
     *
     * @return void
     */
    protected function configureBunnyCDNFilesystem(): void
    {
        $bunnyConfig = config('bunnycdn.disk');

        // Pastikan konfigurasi driver adalah 'bunnycdn'
        if (!isset($bunnyConfig['driver']) || $bunnyConfig['driver'] !== 'bunnycdn') {
            return;
        }

        // Dapatkan API key BunnyCDN
        $bunnyApiKey = $this->getBunnyApiKey();

        // Jika API key tidak ditemukan, log peringatan dan hentikan konfigurasi
        if (!$bunnyApiKey) {
            Log::warning('BunnyCDN API Key not found. BunnyCDN filesystem driver might not function correctly.');
            return;
        }

        // Set API key ke konfigurasi disk di runtime
        $bunnyConfig['api_key'] = $bunnyApiKey;

        // Pastikan cdn_url ada di $bunnyConfig jika ingin digunakan di adapter
        // Ini penting karena nilai default di config/bunnycdn.php mungkin tidak langsung terakses di $config adapter
        if (!isset($bunnyConfig['cdn_url'])) {
             $bunnyConfig['cdn_url'] = env('BUNNYCDN_CDN_URL', 'https://btekno.b-cdn.net');
        }

        // Perbarui konfigurasi filesystems di runtime
        config(['filesystems.disks.bunnycdn' => $bunnyConfig]);

        // Perluas Storage Facade dengan driver 'bunnycdn' kustom
        Storage::extend('bunnycdn', function ($app, $config) use ($bunnyConfig) {
            $adapter = new BunnyCDNAdapter(
                new BunnyCDNClient(
                    $bunnyConfig['storage_zone'],
                    $bunnyConfig['api_key'],
                    $bunnyConfig['region'] ?? null
                ),
                $bunnyConfig['cdn_url'] ?? null
            );

            return new FilesystemAdapter(
                new Filesystem($adapter, $bunnyConfig),
                $adapter,
                $bunnyConfig
            );
        });
    }

    /**
     * Mengambil BunnyCDN API Key dari file sementara atau dari Novay\BunnySecret\Helpers\Secret service.
     *
     * Key akan di-cache di file sementara untuk mengurangi panggilan ke layanan Secret.
     *
     * @return string|null Mengembalikan API key jika berhasil ditemukan, null jika tidak.
     */
    protected function getBunnyApiKey(): ?string
    {
        $secretFile = config('bunnycdn.secret_file');
        $secretKeyName = config('bunnycdn.secret_key_name');

        // Cek apakah API key sudah ada di file sementara
        if (Storage::exists($secretFile)) {
            return Storage::get($secretFile);
        }

        // Jika tidak ada, ambil dari service Novay\BunnySecret\Helpers\Secret
        try {
            // Pastikan service Novay\BunnySecret\Helpers\Secret terdaftar dan tersedia
            if (app()->bound(Secret::class)) {
                $bunnyApiKey = app(Secret::class)->getSecret($secretKeyName);

                // Simpan ke file sementara untuk penggunaan selanjutnya
                Storage::put($secretFile, $bunnyApiKey);

                return $bunnyApiKey;
            }
            Log::error("Novay\\BunnySecret\\Helpers\\Secret service not found. Cannot retrieve BunnyCDN API Key for '{$secretKeyName}'.");
            return null;

        } catch (Throwable $e) {
            Log::error('Error retrieving BunnyCDN API Key from Novay\\BunnySecret\\Helpers\\Secret: ' . $e->getMessage());
            return null;
        }
    }
}