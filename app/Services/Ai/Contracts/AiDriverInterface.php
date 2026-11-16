<?php

namespace App\Services\Ai\Contracts;

interface AiDriverInterface
{
    /**
     * Test connection with API credentials.
     *
     * @return array{success: bool, message: string, http_status?: int|null, error_category?: string}
     */
    public function testConnection(string $apiKey, ?string $baseUrl, string $model): array;

    /**
     * Generate reply from system prompt and user message.
     *
     * @throws \RuntimeException
     */
    public function generate(
        string $apiKey,
        ?string $baseUrl,
        string $model,
        string $systemPrompt,
        string $userMessage,
        float $temperature = 0.7,
        int $maxTokens = 1024
    ): string;
}
