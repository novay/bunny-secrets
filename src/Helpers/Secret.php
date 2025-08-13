<?php

namespace Novay\BunnySecret\Helpers;

use Illuminate\Support\Facades\Http;
use Exception;

/**
 * Secret
 *
 * Kelas ini bertanggung jawab untuk berinteraksi dengan layanan Secret API
 * untuk mengambil, menyimpan, dan menghapus secret (rahasia) dari sumber eksternal.
 * Ini menggunakan HTTP client Laravel untuk melakukan permintaan API.
 *
 * @package Novay\BunnySecret
 * @author Novay <novay@btekno.id>
 * @license https://opensource.org/licenses/MIT MIT License
 */
class Secret
{
    /**
     * Base URI (Uniform Resource Identifier) untuk Secret API.
     *
     * @var string
     */
    protected string $baseUri;

    /**
     * API Key yang digunakan untuk otorisasi permintaan ke Secret API.
     *
     * @var string
     */
    protected string $apiKey;

    /**
     * Konstruktor kelas Secret.
     *
     * Menginisialisasi base URI dan API key yang diperlukan untuk berinteraksi
     * dengan Secret API.
     *
     * @param string $baseUri Base URI dari layanan Secret API (e.g., 'https://api.your-secret-service.com').
     * @param string $apiKey API Key otorisasi untuk layanan Secret API.
     */
    public function __construct(string $baseUri, string $apiKey)
    {
        $this->baseUri = $baseUri;
        $this->apiKey = $apiKey;
    }

    /**
     * Mengambil secret berdasarkan kuncinya dari Secret API.
     *
     * Melakukan permintaan GET ke API untuk mengambil nilai secret.
     * Jika permintaan berhasil, mengembalikan data secret; jika tidak,
     * mengembalikan respons error.
     *
     * @param string $key Kunci (nama) dari secret yang ingin diambil.
     * @return mixed Mengembalikan nilai secret (biasanya string, array, atau integer).
     */
    public function getSecret(string $key): mixed
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
        ])->get($this->baseUri . '/api/secrets/' . $key);

        if ($response->successful()) {
            return $response->json('data');
        }

        // Jika tidak berhasil, kembalikan null atau respons error, tergantung kebutuhan
        // Throwing exception lebih disarankan untuk handling error yang eksplisit
        return null;
        // throw new Exception("Unable to retrieve secret: " . $response->body());
    }

    /**
     * Menyimpan secret baru atau memperbarui secret yang sudah ada di Secret API.
     *
     * Melakukan permintaan POST ke API untuk menyimpan pasangan kunci-nilai secret.
     *
     * @param string $key Kunci (nama) dari secret yang ingin disimpan.
     * @param mixed $value Nilai dari secret yang ingin disimpan.
     * @return array Respons JSON dari API.
     * @throws Exception Jika gagal menyimpan secret (misalnya, masalah otorisasi, validasi, dll.).
     */
    public function storeSecret(string $key, mixed $value): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
        ])->post($this->baseUri . '/api/secrets', [
            'key' => $key,
            'value' => $value,
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        throw new Exception("Unable to store secret: " . $response->body());
    }

    /**
     * Menghapus secret berdasarkan kuncinya dari Secret API.
     *
     * Melakukan permintaan DELETE ke API untuk menghapus secret.
     *
     * @param string $key Kunci (nama) dari secret yang ingin dihapus.
     * @return bool True jika secret berhasil dihapus.
     * @throws Exception Jika gagal menghapus secret.
     */
    public function deleteSecret(string $key): bool
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
        ])->delete($this->baseUri . '/api/secrets/' . $key);

        if ($response->successful()) {
            return true;
        }

        throw new Exception("Unable to delete secret: " . $response->body());
    }
}