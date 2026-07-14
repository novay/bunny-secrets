<?php

namespace Novay\BunnySecret\Support;

final class Path
{
    public static function clean(?string $path): string
    {
        $path = trim((string) $path);
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path) ?: '';

        return trim($path, '/');
    }

    public static function join(string ...$segments): string
    {
        $cleaned = array_filter(array_map([self::class, 'clean'], $segments), static function (string $segment): bool {
            return $segment !== '';
        });

        return implode('/', $cleaned);
    }

    public static function fromUrl(string $pathOrUrl, ?string $baseUrl = null): string
    {
        $pathOrUrl = trim($pathOrUrl);

        if ($pathOrUrl === '') {
            return '';
        }

        if (filter_var($pathOrUrl, FILTER_VALIDATE_URL)) {
            $urlPath = parse_url($pathOrUrl, PHP_URL_PATH) ?: '';

            return self::clean($urlPath);
        }

        if ($baseUrl && str_starts_with($pathOrUrl, rtrim($baseUrl, '/') . '/')) {
            return self::clean(substr($pathOrUrl, strlen(rtrim($baseUrl, '/'))));
        }

        return self::clean($pathOrUrl);
    }
}
