<?php

namespace App\Services\Ai;

use App\Models\AiSetting;
use App\Services\Ai\Contracts\AiDriverInterface;
use App\Services\Ai\Drivers\GeminiDriver;
use App\Services\Ai\Drivers\NvidiaDriver;
use App\Services\Ai\Drivers\OpenAiDriver;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiManager
{
    /**
     * Map of driver instances keyed by provider name.
     *
     * @var array<string, AiDriverInterface>
     */
    protected array $drivers = [];

    /**
     * Get or create a driver instance for the given provider.
     *
     * @throws \InvalidArgumentException
     */
    public function driver(string $provider): AiDriverInterface
    {
        $normalized = strtolower(trim($provider));

        if (! isset($this->drivers[$normalized])) {
            $this->drivers[$normalized] = match ($normalized) {
                'gemini' => new GeminiDriver,
                'openai' => new OpenAiDriver,
                'nvidia' => new NvidiaDriver,
                default => throw new \InvalidArgumentException("Provider AI '{$provider}' tidak didukung."),
            };
        }

        return $this->drivers[$normalized];
    }

    /**
     * Test connection for a given provider configuration with a real API request.
     *
     * @return array{success: bool, message: string, http_status?: int|null, error_category?: string}
     */
    public function testConnection(string $provider, string $apiKey, ?string $baseUrl, string $model): array
    {
        $provider = strtolower(trim($provider));
        $model = trim($model);

        if (! AiSetting::isSupportedProvider($provider)) {
            return [
                'success' => false,
                'message' => "Provider '{$provider}' tidak didukung. Gunakan Google Gemini, OpenAI, atau NVIDIA NIM.",
                'http_status' => null,
                'error_category' => 'unsupported_provider',
            ];
        }

        if (! AiSetting::isModelSupported($provider, $model)) {
            return [
                'success' => false,
                'message' => "Model '{$model}' bukan model resmi untuk provider ".AiSetting::providerLabel($provider).'. Pilih model dari daftar provider tersebut.',
                'http_status' => null,
                'error_category' => 'unsupported_model',
            ];
        }

        $cleanKey = AiSetting::sanitizeKey($apiKey);

        if ($cleanKey === null) {
            return [
                'success' => false,
                'message' => 'API Key '.AiSetting::providerLabel($provider).' belum diisi.',
                'http_status' => null,
                'error_category' => 'missing_key',
            ];
        }

        if (AiSetting::isPlaceholderKey($cleanKey)) {
            return [
                'success' => false,
                'message' => 'API Key masih berupa teks placeholder. Masukkan API Key asli dari provider '.AiSetting::providerLabel($provider).'.',
                'http_status' => null,
                'error_category' => 'placeholder_key',
            ];
        }

        $keyMismatch = AiSetting::keyProviderMismatch($provider, $cleanKey);

        if ($keyMismatch !== null) {
            Log::warning('AI connection test blocked: API key belongs to another provider.', [
                'provider' => $provider,
                'model' => $model,
                'detected_key_provider' => AiSetting::detectKeyProvider($cleanKey),
                'http_status' => null,
                'error_category' => 'key_provider_mismatch',
            ]);

            return [
                'success' => false,
                'message' => $keyMismatch,
                'http_status' => null,
                'error_category' => 'key_provider_mismatch',
            ];
        }

        $effectiveBaseUrl = $baseUrl ?: AiSetting::defaultBaseUrl($provider);
        $baseHost = parse_url($effectiveBaseUrl, PHP_URL_HOST) ?: null;

        Log::info('AI connection test started.', [
            'provider' => $provider,
            'model' => $model,
            'base_url_host' => $baseHost,
        ]);

        try {
            $result = $this->driver($provider)->testConnection($cleanKey, $baseUrl, $model);
        } catch (Throwable $e) {
            $message = self::redact($e->getMessage(), $cleanKey);

            Log::warning('AI connection test failed unexpectedly.', [
                'provider' => $provider,
                'model' => $model,
                'base_url_host' => $baseHost,
                'http_status' => null,
                'error_category' => 'network_error',
                'error' => $message,
            ]);

            return [
                'success' => false,
                'message' => 'Gagal menguji koneksi ke '.AiSetting::providerLabel($provider).': '.$message,
                'http_status' => null,
                'error_category' => 'network_error',
            ];
        }

        Log::info('AI connection test finished.', [
            'provider' => $provider,
            'model' => $model,
            'base_url_host' => $baseHost,
            'http_status' => $result['http_status'] ?? null,
            'error_category' => $result['error_category'] ?? ($result['success'] ? 'success' : 'unknown_error'),
            'success' => $result['success'],
        ]);

        return $result;
    }

    /**
     * Generate an AI reply using the active provider configuration.
     *
     * There is deliberately no provider fallback: the active provider chosen by
     * the administrator is the only provider used.
     *
     * @throws AiException when the active provider is missing, incomplete, or fails
     */
    public function generateReply(string $systemPrompt, string $userMessage, ?AiSetting $setting = null): string
    {
        $config = $setting ?? AiSetting::getActive();

        if (! $config) {
            throw new AiException(
                'Belum ada provider AI aktif. Buka halaman Konfigurasi Multi-Provider AI dan aktifkan salah satu provider.'
            );
        }

        $provider = (string) $config->provider;
        $providerLabel = AiSetting::providerLabel($provider);

        if (! AiSetting::isSupportedProvider($provider)) {
            throw new AiException(
                "Provider aktif '{$provider}' tidak didukung. Pilih Google Gemini, OpenAI, atau NVIDIA NIM pada halaman konfigurasi."
            );
        }

        $model = $config->getConfiguredModel();

        if ($model === null) {
            throw new AiException(
                "Model AI untuk provider {$providerLabel} belum dikonfigurasi atau tidak sesuai provider. Pilih model resmi pada halaman Konfigurasi Multi-Provider AI.",
                $provider
            );
        }

        $apiKey = $config->getEffectiveApiKey();

        if ($apiKey === null) {
            throw new AiException(
                "API Key untuk provider {$providerLabel} belum diisi. Simpan API Key pada halaman Konfigurasi Multi-Provider AI.",
                $provider,
                $model
            );
        }

        $keyMismatch = AiSetting::keyProviderMismatch($provider, $apiKey);

        if ($keyMismatch !== null) {
            Log::warning('AI generation blocked: API key belongs to another provider.', [
                'provider' => $provider,
                'model' => $model,
                'detected_key_provider' => AiSetting::detectKeyProvider($apiKey),
                'error_category' => 'key_provider_mismatch',
            ]);

            throw new AiException(
                $keyMismatch,
                $provider,
                $model
            );
        }

        try {
            $reply = $this->driver($provider)->generate(
                apiKey: $apiKey,
                baseUrl: $config->getEffectiveBaseUrl(),
                model: $model,
                systemPrompt: $systemPrompt,
                userMessage: $userMessage,
                temperature: (float) $config->temperature,
                maxTokens: (int) $config->max_tokens
            );
        } catch (Throwable $e) {
            $message = self::redact($e->getMessage(), $apiKey);

            Log::warning('AI generation failed.', [
                'provider' => $provider,
                'model' => $model,
                'error' => $message,
            ]);

            throw new AiException(
                "Provider {$providerLabel} ({$model}) gagal memproses pertanyaan: {$message}",
                $provider,
                $model,
                $e
            );
        }

        $reply = trim($reply);

        if ($reply === '') {
            throw new AiException(
                "Provider {$providerLabel} ({$model}) mengembalikan jawaban kosong.",
                $provider,
                $model
            );
        }

        return $reply;
    }

    /**
     * Remove an API key from a message before it is logged or returned to the browser.
     */
    public static function redact(string $message, ?string $apiKey): string
    {
        if ($apiKey === null || $apiKey === '') {
            return $message;
        }

        return str_replace([$apiKey, urlencode($apiKey)], '[REDACTED]', $message);
    }
}
