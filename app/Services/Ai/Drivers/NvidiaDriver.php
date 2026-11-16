<?php

namespace App\Services\Ai\Drivers;

use App\Models\AiSetting;
use App\Services\Ai\AiManager;
use App\Services\Ai\Contracts\AiDriverInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class NvidiaDriver implements AiDriverInterface
{
    /**
     * Test connection to the NVIDIA NIM API (OpenAI-compatible endpoint).
     */
    public function testConnection(string $apiKey, ?string $baseUrl, string $model): array
    {
        $keyCheck = $this->prepareApiKey($apiKey);

        if ($keyCheck['valid'] === false) {
            return [
                'success' => false,
                'message' => $keyCheck['error'] ?? 'API Key NVIDIA NIM tidak valid.',
                'http_status' => null,
                'error_category' => $keyCheck['category'] ?? 'invalid_key',
            ];
        }

        $cleanKey = $keyCheck['key'];
        $cleanModel = trim($model);
        $base = rtrim($baseUrl ?: AiSetting::defaultBaseUrl('nvidia'), '/');
        $url = "{$base}/chat/completions";

        try {
            $response = Http::timeout(120)
                ->withToken($cleanKey)
                ->acceptJson()
                ->post($url, [
                    'model' => $cleanModel,
                    'messages' => [
                        ['role' => 'user', 'content' => 'Reply with the single word: pong'],
                    ],
                    'max_tokens' => 512,
                    'temperature' => 0.2,
                ]);

            if ($response->successful()) {
                $text = $this->extractText($response->json());

                if ($text === '') {
                    $reasoningTokens = (int) $response->json('usage.completion_tokens_details.reasoning_tokens', 0);
                    $finishReason = (string) $response->json('choices.0.finish_reason', '');

                    Log::warning('NVIDIA connection test returned no content.', [
                        'provider' => 'nvidia',
                        'model' => $cleanModel,
                        'http_status' => $response->status(),
                        'error_category' => 'empty_response',
                        'finish_reason' => $finishReason,
                        'reasoning_tokens' => $reasoningTokens,
                    ]);

                    $detail = $finishReason !== '' ? " (finish_reason: {$finishReason})" : '';

                    return [
                        'success' => false,
                        'message' => "NVIDIA merespons (HTTP {$response->status()}) tetapi tidak mengembalikan teks jawaban{$detail}. Model reasoning mungkin kehabisan token; naikkan Batas Token Maksimal atau pilih model lain.",
                        'http_status' => $response->status(),
                        'error_category' => 'empty_response',
                    ];
                }

                return [
                    'success' => true,
                    'message' => "Koneksi NVIDIA NIM berhasil! Model '{$cleanModel}' merespons dan siap digunakan.",
                    'http_status' => $response->status(),
                    'error_category' => 'success',
                ];
            }

            return $this->failureFromResponse($response->status(), $response->json(), $cleanModel);
        } catch (Throwable $e) {
            $message = AiManager::redact($e->getMessage(), $cleanKey);

            Log::warning('NVIDIA connection test exception.', [
                'provider' => 'nvidia',
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
     * Generate content using an NVIDIA NIM chat model.
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
            throw new \RuntimeException($keyCheck['error'] ?? 'API Key NVIDIA NIM tidak valid.');
        }

        $cleanKey = $keyCheck['key'];
        $base = rtrim($baseUrl ?: AiSetting::defaultBaseUrl('nvidia'), '/');
        $url = "{$base}/chat/completions";

        $response = Http::timeout(180)
            ->withToken($cleanKey)
            ->acceptJson()
            ->post($url, [
                'model' => trim($model),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userMessage],
                ],
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ]);

        if (! $response->successful()) {
            $failure = $this->failureFromResponse($response->status(), $response->json(), trim($model));

            throw new \RuntimeException($failure['message']);
        }

        $text = $this->extractText($response->json());

        if ($text === '') {
            $finishReason = (string) $response->json('choices.0.finish_reason', '');
            $detail = $finishReason !== '' ? " (finish_reason: {$finishReason})" : '';

            throw new \RuntimeException("NVIDIA NIM mengembalikan respons kosong{$detail}. Naikkan Batas Token Maksimal atau pilih model lain.");
        }

        return $text;
    }

    /**
     * Extract assistant text from an NVIDIA NIM (OpenAI-compatible) response.
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
     * Clean and validate an NVIDIA API key before sending it to the provider.
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
                'error' => 'API Key NVIDIA NIM belum diisi. Masukkan API Key resmi dari https://build.nvidia.com/.',
                'category' => 'missing_key',
            ];
        }

        if (AiSetting::isPlaceholderKey($clean)) {
            return [
                'valid' => false,
                'key' => '',
                'error' => 'API Key masih berupa teks placeholder. Masukkan API Key NVIDIA yang sebenarnya (berawalan nvapi-).',
                'category' => 'placeholder_key',
            ];
        }

        $mismatch = AiSetting::keyProviderMismatch('nvidia', $clean);

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
        $errorMessage = (string) (data_get($body, 'error.message') ?? "HTTP {$status}");
        $lower = strtolower($errorMessage);

        $invalidKey = $status === 401
            || $code === 'invalid_api_key'
            || $type === 'authentication_error'
            || str_contains($lower, 'invalid api key')
            || str_contains($lower, 'incorrect api key')
            || str_contains($lower, 'authentication');

        if ($invalidKey) {
            return [
                'success' => false,
                'message' => 'API Key NVIDIA tidak valid atau sudah dicabut. Periksa kembali kunci dari https://build.nvidia.com/.',
                'http_status' => $status,
                'error_category' => 'authentication_error',
            ];
        }

        if ($status === 402 || $status === 403) {
            return [
                'success' => false,
                'message' => "Akses NVIDIA ditolak (HTTP {$status}). Pastikan API Key aktif dan model tersedia untuk akun Anda.",
                'http_status' => $status,
                'error_category' => 'permission_denied',
            ];
        }

        if ($status === 404 || $code === 'model_not_found' || $type === 'not_found_error') {
            return [
                'success' => false,
                'message' => "Model NVIDIA '{$model}' tidak tersedia di endpoint ini untuk akun Anda. Pilih model resmi lain dari daftar provider NVIDIA.",
                'http_status' => $status,
                'error_category' => 'model_unavailable',
            ];
        }

        $quotaExceeded = $status === 429
            || str_contains($lower, 'quota')
            || str_contains($lower, 'credit balance');

        if ($quotaExceeded) {
            return [
                'success' => false,
                'message' => 'Kuota NVIDIA habis atau rate limit terlampaui. Periksa usage di NVIDIA Build atau coba lagi nanti.',
                'http_status' => $status,
                'error_category' => 'quota_or_rate_limit',
            ];
        }

        if ($status >= 500) {
            return [
                'success' => false,
                'message' => "Layanan NVIDIA sedang gangguan sementara (HTTP {$status}). Coba beberapa saat lagi.",
                'http_status' => $status,
                'error_category' => 'provider_unavailable',
            ];
        }

        return [
            'success' => false,
            'message' => "Pengujian NVIDIA gagal (HTTP {$status}): {$errorMessage}",
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
            return 'Koneksi ke NVIDIA timeout. Model mungkin sedang sibuk; coba lagi atau pilih model yang lebih ringan.';
        }

        if (str_contains($lower, 'could not resolve host') || str_contains($lower, 'name or service not known')) {
            return 'Server tidak dapat menjangkau NVIDIA (DNS gagal). Periksa koneksi internet server.';
        }

        if (str_contains($lower, 'ssl') || str_contains($lower, 'certificate')) {
            return 'Koneksi SSL ke NVIDIA gagal. Periksa sertifikat/konfigurasi jaringan server.';
        }

        return 'Koneksi ke NVIDIA gagal: '.$error;
    }
}
