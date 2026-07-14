# Bunny Secrets

A small Laravel package for Bunny.net Storage with:

- automatic `bunnycdn` filesystem driver registration;
- a `BunnySecret` facade for upload, URL, exists, and delete helpers;
- optional API key resolution from **Borneo Secrets Manager**;
- safe fallback order for API keys to avoid circular filesystem boot errors.

## Requirements

- PHP `^8.1`
- Laravel `^10.0`, `^11.0`, `^12.0`, or `^13.0`
- `platformcommunity/flysystem-bunnycdn ^3.0`

## Installation

```bash
composer require novay/bunny-secrets
php artisan vendor:publish --tag=bunny-secrets-config
php artisan optimize:clear
```

Laravel package discovery will register the service provider automatically.

## Environment

### Recommended direct Bunny.net API key setup

Use this when you do not need Borneo Secrets Manager.

```env
FILESYSTEM_DISK=local

BUNNYCDN_STORAGE_ZONE=your-storage-zone
BUNNYCDN_API_KEY=your-storage-zone-password-or-api-key
BUNNYCDN_REGION=sg
BUNNYCDN_PULL_ZONE=https://pull-zone.b-cdn.net
BUNNYCDN_CDN_URL=https://pull-zone.b-cdn.net

BORNEO_SECRET_ENABLED=false
```

### Optional Borneo Secrets Manager setup

Turn this on only when the package should resolve the Bunny.net API key from your Borneo Secrets Manager API.

```env
FILESYSTEM_DISK=local

BUNNYCDN_STORAGE_ZONE=your-storage-zone
BUNNYCDN_REGION=sg
BUNNYCDN_PULL_ZONE=https://pull-zone.b-cdn.net
BUNNYCDN_CDN_URL=https://pull-zone.b-cdn.net

BORNEO_SECRET_ENABLED=true
BORNEO_SECRET_URI=https://key.btekno.id
BORNEO_SECRET_API_KEY=your-borneo-secret-manager-api-key

BUNNY_SECRET_KEY=bunny-secrets-pass
BUNNY_SECRET_FILE=bunny_api_key.txt
BUNNY_SECRET_CACHE_FILE=true
```

Legacy env names are still supported:

```env
SECRET_ENABLED=true
SECRET_URI=https://key.btekno.id
SECRET_KEY=your-borneo-secret-manager-api-key
```

API key resolution order:

1. `BUNNYCDN_API_KEY`
2. `storage/app/{BUNNY_SECRET_FILE}`
3. Borneo Secrets Manager, only when `BORNEO_SECRET_ENABLED=true`

> Recommendation: keep `FILESYSTEM_DISK=local` for Laravel and Livewire temporary uploads. Use the `bunnycdn` disk only when storing final files.

## Usage

### Upload with the facade

```php
use Novay\BunnySecret\BunnySecret;

$filePath = BunnySecret::uploadCDN(
    file: $request->file('photo'),
    path: 'btekno/storage',
    filename: str()->slug('bride-' . $name) . '-' . uniqid(),
);

if ($filePath === false) {
    throw new RuntimeException('Failed to upload photo to Bunny.net Storage.');
}

$url = BunnySecret::showCDN($filePath);
```

### Delete a file

```php
BunnySecret::deleteCDN('/btekno/storage/photo.jpg');
```

### Check file existence

```php
if (BunnySecret::existsCDN('/btekno/storage/photo.jpg')) {
    // file exists
}
```

### Use Laravel Storage

```php
use Illuminate\Support\Facades\Storage;

Storage::disk('bunnycdn')->put('demo/index.html', '<h1>Hello Bunny</h1>');
$url = Storage::disk('bunnycdn')->url('demo/index.html');
Storage::disk('bunnycdn')->delete('demo/index.html');
```

## Optional Borneo Secrets Manager API

The Borneo Secrets Manager feature remains available, but it is optional and disabled by default. When disabled, the package will not call the secret API during filesystem boot.

```php
use Novay\BunnySecret\BunnySecret;

if (BunnySecret::hasSecretManager()) {
    $value = BunnySecret::getSecret('bunny-secrets-pass');
}

BunnySecret::storeSecret('another-key', 'secret-value');
BunnySecret::deleteSecret('another-key');
```

`storeSecret()` and `deleteSecret()` intentionally throw a clear exception when Borneo Secrets Manager is disabled or incomplete, because those are explicit write/delete actions. `getSecret()` returns `null` when the integration is disabled, not configured, or the secret cannot be resolved.

## Configuration

The package publishes `config/bunnycdn.php`.

You do not need to manually add a `bunnycdn` disk to `config/filesystems.php`; the package registers it at runtime from `config/bunnycdn.php`. If you do define `filesystems.disks.bunnycdn`, your app-level values override the package defaults.

## Failure behavior

This package avoids the previous circular failure where the service provider tried to read the API key through Laravel's default `Storage` disk before the custom `bunnycdn` driver existed.

Current behavior:

- The `bunnycdn` driver is always registered during boot.
- Borneo Secrets Manager is disabled by default and used only when `BORNEO_SECRET_ENABLED=true`.
- Missing `storage_zone` or `api_key` throws a clear `BunnySecretException` when the disk is used.
- Facade helpers such as `uploadCDN()` catch upload errors, log context, and return `false` for backward compatibility.

## Useful commands after changing config

```bash
php artisan optimize:clear
php artisan package:discover
composer dump-autoload
```

## License

This package is licensed under the [MIT License](https://opensource.org/licenses/MIT).