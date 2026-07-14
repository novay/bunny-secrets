<?php

namespace Novay\BunnySecret;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use Novay\BunnySecret\Exceptions\BunnySecretException;
use Novay\BunnySecret\Helpers\Secret;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNAdapter;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNClient;
use Throwable;

class BunnySecretServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/bunnycdn.php', 'bunnycdn');

        $this->registerSecretClient();
        $this->registerManager();
    }

    public function boot(): void
    {
        $this->publishConfig();
        $this->ensureBunnyDiskConfigExists();
        $this->registerFilesystemDriver();
    }

    protected function registerSecretClient(): void
    {
        $this->app->singleton(Secret::class, static function (): Secret {
            return new Secret(
                baseUri: (string) config('bunnycdn.secret_api.base_uri', ''),
                apiKey: (string) config('bunnycdn.secret_api.api_key', ''),
                enabled: static::truthy(config('bunnycdn.secret_api.enabled', false)),
                timeout: (int) config('bunnycdn.secret_api.timeout', 10),
                retries: (int) config('bunnycdn.secret_api.retries', 1),
                retrySleepMs: (int) config('bunnycdn.secret_api.retry_sleep_ms', 200),
            );
        });
    }

    protected function registerManager(): void
    {
        $this->app->singleton(BunnySecretManager::class, static function ($app): BunnySecretManager {
            return new BunnySecretManager($app->make(Secret::class));
        });

        $this->app->alias(BunnySecretManager::class, 'bunnysecret');
    }

    protected function publishConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../config/bunnycdn.php' => config_path('bunnycdn.php'),
        ], 'bunny-secrets-config');
    }

    protected function ensureBunnyDiskConfigExists(): void
    {
        $packageDisk = config('bunnycdn.disk', []);
        $appDisk = config('filesystems.disks.bunnycdn', []);

        config([
            'filesystems.disks.bunnycdn' => array_replace_recursive($packageDisk, $appDisk),
        ]);
    }

    protected function registerFilesystemDriver(): void
    {
        Storage::extend('bunnycdn', function ($app, array $config): FilesystemAdapter {
            $config = $this->resolveDiskConfig($config);

            $adapter = new BunnyCDNAdapter(
                new BunnyCDNClient(
                    $config['storage_zone'],
                    $config['api_key'],
                    $config['region'] ?? null,
                ),
                $config['pull_zone'] ?? $config['cdn_url'] ?? null,
            );

            if (!empty($config['token_auth_key']) && method_exists($adapter, 'setTokenAuthKey')) {
                $adapter->setTokenAuthKey($config['token_auth_key']);
            }

            return new FilesystemAdapter(
                new Filesystem($adapter, $config),
                $adapter,
                $config,
            );
        });
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    protected function resolveDiskConfig(array $config): array
    {
        $packageDisk = config('bunnycdn.disk', []);
        $config = array_replace_recursive($packageDisk, $config);

        $config['driver'] = 'bunnycdn';
        $config['pull_zone'] = $this->nullableString($config['pull_zone'] ?? $config['cdn_url'] ?? null);
        $config['cdn_url'] = $this->nullableString($config['cdn_url'] ?? $config['pull_zone'] ?? null);
        $config['api_key'] = $this->resolveBunnyApiKey($config);

        foreach (['storage_zone', 'api_key'] as $requiredKey) {
            if (empty($config[$requiredKey])) {
                throw BunnySecretException::missingConfig("disk.{$requiredKey}");
            }
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function resolveBunnyApiKey(array $config): ?string
    {
        $configuredApiKey = $this->nullableString($config['api_key'] ?? null);

        if ($configuredApiKey !== null) {
            return $configuredApiKey;
        }

        $secretFileApiKey = $this->readApiKeyFromSecretFile();

        if ($secretFileApiKey !== null) {
            return $secretFileApiKey;
        }

        $secretName = $this->nullableString(config('bunnycdn.secret_key_name'));

        if ($secretName === null || !$this->shouldUseSecretApi()) {
            return null;
        }

        try {
            $apiKey = $this->app->make(Secret::class)->getSecret($secretName);
            $apiKey = $this->nullableString(is_scalar($apiKey) ? (string) $apiKey : null);

            if ($apiKey !== null && $this->shouldCacheSecretFile()) {
                $this->writeApiKeyToSecretFile($apiKey);
            }

            return $apiKey;
        } catch (Throwable $e) {
            Log::warning('Unable to resolve BunnyCDN API key from secret service.', [
                'secret_key_name' => $secretName,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function readApiKeyFromSecretFile(): ?string
    {
        $path = $this->secretFilePath();

        if ($path === null || !is_file($path) || !is_readable($path)) {
            return null;
        }

        $apiKey = file_get_contents($path);

        return $this->nullableString($apiKey === false ? null : $apiKey);
    }

    protected function writeApiKeyToSecretFile(string $apiKey): void
    {
        $path = $this->secretFilePath();

        if ($path === null) {
            return;
        }

        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, $apiKey);
    }

    protected function secretFilePath(): ?string
    {
        $secretFile = $this->nullableString(config('bunnycdn.secret_file'));

        if ($secretFile === null) {
            return null;
        }

        if (str_starts_with($secretFile, DIRECTORY_SEPARATOR)) {
            return $secretFile;
        }

        return storage_path('app/' . ltrim($secretFile, '/\\'));
    }

    protected function shouldCacheSecretFile(): bool
    {
        return static::truthy(config('bunnycdn.cache_secret_file', true));
    }

    protected function shouldUseSecretApi(): bool
    {
        return static::truthy(config('bunnycdn.secret_api.enabled', false));
    }

    protected function nullableString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected static function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return false;
    }
}

