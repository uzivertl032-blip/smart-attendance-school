<?php

namespace App\Services\Ai\Drivers;

use App\Models\AiSetting;
use App\Services\Ai\AiManager;
use App\Services\Ai\Contracts\AiDriverInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class OpenAiDriver implements AiDriverInterface
{
    /**
     * Test connection to OpenAI API.
     */
    public function testConnection(string $apiKey, ?string $baseUrl, string $model): array
    {
        $keyCheck = $this->prepareApiKey($apiKey);

        if ($keyCheck['valid'] === false) {
            return [
                'success' => false,
                'message' => $keyCheck['error'] ?? 'API Key OpenAI tidak valid.',
                'http_status' => null,
                'error_category' => $keyCheck['category'] ?? 'invalid_key',
            ];
        }

        $cleanKey = $keyCheck['key'];
        $cleanModel = trim($model);
        $base = rtrim($baseUrl ?: AiSetting::defaultBaseUrl('openai'), '/');
        $url = "{$base}/chat/completions";

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => "Bearer {$cleanKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post($url, [
                    'model' => $cleanModel,
                    'messages' => [
                        ['role' => 'user', 'content' => 'Balas dengan satu kata: pong'],
                    ],
                    'max_completion_tokens' => 512,
                ]);

            if ($response->successful()) {
                $text = $this->extractText($response->json());

                if ($text === '') {
                    $finishReason = (string) $response->json('choices.0.finish_reason', '');
                    $reasoningTokens = (int) $response->json('usage.completion_tokens_details.reasoning_tokens', 0);

                    Log::warning('OpenAI connection test returned no content.', [
                        'provider' => 'openai',
                        'model' => $cleanModel,
                        'http_status' => $response->status(),
                        'error_category' => 'empty_response',
                        'finish_reason' => $finishReason,
                        'reasoning_tokens' => $reasoningTokens,
                    ]);

                    $detail = $finishReason !== ''
                        ? " (finish_reason: {$finishReason})"
                        : '';

                    return [
                        'success' => false,
                        'message' => "OpenAI merespons (HTTP {$response->status()}) tetapi tidak mengembalikan teks jawaban{$detail}. Coba model lain atau ulangi pengujian.",
                        'http_status' => $response->status(),
                        'error_category' => 'empty_response',
                    ];
                }

                return [
                    'success' => true,
                    'message' => "Koneksi OpenAI berhasil! Model '{$cleanModel}' merespons dan siap digunakan.",
                    'http_status' => $response->status(),
                    'error_category' => 'success',
                ];
            }

            return $this->failureFromResponse($response->status(), $response->json(), $cleanModel);
        } catch (Throwable $e) {
            $message = AiManager::redact($e->getMessage(), $cleanKey);

            Log::warning('OpenAI connection test exception.', [
                'provider' => 'openai',
                'model' => $cleanModel,
                'http_status' => null,
                'error_category' => 'network_error',
                'error' => $message,
            ]);

            return [
                'success' => false,
                'message' => $this->networkMessage($message),
                'http_status' => null,
                'error_category' => 'network_error',
            ];
        }
    }

    /**
     * Generate content using OpenAI.
     */
    public function generate(
        string $apiKey,
        ?string $baseUrl,
        string $model,
        string $systemPrompt,
        string $userMessage,
        float $temperature = 0.7,
        int $maxTokens = 1024
    ): string {
        $keyCheck = $this->prepareApiKey($apiKey);

        if ($keyCheck['valid'] === false) {
            throw new \RuntimeException($keyCheck['error'] ?? 'API Key OpenAI tidak valid.');
        }

        $cleanKey = $keyCheck['key'];
        $base = rtrim($baseUrl ?: AiSetting::defaultBaseUrl('openai'), '/');
        $url = "{$base}/chat/completions";

        $response = Http::timeout(90)
            ->withHeaders([
                'Authorization' => "Bearer {$cleanKey}",
                'Content-Type' => 'application/json',
            ])
            ->post($url, [
                'model' => trim($model),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userMessage],
                ],
                'max_completion_tokens' => $maxTokens,
            ]);

        if (! $response->successful()) {
            $failure = $this->failureFromResponse($response->status(), $response->json(), trim($model));

            throw new \RuntimeException($failure['message']);
        }

        $text = $this->extractText($response->json());

        if ($text === '') {
            $finishReason = (string) $response->json('choices.0.finish_reason', '');
            $detail = $finishReason !== '' ? " (finish_reason: {$finishReason})" : '';

            throw new \RuntimeException("OpenAI mengembalikan respons kosong{$detail}.");
        }

        return $text;
    }

    /**
     * Extract assistant text from an OpenAI-compatible response.
     *
     * Content may be a plain string or an array of content parts.
     *
     * @param  array<string, mixed>|null  $body
     */
    protected function extractText(?array $body): string
    {
        $text = $this->stringifyContent(data_get($body, 'choices.0.message.content'));

        if ($text === '') {
            $text = $this->stringifyContent(data_get($body, 'choices.0.text'));
        }

        return $text;
    }

    /**
     * Convert a string or content-part array into plain text.
     */
    protected function stringifyContent(mixed $content): string
    {
        if (is_string($content)) {
            return trim($content);
        }

        if (! is_array($content)) {
            return '';
        }

        $text = '';

        foreach ($content as $part) {
            if (is_string($part)) {
                $text .= $part;

                continue;
            }

            if (! is_array($part)) {
                continue;
            }

            $candidate = $part['text'] ?? $part['content'] ?? null;
            $text .= $this->stringifyContent($candidate);
        }

        return trim($text);
    }

    /**
     * Clean and validate an OpenAI API key before sending it to the provider.
     *
     * @return array{valid: bool, key: string, error?: string, category?: string}
     */
    protected function prepareApiKey(string $rawKey): array
    {
        $clean = AiSetting::sanitizeKey($rawKey) ?? '';

        if ($clean === '') {
            return [
                'valid' => false,
                'key' => '',
                'error' => 'API Key OpenAI belum diisi. Masukkan API Key resmi dari OpenAI Platform.',
                'category' => 'missing_key',
            ];
        }

        if (AiSetting::isPlaceholderKey($clean)) {
            return [
                'valid' => false,
                'key' => '',
                'error' => 'API Key masih berupa teks placeholder. Masukkan API Key OpenAI yang sebenarnya dari https://platform.openai.com/api-keys.',
                'category' => 'placeholder_key',
            ];
        }

        $mismatch = AiSetting::keyProviderMismatch('openai', $clean);

        if ($mismatch !== null) {
            return [
                'valid' => false,
                'key' => '',
                'error' => $mismatch,
                'category' => 'key_provider_mismatch',
            ];
        }

        return [
            'valid' => true,
            'key' => $clean,
        ];
    }

    /**
     * Convert a provider error response into a clear, actionable message.
     *
     * @param  array<string, mixed>|null  $body
     * @return array{success: bool, message: string, http_status: int, error_category: string}
     */
    protected function failureFromResponse(int $status, ?array $body, string $model): array
    {
        $code = strtolower((string) data_get($body, 'error.code', ''));
        $type = strtolower((string) data_get($body, 'error.type', ''));
        $param = (string) data_get($body, 'error.param', '');
        $errorMessage = (string) (data_get($body, 'error.message') ?? "HTTP {$status}");
        $lower = strtolower($errorMessage);

        $invalidKey = $status === 401
            || $code === 'invalid_api_key'
            || str_contains($lower, 'incorrect api key')
            || str_contains($lower, 'invalid api key')
            || str_contains($lower, 'invalid_api_key');

        if ($invalidKey) {
            return [
                'success' => false,
                'message' => 'API Key OpenAI tidak valid atau sudah dicabut. Periksa kembali kunci dari OpenAI Platform.',
                'http_status' => $status,
                'error_category' => 'authentication_error',
            ];
        }

        $quotaExceeded = $code === 'insufficient_quota'
            || $type === 'insufficient_quota'
            || str_contains($lower, 'exceeded your current quota')
            || str_contains($lower, 'insufficient quota')
            || str_contains($lower, 'billing hard limit')
            || str_contains($lower, 'credit balance');

        if ($quotaExceeded) {
            return [
                'success' => false,
                'message' => 'Kuota OpenAI habis atau saldo/billing tidak mencukupi. Periksa usage dan billing di OpenAI Platform.',
                'http_status' => $status,
                'error_category' => 'quota_exceeded',
            ];
        }

        $modelUnavailable = $status === 404
            || $code === 'model_not_found'
            || $param === 'model'
            || str_contains($lower, 'does not exist')
            || str_contains($lower, 'not have access to model')
            || str_contains($lower, 'unknown model')
            || str_contains($lower, 'model_not_found');

        if ($modelUnavailable) {
            return [
                'success' => false,
                'message' => "Model OpenAI '{$model}' tidak tersedia atau akun Anda tidak memiliki akses ke model tersebut. Pilih model resmi lain dari daftar provider OpenAI.",
                'http_status' => $status,
                'error_category' => 'model_unavailable',
            ];
        }

        if ($status === 403) {
            return [
                'success' => false,
                'message' => 'Akses ke OpenAI ditolak (HTTP 403). Periksa izin project/organization untuk API Key ini.',
                'http_status' => $status,
                'error_category' => 'permission_denied',
            ];
        }

        if ($status === 429) {
            return [
                'success' => false,
                'message' => 'Rate limit OpenAI terlampaui. Tunggu beberapa saat lalu coba lagi.',
                'http_status' => $status,
                'error_category' => 'rate_limit',
            ];
        }

        if ($status >= 500) {
            return [
                'success' => false,
                'message' => "Layanan OpenAI sedang gangguan sementara (HTTP {$status}). Coba beberapa saat lagi.",
                'http_status' => $status,
                'error_category' => 'provider_unavailable',
            ];
        }

        return [
            'success' => false,
            'message' => "Pengujian OpenAI gagal (HTTP {$status}): {$errorMessage}",
            'http_status' => $status,
            'error_category' => 'provider_error',
        ];
    }

    /**
     * Build a friendly network failure message.
     */
    protected function networkMessage(string $error): string
    {
        $lower = strtolower($error);

        if (str_contains($lower, 'timed out') || str_contains($lower, 'timeout')) {
            return 'Koneksi ke OpenAI timeout. Periksa koneksi internet server lalu coba lagi.';
        }

        if (str_contains($lower, 'could not resolve host') || str_contains($lower, 'name or service not known')) {
            return 'Server tidak dapat menjangkau OpenAI (DNS gagal). Periksa koneksi internet server.';
        }

        if (str_contains($lower, 'ssl') || str_contains($lower, 'certificate')) {
            return 'Koneksi SSL ke OpenAI gagal. Periksa sertifikat/konfigurasi jaringan server.';
        }

        return 'Koneksi ke OpenAI gagal: '.$error;
    }
}
