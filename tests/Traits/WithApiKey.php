<?php

namespace Tests\Traits;

use App\Models\ApiKey;

trait WithApiKey
{
    /**
     * The default API key for testing.
     */
    protected string $testApiKey = 'dev_test_ddddddddddddddddddddddddddddddddddddddddddddddddddddddd';

    /**
     * Ensure the test API key exists in the database.
     */
    protected function ensureTestApiKeyExists(): void
    {
        if (!ApiKey::where('key', $this->testApiKey)->exists()) {
            ApiKey::create([
                'name' => 'Test API Key',
                'key' => $this->testApiKey,
                'platform' => 'testing',
                'is_active' => true,
                'rate_limit' => 10000,
            ]);
        }
    }

    /**
     * Make a GET request with API key.
     */
    protected function getJsonWithApiKey(string $uri, array $headers = []): \Illuminate\Testing\TestResponse
    {
        $this->ensureTestApiKeyExists();
        
        return $this->getJson($uri, array_merge([
            'X-API-Key' => $this->testApiKey,
        ], $headers));
    }

    /**
     * Make a POST request with API key.
     */
    protected function postJsonWithApiKey(string $uri, array $data = [], array $headers = []): \Illuminate\Testing\TestResponse
    {
        $this->ensureTestApiKeyExists();
        
        return $this->postJson($uri, $data, array_merge([
            'X-API-Key' => $this->testApiKey,
        ], $headers));
    }

    /**
     * Make a PATCH request with API key.
     */
    protected function patchJsonWithApiKey(string $uri, array $data = [], array $headers = []): \Illuminate\Testing\TestResponse
    {
        $this->ensureTestApiKeyExists();
        
        return $this->patchJson($uri, $data, array_merge([
            'X-API-Key' => $this->testApiKey,
        ], $headers));
    }

    /**
     * Make a DELETE request with API key.
     */
    protected function deleteJsonWithApiKey(string $uri, array $data = [], array $headers = []): \Illuminate\Testing\TestResponse
    {
        $this->ensureTestApiKeyExists();
        
        return $this->deleteJson($uri, $data, array_merge([
            'X-API-Key' => $this->testApiKey,
        ], $headers));
    }

    /**
     * Add API key header to existing headers.
     */
    protected function withApiKeyHeader(array $headers = []): array
    {
        $this->ensureTestApiKeyExists();
        
        return array_merge([
            'X-API-Key' => $this->testApiKey,
        ], $headers);
    }
}
