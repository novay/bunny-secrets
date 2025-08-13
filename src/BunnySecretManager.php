<?php

namespace Novay\BunnySecret;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Novay\BunnySecret\Helpers\Secret;

/**
 * BunnySecretManager
 *
 * Kelas ini bertanggung jawab untuk mengelola operasi file terkait BunnyCDN,
 * termasuk upload, pengambilan URL, dan penghapusan file. Ini juga menyediakan
 * integrasi dengan ImageKit.io untuk transformasi gambar dan layanan Secret
 * untuk mengambil kunci rahasia.
 *
 * @package Novay\BunnySecret
 * @author Novay <novay@btekno.id>
 * @license https://opensource.org/licenses/MIT MIT License
 */
class BunnySecretManager
{
    /**
     * Instance dari layanan Novay\BunnySecret\Helpers\Secret untuk mengambil rahasia.
     *
     * @var Secret
     */
    protected Secret $secretService;

    /**
     * Konstruktor kelas BunnySecretManager.
     *
     * Menginisialisasi kelas dengan instance dari layanan Secret.
     *
     * @param Secret $secretService Layanan untuk mengambil rahasia.
     */
    public function __construct(Secret $secretService)
    {
        $this->secretService = $secretService;
    }

    /**
     * Menghasilkan URL ImageKit dari URL S3 yang diberikan.
     *
     * Metode ini membersihkan URL S3 dan menyiapkannya untuk transformasi gambar
     * melalui ImageKit. URL yang dihasilkan di-cache untuk performa.
     *
     * @param string $url URL asli dari file gambar di S3.
     * @param int $resolution Resolusi tinggi gambar yang diinginkan (default 500px).
     * @return string URL gambar yang telah diubah dengan ImageKit.
     */
    public function imageKit(string $url, int $resolution = 500): string
    {
        $cacheKey = 'imagekit_' . md5($url . $resolution);

        return Cache::remember($cacheKey, 60, function () use ($url, $resolution) {
            // Asumsi URL S3 memiliki format: https://[BUCKET_NAME].s3.[REGION].amazonaws.com/[PATH]
            // Kita perlu membersihkan bagian base S3 URL untuk mendapatkan PATH yang murni
            $s3Bucket = env('AWS_BUCKET');
            $s3Region = env('AWS_DEFAULT_REGION');
            $s3BaseUrl = "https://{$s3Bucket}.s3.{$s3Region}.amazonaws.com/";

            $cleanedUrl = str_replace($s3BaseUrl, '', $url);

            return "https://ik.imagekit.io/enterwind/tr:h-{$resolution}/{$cleanedUrl}";
        });
    }

    /**
     * Mengunggah file ke BunnyCDN.
     *
     * File akan disimpan di zona penyimpanan BunnyCDN. Nama file dapat disesuaikan,
     * dan jika tidak diberikan, nama unik akan dihasilkan.
     *
     * @param mixed $file Instance file yang diunggah.
     * @param string $path Direktori tujuan di dalam zona penyimpanan (default 'temp').
     * @param string|null $filename Nama file kustom, atau null untuk menghasilkan nama unik.
     * @param string $disk Disk filesystem yang akan digunakan (default 'bunnycdn').
     * @return string|false Jalur file yang disimpan (relatif terhadap zona penyimpanan) atau false jika gagal.
     */
    public function uploadCDN($file, string $path = 'temp', ?string $filename = null, string $disk = 'bunnycdn')
    {
        if (!($file instanceof \Illuminate\Http\UploadedFile)) {
             Log::error('Invalid file instance provided to uploadCDN.', ['file_type' => gettype($file)]);
             return false;
        }

        $finalFilename = $filename ? "{$filename}.{$file->getClientOriginalExtension()}" : uniqid() . '_' . trim($file->getClientOriginalName());

        $fullPath = rtrim($path, '/') . '/' . $finalFilename;

        // Note: The third parameter of `put` can be an array of options (e.g., ACL).
        // The current implementation passes an empty array, which is fine.
        $stored = Storage::disk($disk)->put($fullPath, $file->get(), []);

        return $stored ? '/' . $fullPath : false;
    }

    /**
     * Mendapatkan URL publik lengkap untuk file yang disimpan di BunnyCDN.
     *
     * URL dibangun berdasarkan path relatif file dan URL zona CDN yang terkonfigurasi.
     *
     * @param string $path Jalur relatif file di dalam zona penyimpanan (e.g., '/gambar/foto-saya.jpg').
     * @param bool $zone Menentukan apakah akan menyertakan URL zona CDN dasar (true secara default).
     * @return string URL publik lengkap dari file.
     */
    public function showCDN(string $path, bool $zone = true): string
    {
        $return = '';
        $zone_url = config('bunnycdn.disk.cdn_url');

        if ($zone && $zone_url) {
            $return .= rtrim($zone_url, '/') . '/';
        }

        if (!empty($return) && str_starts_with($path, '/')) {
            $path = ltrim($path, '/');
        }

        return "{$return}{$path}";
    }

    /**
     * Menghapus file dari BunnyCDN.
     *
     * Memeriksa keberadaan file di disk yang ditentukan sebelum mencoba menghapusnya.
     *
     * @param string $filePath Jalur relatif file yang akan dihapus (e.g., '/gambar/foto-saya.jpg').
     * @param string $disk Disk filesystem yang akan digunakan (default 'bunnycdn').
     * @return bool True jika penghapusan berhasil, false jika tidak.
     */
    public function deleteCDN(string $filePath, string $disk = 'bunnycdn'): bool
    {
        if (empty($filePath)) {
            return false;
        }

        $cleanedPath = ltrim($filePath, '/');

        if (Storage::disk($disk)->exists($cleanedPath)) {
            return Storage::disk($disk)->delete($cleanedPath);
        }

        return false;
    }
}