<?php

namespace Novay\BunnySecret\Helpers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class Secret
{
    public function __construct(
        protected string $baseUri,
        protected string $apiKey,
        protected bool $enabled = false,
        protected int $timeout = 10,
        protected int $retries = 1,
        protected int $retrySleepMs = 200,
    ) {
        $this->baseUri = rtrim(trim($this->baseUri), '/');
        $this->apiKey = trim($this->apiKey);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isConfigured(): bool
    {
        return $this->isEnabled() && $this->baseUri !== '' && $this->apiKey !== '';
    }

    public function getSecret(string $key): mixed
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $response = $this->request()->get('/api/secrets/' . rawurlencode($key));

            if ($response->successful()) {
                return $response->json('data');
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function storeSecret(string $key, mixed $value): array
    {
        $this->ensureConfigured();

        $response = $this->request()->post('/api/secrets', [
            'key' => $key,
            'value' => $value,
        ]);

        if ($response->successful()) {
            return $response->json() ?? [];
        }

        throw new RuntimeException('Unable to store secret in Borneo Secrets Manager: ' . $response->body());
    }

    public function deleteSecret(string $key): bool
    {
        $this->ensureConfigured();

        $response = $this->request()->delete('/api/secrets/' . rawurlencode($key));

        if ($response->successful()) {
            return true;
        }

        throw new RuntimeException('Unable to delete secret from Borneo Secrets Manager: ' . $response->body());
    }

    protected function ensureConfigured(): void
    {
        if (!$this->isEnabled()) {
            throw new RuntimeException('Borneo Secrets Manager is disabled. Set BORNEO_SECRET_ENABLED=true to use the secret API.');
        }

        if ($this->baseUri === '' || $this->apiKey === '') {
            throw new RuntimeException('Borneo Secrets Manager is enabled but not configured. Please set BORNEO_SECRET_URI and BORNEO_SECRET_API_KEY.');
        }
    }

    protected function request(): PendingRequest
    {
        $request = Http::baseUrl($this->baseUri)
            ->acceptJson()
            ->timeout(max(1, $this->timeout))
            ->withToken($this->apiKey);

        if ($this->retries > 0) {
            $request = $request->retry(
                $this->retries,
                max(0, $this->retrySleepMs),
                static fn(Throwable $exception): bool => $exception instanceof ConnectionException,
                false,
            );
        }

        return $request;
    }
}
