# [Personal use] Bunny Secrets

This Laravel package provides a seamless integration with **BunnyCDN** for both secret management and filesystem operations. It allows you to easily upload, delete, and retrieve files from your BunnyCDN Storage Zone while securely managing your API keys.

## Key Features

  * **Secrets Management:** Fetch and store API keys and other secrets from an external service.
  * **Flysystem Driver:** Full integration with Laravel's filesystem (leveraging `platformcommunity/flysystem-bunnycdn`).
  * **File Operations:** Effortlessly upload, retrieve public URLs, and delete files on BunnyCDN.
  * **ImageKit Support:** Optionally, transform image URLs to utilize BunnyCDN's ImageKit.io features.
  * **Service Provider:** Automatic configuration for Laravel 10.0 and above.

-----

## Requirements

  * **PHP:** ^8.2
  * **Laravel Framework:** ^10.0
  * API from Borneo Secrets Manager

-----

## Installation

You can install the package via Composer:

```bash
composer require novay/bunny-secrets
```

After installation, the `BunnySecretServiceProvider` will be automatically discovered and registered by Laravel.

-----

## Configuration

To publish the `bunnycdn.php` configuration file, run the following Artisan command:

```bash
php artisan vendor:publish --tag=bunny-secrets-config
```

The configuration file will be located at `config/bunnycdn.php`. Make sure to populate the necessary environment variables in your `.env` file, such as `BUNNYCDN_API_KEY` and `BUNNYCDN_STORAGE_ZONE`.

Example `.env` configuration:

```bash
# BunnyCDN Storage Zone & API Key
BUNNYCDN_STORAGE_ZONE="your-storage-zone-name"
BUNNYCDN_API_KEY="your-api-key"
BUNNYCDN_REGION="sg" # e.g., 'de', 'ny', 'la', 'sao', 'syd', 'singapore', 'london', 'tokyo'
BUNNYCDN_CDN_URL="https://your-cdn-hostname.b-cdn.net"

# Secret API Service
SECRET_URI="https://api.your-secret-service.com"
SECRET_KEY="your-secret-service-api-key"

# Secret File & Key
BUNNY_SECRET_FILE="bunny_api_key.txt"
BUNNY_SECRET_KEY="bunny-secrets-pass"
```

-----

## Usage

You can access the package's functionality through the **`BunnySecret` Facade**.

```php
use Novay\BunnySecret\BunnySecret;
use Illuminate\Http\UploadedFile;

// Upload a file to BunnyCDN
$file = new UploadedFile(
    'path/to/your/local/file.jpg',
    'file.jpg'
);
$filePath = BunnySecret::uploadCDN($file, 'images');

if ($filePath) {
    echo "File uploaded successfully to: " . $filePath;
}

// Get the public URL of the file
$fileUrl = BunnySecret::showCDN($filePath);
echo "Public file URL: " . $fileUrl;

// Transform an image URL with ImageKit (if configured)
$imageUrl = 'https://s3-url/images/my-image.jpg';
$resizedImageUrl = BunnySecret::imageKit($imageUrl, 800);
echo "Transformed image URL: " . $resizedImageUrl;

// Delete a file
$isDeleted = BunnySecret::deleteCDN($filePath);
if ($isDeleted) {
    echo "File deleted successfully.";
}
```

### Accessing via the `Storage` Facade

Since this package provides a custom Flysystem driver, you can also use Laravel's `Storage` Facade with the `bunnycdn` disk.

```php
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

// Upload a file
$fileContent = file_get_contents('path/to/your/local/file.jpg');
Storage::disk('bunnycdn')->put('images/file.jpg', $fileContent);

// Get a public URL
$url = Storage::disk('bunnycdn')->url('images/file.jpg');

// Delete a file
Storage::disk('bunnycdn')->delete('images/file.jpg');
```

-----

## License

This package is licensed under the [MIT License](https://opensource.org/licenses/MIT).