<?php

namespace Novay\BunnySecret;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Novay\BunnySecret\Helpers\Secret secret()
 * @method static bool hasSecretManager()
 * @method static mixed getSecret(string $key)
 * @method static array<string, mixed> storeSecret(string $key, mixed $value)
 * @method static bool deleteSecret(string $key)
 * @method static string imageKit(string $url, int $resolution = 500)
 * @method static string|false uploadCDN(mixed $file, string $path = 'temp', ?string $filename = null, string $disk = 'bunnycdn')
 * @method static string showCDN(string $path, bool $zone = true)
 * @method static bool deleteCDN(string $filePath, string $disk = 'bunnycdn')
 * @method static bool existsCDN(string $filePath, string $disk = 'bunnycdn')
 *
 * @see \Novay\BunnySecret\BunnySecretManager
 */
class BunnySecret extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'bunnysecret';
    }
}
