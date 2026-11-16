<?php

namespace App\Services\Ai\Drivers;

use App\Models\AiSetting;
use App\Services\Ai\AiManager;
use App\Services\Ai\Contracts\AiDriverInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeminiDriver implements AiDriverInterface
{
    /**
     * Test connection to Google Gemini API.
     */
    public function testConnection(string $apiKey, ?string $baseUrl, string $model): array
    {
        $keyCheck = $this->prepareApiKey($apiKey);

        if ($keyCheck['valid'] === false) {
            return [
                'success' => false,
                'message' => $keyCheck['error'] ?? 'API Key Google Gemini tidak valid.',
                'http_status' => null,
                'error_category' => $keyCheck['category'] ?? 'invalid_key',
            ];
        }

        $cleanKey = $keyCheck['key'];
        $cleanModel = $this->normalizeModel($model);
        $base = rtrim($baseUrl ?: AiSetting::defaultBaseUrl('gemini'), '/');
        $url = "{$base}/models/{$cleanModel}:generateContent";

        try {
            $response = Http::timeout(45)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => $cleanKey,
                ])
                ->post($url, [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [['text' => 'Balas dengan satu kata: pong']],
                        ],
                    ],
                    'generationConfig' => [
                        'maxOutputTokens' => 512,
                    ],
                ]);

            if ($response->successful()) {
                $text = $this->extractText($response->json());

                if ($text === '') {
                    $finishReason = (string) $response->json('candidates.0.finishReason', '');
                    $blockReason = (string) $response->json('promptFeedback.blockReason', '');

                    Log::warning('Gemini connection test returned no content.', [
                        'provider' => 'gemini',
                        'model' => $cleanModel,
                        'http_status' => $response->status(),
                        'error_category' => 'empty_response',
                        'finish_reason' => $finishReason,
                        'block_reason' => $blockReason,
                    ]);

                    $detail = $blockReason !== ''
                        ? " (blockReason: {$blockReason})"
                        : ($finishReason !== '' ? " (finishReason: {$finishReason})" : '');

                    return [
                        'success' => false,
                        'message' => "Google Gemini merespons (HTTP {$response->status()}) tetapi tidak mengembalikan teks jawaban{$detail}. Model thinking mungkin kehabisan token; coba lagi atau pilih model lain.",
                        'http_status' => $response->status(),
                        'error_category' => 'empty_response',
                    ];
                }

                return [
                    'success' => true,
                    'message' => "Koneksi Google Gemini berhasil! Model '{$cleanModel}' merespons dan siap digunakan.",
                    'http_status' => $response->status(),
                    'error_category' => 'success',
                ];
            }

            return $this->failureFromResponse($response->status(), $response->json(), $cleanModel);
        } catch (Throwable $e) {
            $message = AiManager::redact($e->getMessage(), $cleanKey);

            Log::warning('Gemini connection test exception.', [
                'provider' => 'gemini',
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
     * Generate content using Google Gemini.
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
            throw new \RuntimeException($keyCheck['error'] ?? 'API Key Google Gemini tidak valid.');
        }

        $cleanKey = $keyCheck['key'];
        $cleanModel = $this->normalizeModel($model);
        $base = rtrim($baseUrl ?: AiSetting::defaultBaseUrl('gemini'), '/');
        $url = "{$base}/models/{$cleanModel}:generateContent";

        $response = Http::timeout(90)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $cleanKey,
            ])
            ->post($url, [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => "Instruksi Sistem:\n{$systemPrompt}\n\nPertanyaan Pengguna:\n{$userMessage}"],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => $temperature,
                    'maxOutputTokens' => $maxTokens,
                ],
            ]);

        if (! $response->successful()) {
            $failure = $this->failureFromResponse($response->status(), $response->json(), $cleanModel);

            throw new \RuntimeException($failure['message']);
        }

        $text = $this->extractText($response->json());

        if ($text === '') {
            $finishReason = (string) $response->json('candidates.0.finishReason', '');
            $detail = $finishReason !== '' ? " (finishReason: {$finishReason})" : '';

            throw new \RuntimeException("Google Gemini mengembalikan respons kosong{$detail}.");
        }

        return $text;
    }

    /**
     * Join every answer text part returned by Gemini, ignoring thought parts.
     *
     * @param  array<string, mixed>|null  $body
     */
    protected function extractText(?array $body): string
    {
        $parts = data_get($body, 'candidates.0.content.parts');

        if (! is_array($parts)) {
            return '';
        }

        $text = '';

        foreach ($parts as $part) {
            if (! is_array($part) || ($part['thought'] ?? false) === true) {
                continue;
            }

            if (isset($part['text']) && is_string($part['text'])) {
                $text .= $part['text'];
            }
        }

        return trim($text);
    }

    /**
     * Normalize a model name. Provider-specific legacy names are never replaced
     * with a model that belongs to another provider.
     */
    protected function normalizeModel(string $model): string
    {
        return trim($model);
    }

    /**
     * Clean and validate the format of a Google Gemini API key before sending.
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
                'error' => 'API Key Google Gemini belum diisi. Silakan masukkan API Key resmi dari Google AI Studio.',
                'category' => 'missing_key',
            ];
        }

        if (AiSetting::isPlaceholderKey($clean)) {
            return [
                'valid' => false,
                'key' => '',
                'error' => 'API Key masih berupa teks placeholder/contoh. Masukkan API Key Google Gemini yang sebenarnya dari Google AI Studio (https://aistudio.google.com/).',
                'category' => 'placeholder_key',
            ];
        }

        $mismatch = AiSetting::keyProviderMismatch('gemini', $clean);

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
        $apiStatus = strtoupper((string) data_get($body, 'error.status', ''));
        $reason = strtoupper((string) data_get($body, 'error.details.0.reason', ''));
        $errorMessage = (string) (data_get($body, 'error.message') ?? "HTTP {$status}");
        $lower = strtolower($errorMessage);

        $invalidKey = in_array($apiStatus, ['UNAUTHENTICATED', 'PERMISSION_DENIED'], true)
            || in_array($reason, ['API_KEY_INVALID', 'API_KEY_EXPIRED'], true)
            || str_contains($lower, 'api key not valid')
            || str_contains($lower, 'api key expired')
            || str_contains($lower, 'api_key_invalid')
            || str_contains($lower, 'expected oauth2 access token');

        if ($invalidKey) {
            return [
                'success' => false,
                'message' => 'API Key Google Gemini tidak valid atau sudah kedaluwarsa. Periksa kembali kunci dari Google AI Studio (https://aistudio.google.com/).',
                'http_status' => $status,
                'error_category' => 'authentication_error',
            ];
        }

        $modelUnavailable = $status === 404
            || $apiStatus === 'NOT_FOUND'
            || str_contains($lower, 'is not found')
            || str_contains($lower, 'not found for api version')
            || str_contains($lower, 'is not supported')
            || str_contains($lower, 'does not exist');

        if ($modelUnavailable) {
            return [
                'success' => false,
                'message' => "Model Google Gemini '{$model}' tidak tersedia untuk akun/API ini. Pilih model resmi lain dari daftar provider Gemini.",
                'http_status' => $status,
                'error_category' => 'model_unavailable',
            ];
        }

        $quotaExceeded = $status === 429
            || $apiStatus === 'RESOURCE_EXHAUSTED'
            || str_contains($lower, 'quota')
            || str_contains($lower, 'rate limit');

        if ($quotaExceeded) {
            return [
                'success' => false,
                'message' => 'Kuota atau rate limit Google Gemini habis/terlampaui. Periksa kuota akun di Google AI Studio atau coba lagi nanti.',
                'http_status' => $status,
                'error_category' => 'quota_or_rate_limit',
            ];
        }

        if ($status === 403 || $apiStatus === 'PERMISSION_DENIED') {
            return [
                'success' => false,
                'message' => "Akses ke Google Gemini ditolak (HTTP {$status}). Pastikan Generative Language API aktif dan API Key memiliki izin yang benar.",
                'http_status' => $status,
                'error_category' => 'permission_denied',
            ];
        }

        if ($status >= 500) {
            return [
                'success' => false,
                'message' => "Layanan Google Gemini sedang gangguan sementara (HTTP {$status}). Coba beberapa saat lagi.",
                'http_status' => $status,
                'error_category' => 'provider_unavailable',
            ];
        }

        return [
            'success' => false,
            'message' => "Pengujian Google Gemini gagal (HTTP {$status}): {$errorMessage}",
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
            return 'Koneksi ke Google Gemini timeout. Periksa koneksi internet server lalu coba lagi.';
        }

        if (str_contains($lower, 'could not resolve host') || str_contains($lower, 'name or service not known')) {
            return 'Server tidak dapat menjangkau Google Gemini (DNS gagal). Periksa koneksi internet server.';
        }

        if (str_contains($lower, 'ssl') || str_contains($lower, 'certificate')) {
            return 'Koneksi SSL ke Google Gemini gagal. Periksa sertifikat/konfigurasi jaringan server.';
        }

        return 'Koneksi ke Google Gemini gagal: '.$error;
    }
}
