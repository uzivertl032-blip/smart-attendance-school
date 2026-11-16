<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AiSetting extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ai_settings';

    /**
     * Providers supported by the application.
     *
     * @var list<string>
     */
    public const PROVIDERS = ['gemini', 'openai', 'nvidia'];

    /**
     * Endpoint hosts of retired providers that must never receive another provider's key.
     *
     * @var list<string>
     */
    protected const RETIRED_ENDPOINT_HOSTS = ['api.anthropic.com'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'provider',
        'is_active',
        'model',
        'api_key',
        'base_url',
        'temperature',
        'max_tokens',
        'last_tested_at',
        'last_test_status',
        'last_test_message',
    ];

    /**
     * The attributes that should be hidden for serialization to ensure credentials are never leaked.
     *
     * @var list<string>
     */
    protected $hidden = [
        'api_key',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'api_key' => 'encrypted',
            'temperature' => 'float',
            'max_tokens' => 'integer',
            'last_tested_at' => 'datetime',
        ];
    }

    /**
     * Human readable provider labels.
     *
     * @return array<string, string>
     */
    public static function providerLabels(): array
    {
        return [
            'gemini' => 'Google Gemini',
            'openai' => 'OpenAI',
            'nvidia' => 'NVIDIA NIM',
            'retired' => 'provider lain yang sudah tidak didukung',
        ];
    }

    /**
     * Get the supported provider keys.
     *
     * @return list<string>
     */
    public static function supportedProviders(): array
    {
        return self::PROVIDERS;
    }

    /**
     * Determine whether a provider key is supported.
     */
    public static function isSupportedProvider(?string $provider): bool
    {
        return $provider !== null && in_array($provider, self::PROVIDERS, true);
    }

    /**
     * Get a human readable provider label.
     */
    public static function providerLabel(?string $provider): string
    {
        return self::providerLabels()[$provider] ?? 'Belum dikonfigurasi';
    }

    /**
     * Get the human readable label for this setting's provider.
     */
    public function getProviderLabel(): string
    {
        return self::providerLabel($this->provider);
    }

    /**
     * Sanitize an API key by trimming and removing outer quotes.
     */
    public static function sanitizeKey(?string $key): ?string
    {
        if ($key === null) {
            return null;
        }

        $trimmed = trim($key);
        if ($trimmed === '') {
            return null;
        }

        if (
            (str_starts_with($trimmed, '"') && str_ends_with($trimmed, '"')) ||
            (str_starts_with($trimmed, "'") && str_ends_with($trimmed, "'"))
        ) {
            $trimmed = trim(substr($trimmed, 1, -1));
        }

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * Check whether a key is a known placeholder rather than a real credential.
     */
    public static function isPlaceholderKey(?string $key): bool
    {
        $sanitized = self::sanitizeKey($key);
        if ($sanitized === null) {
            return false;
        }

        return in_array(strtolower($sanitized), [
            'your_gemini_api_key_here',
            'your_api_key_here',
            'your_nous_api_key_here',
            'placeholder',
            'ganti_dengan_api_key_anda',
            'masukkan_api_key_resmi_anda_di_sini',
            'your_gemini_api_key',
            'api_key_here',
        ], true);
    }

    /**
     * Detect which provider a recognizable API key format belongs to.
     *
     * Only clearly identifiable prefixes are classified. Unknown or newer key
     * formats return null so they are never rejected preemptively; the provider
     * API remains the authority on validity.
     */
    public static function detectKeyProvider(?string $key): ?string
    {
        $sanitized = self::sanitizeKey($key);

        if ($sanitized === null) {
            return null;
        }

        if (
            str_starts_with($sanitized, 'AIza')
            || str_starts_with($sanitized, 'AQ.')
        ) {
            return 'gemini';
        }

        if (
            str_starts_with($sanitized, 'sk-proj-')
            || str_starts_with($sanitized, 'sk-svcacct-')
            || str_starts_with($sanitized, 'sk-')
        ) {
            return 'openai';
        }

        if (str_starts_with($sanitized, 'nvapi-')) {
            return 'nvidia';
        }

        return null;
    }

    /**
     * Return a clear message when a key clearly belongs to another provider.
     *
     * Returns null when the key is compatible, unknown, or unclassifiable.
     */
    public static function keyProviderMismatch(string $provider, ?string $key): ?string
    {
        $detected = self::detectKeyProvider($key);

        if ($detected === null || $detected === $provider) {
            return null;
        }

        $currentLabel = self::providerLabel($provider);

        if ($detected === 'nvidia') {
            return "API Key yang dimasukkan adalah kunci NVIDIA (berawalan 'nvapi-'), bukan API Key {$currentLabel}. Gunakan API Key resmi dari {$currentLabel}.";
        }

        if (! self::isSupportedProvider($detected)) {
            return "API Key yang dimasukkan bukan API Key {$currentLabel}.";
        }

        $detectedLabel = self::providerLabel($detected);

        return "API Key yang dimasukkan tampaknya milik {$detectedLabel}, bukan {$currentLabel}. Gunakan API Key resmi dari {$currentLabel}.";
    }

    /**
     * Validate whether an API key has a plausible format for the given provider.
     *
     * This is only a light plausibility check used for UI hints. Real validity is
     * always determined by a request to the provider API.
     */
    public static function isValidKeyFormat(string $provider, ?string $key): bool
    {
        $sanitized = self::sanitizeKey($key);
        if ($sanitized === null || self::isPlaceholderKey($sanitized)) {
            return false;
        }

        return self::keyProviderMismatch($provider, $sanitized) === null
            && strlen($sanitized) >= 10;
    }

    /**
     * Get effective API key from the encrypted database value or environment.
     *
     * A stored key is returned as-is because only the provider can decide whether
     * it is valid. Format heuristics must never reject a legitimate new key.
     */
    public function getEffectiveApiKey(): ?string
    {
        $dbKey = self::sanitizeKey((string) $this->api_key);
        if ($dbKey !== null) {
            return $dbKey;
        }

        $envKey = self::sanitizeKey(
            (string) config(
                "services.{$this->provider}.api_key",
                env(strtoupper($this->provider).'_API_KEY')
            )
        );

        if ($envKey !== null) {
            return $envKey;
        }

        return null;
    }

    /**
     * Get key source ('database', 'environment', or 'none').
     */
    public function getKeySource(): string
    {
        if (self::sanitizeKey((string) $this->api_key) !== null) {
            return 'database';
        }

        $envKey = self::sanitizeKey(
            (string) config(
                "services.{$this->provider}.api_key",
                env(strtoupper($this->provider).'_API_KEY')
            )
        );

        return $envKey !== null ? 'environment' : 'none';
    }

    /**
     * Check if provider has an API key configured (database or environment).
     */
    public function hasApiKey(): bool
    {
        return $this->getEffectiveApiKey() !== null;
    }

    /**
     * Get safe masked preview of API key.
     */
    public function getMaskedApiKey(): ?string
    {
        $key = $this->getEffectiveApiKey();
        if ($key === null) {
            return null;
        }

        if (strlen($key) <= 8) {
            return '••••••••';
        }

        return '••••••••'.substr($key, -4);
    }

    /**
     * Determine whether this provider has a configured, provider-compatible model.
     */
    public function hasConfiguredModel(): bool
    {
        return self::isModelSupported($this->provider, $this->model);
    }

    /**
     * Get the configured model, or null when it is missing or belongs to another provider.
     */
    public function getConfiguredModel(): ?string
    {
        $model = $this->model !== null ? trim((string) $this->model) : null;

        if ($model === null || $model === '') {
            return null;
        }

        return self::isModelSupported($this->provider, $model) ? $model : null;
    }

    /**
     * Get effective base URL.
     */
    public function getEffectiveBaseUrl(): string
    {
        $baseUrl = trim((string) $this->base_url);

        if ($baseUrl !== '' && filter_var($baseUrl, FILTER_VALIDATE_URL) !== false) {
            return rtrim($baseUrl, '/');
        }

        return self::defaultBaseUrl($this->provider);
    }

    /**
     * Repair a stored configuration whose model or endpoint belongs to another provider.
     *
     * Returns true when the database row was changed.
     */
    public function normalizeConfiguration(): bool
    {
        $originalModel = $this->model;
        $originalBaseUrl = $this->base_url;
        $originalTestedAt = $this->last_tested_at;
        $originalTestStatus = $this->last_test_status;
        $originalTestMessage = $this->last_test_message;

        $model = $this->model !== null ? trim((string) $this->model) : null;

        if ($model === '' || ($model !== null && ! self::isModelSupported($this->provider, $model))) {
            $model = null;
        }

        $baseUrl = trim((string) $this->base_url);

        if ($baseUrl === '' || filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            $baseUrl = self::defaultBaseUrl($this->provider);
        } elseif (self::baseUrlBelongsToAnotherProvider($this->provider, $baseUrl)) {
            $baseUrl = self::defaultBaseUrl($this->provider);
        }

        $this->model = $model;
        $this->base_url = $baseUrl;

        if ($model === null) {
            $this->last_tested_at = null;
            $this->last_test_status = null;
            $this->last_test_message = null;
        }

        $changed = $this->model !== $originalModel
            || $this->base_url !== $originalBaseUrl
            || $this->last_tested_at !== $originalTestedAt
            || $this->last_test_status !== $originalTestStatus
            || $this->last_test_message !== $originalTestMessage;

        if ($changed) {
            $this->save();

            Log::info('Konfigurasi AI provider dinormalisasi agar tidak mencampur model antar-provider.', [
                'provider' => $this->provider,
                'model' => $this->model,
                'base_url' => $this->base_url,
            ]);
        }

        return $changed;
    }

    /**
     * Get current active provider setting without any silent fallback.
     */
    public static function getActive(): ?self
    {
        return self::where('is_active', true)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Set a specific provider as active and deactivate all others.
     */
    public static function setActiveProvider(string $provider): ?self
    {
        if (! self::isSupportedProvider($provider)) {
            throw new \InvalidArgumentException("Provider AI '{$provider}' tidak didukung.");
        }

        return DB::transaction(function () use ($provider) {
            self::query()
                ->where('provider', '!=', $provider)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $setting = self::where('provider', $provider)->first();

            if ($setting) {
                $setting->is_active = true;
                $setting->save();
            }

            return $setting;
        });
    }

    /**
     * Default model per provider.
     */
    public static function defaultModel(string $provider): string
    {
        return match ($provider) {
            'gemini' => 'gemini-3.6-flash',
            'openai' => 'gpt-6-luna',
            'nvidia' => 'nvidia/nemotron-3.5-bolt-e4b',
            default => '',
        };
    }

    /**
     * Get supported AI models per provider.
     *
     * Only models owned by the provider are listed so the UI can never mix them.
     *
     * @return array<string, array<string, string>>
     */
    public static function supportedModels(): array
    {
        return [
            'gemini' => [
                'gemini-3.6-flash' => 'Gemini 3.6 Flash (Rekomendasi)',
                'gemini-3.5-flash-lite' => 'Gemini 3.5 Flash-Lite (Hemat Biaya)',
                'gemini-3.1-flash-lite' => 'Gemini 3.1 Flash-Lite (Ringan)',
            ],
            'openai' => [
                'gpt-6-luna' => 'GPT-6 Luna (Hemat Biaya - Rekomendasi)',
            ],
            'nvidia' => [
                'nvidia/nemotron-3.5-bolt-e4b' => 'Nemotron 3.5 Bolt E4B (Cepat - Rekomendasi)',
                'nvidia/nemotron-3.5-lightning-30b-a3b' => 'Nemotron 3.5 Lightning 30B (Reasoning)',
                'minimaxai/minimax-m3.1-flash' => 'MiniMax M3.1 Flash (General)',
            ],
        ];
    }

    /**
     * Determine whether a model belongs to the given provider.
     */
    public static function isModelSupported(?string $provider, ?string $model): bool
    {
        if (! self::isSupportedProvider($provider)) {
            return false;
        }

        if ($model === null || trim($model) === '') {
            return false;
        }

        return array_key_exists(trim($model), self::supportedModels()[$provider]);
    }

    /**
     * Get default base URL for provider.
     */
    public static function defaultBaseUrl(string $provider): string
    {
        return match ($provider) {
            'gemini' => 'https://generativelanguage.googleapis.com/v1beta',
            'openai' => 'https://api.openai.com/v1',
            'nvidia' => 'https://integrate.api.nvidia.com/v1',
            default => '',
        };
    }

    /**
     * Determine whether an endpoint host belongs to a different supported provider.
     */
    public static function baseUrlBelongsToAnotherProvider(string $provider, string $baseUrl): bool
    {
        return self::foreignBaseUrlProvider($provider, $baseUrl) !== null;
    }

    /**
     * Get the supported provider that owns an official endpoint host, if different.
     */
    public static function foreignBaseUrlProvider(string $provider, string $baseUrl): ?string
    {
        $host = parse_url($baseUrl, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        $officialHosts = [
            'gemini' => 'generativelanguage.googleapis.com',
            'openai' => 'api.openai.com',
            'nvidia' => 'integrate.api.nvidia.com',
        ];

        foreach ($officialHosts as $otherProvider => $officialHost) {
            if ($otherProvider !== $provider && $host === $officialHost) {
                return $otherProvider;
            }
        }

        if (in_array(strtolower($host), self::RETIRED_ENDPOINT_HOSTS, true)) {
            return 'retired';
        }

        return null;
    }
}
