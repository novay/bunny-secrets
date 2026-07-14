<?php

namespace Novay\BunnySecret;

use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Novay\BunnySecret\Helpers\Secret;
use Novay\BunnySecret\Support\Path;
use SplFileInfo;
use Throwable;

class BunnySecretManager
{
    public function __construct(protected Secret $secretService) {}

    public function secret(): Secret
    {
        return $this->secretService;
    }

    public function hasSecretManager(): bool
    {
        return $this->secretService->isConfigured();
    }

    public function getSecret(string $key): mixed
    {
        return $this->secretService->getSecret($key);
    }

    /**
     * @return array<string, mixed>
     */
    public function storeSecret(string $key, mixed $value): array
    {
        return $this->secretService->storeSecret($key, $value);
    }

    public function deleteSecret(string $key): bool
    {
        return $this->secretService->deleteSecret($key);
    }

    public function imageKit(string $url, int $resolution = 500): string
    {
        if (!config('bunnycdn.imagekit.enabled', true)) {
            return $url;
        }

        $endpoint = trim((string) config('bunnycdn.imagekit.endpoint', ''));

        if ($endpoint === '') {
            return $url;
        }

        $resolution = max(1, $resolution);
        $cacheTtl = max(0, (int) config('bunnycdn.imagekit.cache_ttl', 3600));
        $cacheKey = 'bunny-secret:imagekit:' . md5($url . '|' . $resolution . '|' . $endpoint);

        $callback = function () use ($url, $resolution, $endpoint): string {
            $path = $this->extractAssetPath($url);

            if ($path === '') {
                return $url;
            }

            return rtrim($endpoint, '/') . "/tr:h-{$resolution}/" . ltrim($path, '/');
        };

        if ($cacheTtl === 0) {
            return $callback();
        }

        return Cache::remember($cacheKey, $cacheTtl, $callback);
    }

    /**
     * Upload a file to Bunny.net Storage.
     *
     * The legacy return value is preserved: a stored path with a leading slash,
     * or false when the upload fails.
     */
    public function uploadCDN(mixed $file, string $path = 'temp', ?string $filename = null, string $disk = 'bunnycdn'): string|false
    {
        try {
            $source = $this->resolveUploadSource($file);

            if ($source === null) {
                Log::warning('BunnySecret upload failed: unsupported or unreadable file.', [
                    'type' => is_object($file) ? $file::class : gettype($file),
                ]);

                return false;
            }

            $targetPath = Path::join($path, $this->resolveFilename($file, $filename));
            $stream = fopen($source, 'rb');

            if ($stream === false) {
                Log::warning('BunnySecret upload failed: unable to open file stream.', [
                    'source' => $source,
                    'target' => $targetPath,
                ]);

                return false;
            }

            try {
                $stored = Storage::disk($disk)->put($targetPath, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            return $stored ? '/' . $targetPath : false;
        } catch (Throwable $e) {
            Log::error('BunnySecret upload failed.', [
                'disk' => $disk,
                'path' => $path,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function showCDN(string $path, bool $zone = true): string
    {
        $path = trim($path);

        if ($path === '') {
            return '';
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        $cleanPath = Path::clean($path);

        if (!$zone) {
            return $cleanPath;
        }

        $baseUrl = $this->cdnUrl();

        if ($baseUrl !== null) {
            return rtrim($baseUrl, '/') . '/' . $cleanPath;
        }

        try {
            return Storage::disk('bunnycdn')->url($cleanPath);
        } catch (Throwable) {
            return $cleanPath;
        }
    }

    public function deleteCDN(string $filePath, string $disk = 'bunnycdn'): bool
    {
        $cleanPath = Path::fromUrl($filePath, $this->cdnUrl());

        if ($cleanPath === '') {
            return false;
        }

        try {
            if (Storage::disk($disk)->exists($cleanPath)) {
                return Storage::disk($disk)->delete($cleanPath);
            }

            return false;
        } catch (Throwable $e) {
            Log::warning('BunnySecret delete failed.', [
                'disk' => $disk,
                'path' => $cleanPath,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function existsCDN(string $filePath, string $disk = 'bunnycdn'): bool
    {
        $cleanPath = Path::fromUrl($filePath, $this->cdnUrl());

        if ($cleanPath === '') {
            return false;
        }

        try {
            return Storage::disk($disk)->exists($cleanPath);
        } catch (Throwable) {
            return false;
        }
    }

    protected function resolveUploadSource(mixed $file): ?string
    {
        if ($file instanceof UploadedFile || $file instanceof File || $file instanceof SplFileInfo) {
            $path = $file->getRealPath() ?: $file->getPathname();

            return is_string($path) && is_file($path) && is_readable($path) ? $path : null;
        }

        if (is_string($file)) {
            return is_file($file) && is_readable($file) ? $file : null;
        }

        return null;
    }

    protected function resolveFilename(mixed $file, ?string $filename): string
    {
        $extension = $this->resolveExtension($file);
        $filename = $filename !== null ? trim($filename) : '';

        if ($filename === '') {
            $filename = $this->uniqueFilename($file);
        }

        $filename = $this->sanitizeFilename($filename);

        if ($extension !== '' && pathinfo($filename, PATHINFO_EXTENSION) === '') {
            $filename .= '.' . $extension;
        }

        return $filename;
    }

    protected function uniqueFilename(mixed $file): string
    {
        $baseName = 'file';

        if ($file instanceof UploadedFile) {
            $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: $baseName;
        } elseif ($file instanceof SplFileInfo) {
            $baseName = pathinfo($file->getFilename(), PATHINFO_FILENAME) ?: $baseName;
        } elseif (is_string($file)) {
            $baseName = pathinfo($file, PATHINFO_FILENAME) ?: $baseName;
        }

        try {
            $suffix = bin2hex(random_bytes(8));
        } catch (Throwable) {
            $suffix = str_replace('.', '', uniqid('', true));
        }

        return $baseName . '-' . $suffix;
    }

    protected function resolveExtension(mixed $file): string
    {
        if ($file instanceof UploadedFile) {
            return strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
        }

        if ($file instanceof File) {
            return strtolower($file->extension() ?: pathinfo($file->getFilename(), PATHINFO_EXTENSION));
        }

        if ($file instanceof SplFileInfo) {
            return strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
        }

        if (is_string($file)) {
            return strtolower(pathinfo($file, PATHINFO_EXTENSION));
        }

        return '';
    }

    protected function sanitizeFilename(string $filename): string
    {
        $filename = str_replace('\\', '/', $filename);
        $filename = basename($filename);
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?: '';
        $filename = trim($filename, '.-_');

        return $filename !== '' ? $filename : 'file-' . str_replace('.', '', uniqid('', true));
    }

    protected function extractAssetPath(string $url): string
    {
        $s3Bucket = trim((string) config('filesystems.disks.s3.bucket', env('AWS_BUCKET', '')));
        $s3Region = trim((string) config('filesystems.disks.s3.region', env('AWS_DEFAULT_REGION', '')));

        if ($s3Bucket !== '' && $s3Region !== '') {
            $s3BaseUrl = "https://{$s3Bucket}.s3.{$s3Region}.amazonaws.com/";

            if (str_starts_with($url, $s3BaseUrl)) {
                return Path::clean(substr($url, strlen($s3BaseUrl)));
            }
        }

        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return Path::clean(parse_url($url, PHP_URL_PATH) ?: '');
        }

        return Path::clean($url);
    }

    protected function cdnUrl(): ?string
    {
        $url = config('bunnycdn.disk.cdn_url') ?: config('bunnycdn.disk.pull_zone') ?: config('filesystems.disks.bunnycdn.pull_zone');
        $url = is_scalar($url) ? trim((string) $url) : '';

        return $url === '' ? null : $url;
    }
}
