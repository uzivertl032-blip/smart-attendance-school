<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiSetting;
use App\Services\Ai\AiManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AiConfigurationController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected AiManager $aiManager
    ) {}

    /**
     * Display the AI configuration page for Administrators.
     */
    public function index(): View
    {
        foreach (AiSetting::supportedProviders() as $provider) {
            AiSetting::firstOrCreate(
                ['provider' => $provider],
                [
                    'is_active' => false,
                    'model' => null,
                    'base_url' => AiSetting::defaultBaseUrl($provider),
                    'temperature' => 0.70,
                    'max_tokens' => 1024,
                ]
            );
        }

        // Repair legacy rows where a model or endpoint was stored under the wrong provider.
        foreach (AiSetting::all() as $setting) {
            $setting->normalizeConfiguration();
        }

        $settings = AiSetting::all()->keyBy('provider');
        $activeSetting = AiSetting::getActive();
        $supportedModels = AiSetting::supportedModels();
        $providerLabels = AiSetting::providerLabels();

        return view('admin.ai.index', compact('settings', 'activeSetting', 'supportedModels', 'providerLabels'));
    }

    /**
     * Update AI configuration and active provider.
     */
    public function update(Request $request): RedirectResponse
    {
        $provider = strtolower(trim((string) $request->input('active_provider')));

        if (! AiSetting::isSupportedProvider($provider)) {
            throw ValidationException::withMessages([
                'active_provider' => 'Provider AI tidak valid. Pilih Google Gemini, OpenAI, atau NVIDIA NIM.',
            ]);
        }

        $allowedModels = array_keys(AiSetting::supportedModels()[$provider]);

        $validated = $request->validate([
            'active_provider' => ['required', Rule::in(AiSetting::supportedProviders())],
            'model' => ['required', 'string', Rule::in($allowedModels)],
            'api_key' => ['nullable', 'string', 'max:500'],
            'base_url' => ['nullable', 'url', 'max:255'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:1.5'],
            'max_tokens' => ['required', 'integer', 'min:100', 'max:8192'],
            'remove_api_key' => ['nullable', 'boolean'],
        ], [
            'active_provider.required' => 'Pilih provider AI aktif.',
            'active_provider.in' => 'Provider AI tidak valid.',
            'model.required' => 'Pilih model AI untuk provider yang dipilih.',
            'model.in' => 'Model yang dipilih bukan model resmi untuk provider tersebut.',
            'base_url.url' => 'Format Base URL tidak valid (harus berupa URL valid e.g. https://...).',
            'temperature.required' => 'Parameter temperature wajib diisi.',
            'temperature.numeric' => 'Temperature harus berupa angka antara 0.0 dan 1.5.',
            'max_tokens.required' => 'Batas token wajib diisi.',
            'max_tokens.integer' => 'Batas token harus berupa bilangan bulat.',
        ]);

        $setting = AiSetting::firstOrCreate(
            ['provider' => $provider],
            [
                'is_active' => false,
                'model' => null,
                'base_url' => AiSetting::defaultBaseUrl($provider),
                'temperature' => 0.70,
                'max_tokens' => 1024,
            ]
        );

        $model = trim($validated['model']);
        $baseUrl = ! empty($validated['base_url'])
            ? rtrim(trim($validated['base_url']), '/')
            : AiSetting::defaultBaseUrl($provider);

        $this->ensureEndpointBelongsToProvider($provider, $baseUrl);

        $removeApiKey = $request->boolean('remove_api_key');
        $newApiKey = null;

        if (! $removeApiKey && ! empty($validated['api_key'])) {
            $newApiKey = AiSetting::sanitizeKey($validated['api_key']);

            if ($newApiKey === null) {
                throw ValidationException::withMessages([
                    'api_key' => 'API Key tidak valid atau kosong.',
                ]);
            }

            if (AiSetting::isPlaceholderKey($newApiKey)) {
                throw ValidationException::withMessages([
                    'api_key' => 'API Key masih berupa teks placeholder. Masukkan API Key asli dari provider.',
                ]);
            }

            $keyMismatch = AiSetting::keyProviderMismatch($provider, $newApiKey);

            if ($keyMismatch !== null) {
                throw ValidationException::withMessages([
                    'api_key' => $keyMismatch,
                ]);
            }
        }

        $configurationChanged = $setting->model !== $model
            || $setting->base_url !== $baseUrl
            || $removeApiKey
            || $newApiKey !== null;

        if ($removeApiKey) {
            $setting->api_key = null;
        } elseif ($newApiKey !== null) {
            $setting->api_key = $newApiKey;
        }

        $setting->model = $model;
        $setting->base_url = $baseUrl;
        $setting->temperature = (float) $validated['temperature'];
        $setting->max_tokens = (int) $validated['max_tokens'];

        // A previous connection test no longer describes the new configuration.
        if ($configurationChanged) {
            $setting->last_tested_at = null;
            $setting->last_test_status = null;
            $setting->last_test_message = null;
        }

        $setting->save();

        AiSetting::setActiveProvider($provider);

        $label = AiSetting::providerLabel($provider);

        return redirect()
            ->route('admin.ai-configuration.index')
            ->with('success', "Konfigurasi AI berhasil disimpan. Provider aktif: {$label} ({$model}). Klik Tes Koneksi untuk memverifikasi API Key dan model ini.");
    }

    /**
     * Test connection to a specific AI provider with a real API request.
     */
    public function testConnection(Request $request): JsonResponse
    {
        $provider = strtolower(trim((string) $request->input('provider')));

        if (! AiSetting::isSupportedProvider($provider)) {
            return response()->json([
                'success' => false,
                'status' => 'failed',
                'message' => 'Provider AI tidak valid. Pilih Google Gemini, OpenAI, atau NVIDIA NIM.',
                'http_status' => null,
                'error_category' => 'unsupported_provider',
            ], 422);
        }

        $allowedModels = array_keys(AiSetting::supportedModels()[$provider]);

        $validated = $request->validate([
            'provider' => ['required', Rule::in(AiSetting::supportedProviders())],
            'model' => ['required', 'string', Rule::in($allowedModels)],
            'api_key' => ['nullable', 'string', 'max:500'],
            'base_url' => ['nullable', 'url', 'max:255'],
        ], [
            'provider.required' => 'Provider AI wajib dipilih.',
            'provider.in' => 'Provider AI tidak valid.',
            'model.required' => 'Pilih model yang ingin diuji.',
            'model.in' => 'Model yang dipilih bukan model resmi untuk provider tersebut.',
            'base_url.url' => 'Format Base URL tidak valid (harus berupa URL valid e.g. https://...).',
        ]);

        $setting = AiSetting::firstOrCreate(
            ['provider' => $provider],
            [
                'is_active' => false,
                'model' => null,
                'base_url' => AiSetting::defaultBaseUrl($provider),
                'temperature' => 0.70,
                'max_tokens' => 1024,
            ]
        );

        $model = trim($validated['model']);
        $enteredKey = AiSetting::sanitizeKey($validated['api_key'] ?? null);
        $apiKey = $enteredKey ?? $setting->getEffectiveApiKey();
        $keySource = $enteredKey !== null ? 'request' : $setting->getKeySource();
        $baseUrl = ! empty($validated['base_url'])
            ? rtrim(trim($validated['base_url']), '/')
            : $setting->getEffectiveBaseUrl();

        if (AiSetting::baseUrlBelongsToAnotherProvider($provider, $baseUrl)) {
            $foreignProvider = (string) AiSetting::foreignBaseUrlProvider($provider, $baseUrl);
            $message = 'Endpoint yang dipilih adalah milik '.AiSetting::providerLabel($foreignProvider)
                .'. Gunakan endpoint resmi '.AiSetting::providerLabel($provider)
                .' ('.AiSetting::defaultBaseUrl($provider).').';

            Log::warning('AI connection test blocked: endpoint belongs to another provider.', [
                'provider' => $provider,
                'model' => $model,
                'base_url_host' => parse_url($baseUrl, PHP_URL_HOST) ?: null,
                'error_category' => 'endpoint_provider_mismatch',
            ]);

            $this->recordTestResult($setting, false, $message);

            return $this->testResponse(
                provider: $provider,
                model: $model,
                success: false,
                message: $message,
                keySource: $keySource,
                setting: $setting,
                httpStatus: null,
                errorCategory: 'endpoint_provider_mismatch',
                endpoint: $baseUrl,
            );
        }

        if ($apiKey === null) {
            $message = 'API Key '.AiSetting::providerLabel($provider).' belum diisi. Simpan API Key pada formulir konfigurasi lalu uji kembali.';

            $this->recordTestResult($setting, false, $message);

            return $this->testResponse(
                provider: $provider,
                model: $model,
                success: false,
                message: $message,
                keySource: $keySource,
                setting: $setting,
                httpStatus: null,
                errorCategory: 'missing_key',
                endpoint: $baseUrl,
            );
        }

        if (AiSetting::isPlaceholderKey($apiKey)) {
            $message = 'API Key masih berupa teks placeholder. Masukkan API Key asli dari provider '.AiSetting::providerLabel($provider).'.';

            $this->recordTestResult($setting, false, $message);

            return $this->testResponse(
                provider: $provider,
                model: $model,
                success: false,
                message: $message,
                keySource: $keySource,
                setting: $setting,
                httpStatus: null,
                errorCategory: 'placeholder_key',
                endpoint: $baseUrl,
            );
        }

        Log::info('AI connection test requested.', [
            'provider' => $provider,
            'model' => $model,
            'base_url_host' => parse_url($baseUrl, PHP_URL_HOST) ?: null,
            'key_source' => $keySource,
        ]);

        $result = $this->aiManager->testConnection(
            provider: $provider,
            apiKey: $apiKey,
            baseUrl: $baseUrl,
            model: $model
        );

        $httpStatus = $result['http_status'] ?? null;
        $errorCategory = $result['error_category'] ?? ($result['success'] ? 'success' : 'unknown_error');

        Log::info('AI connection test completed.', [
            'provider' => $provider,
            'model' => $model,
            'base_url_host' => parse_url($baseUrl, PHP_URL_HOST) ?: null,
            'key_source' => $keySource,
            'http_status' => $httpStatus,
            'error_category' => $errorCategory,
            'success' => $result['success'],
        ]);

        $this->recordTestResult($setting, $result['success'], $result['message']);

        return $this->testResponse(
            provider: $provider,
            model: $model,
            success: $result['success'],
            message: $result['message'],
            keySource: $keySource,
            setting: $setting,
            httpStatus: $httpStatus,
            errorCategory: $errorCategory,
            endpoint: $baseUrl,
        );
    }

    /**
     * Reject a base URL whose official host belongs to another provider.
     */
    protected function ensureEndpointBelongsToProvider(string $provider, string $baseUrl): void
    {
        if (! AiSetting::baseUrlBelongsToAnotherProvider($provider, $baseUrl)) {
            return;
        }

        $foreignProvider = (string) AiSetting::foreignBaseUrlProvider($provider, $baseUrl);

        throw ValidationException::withMessages([
            'base_url' => 'Endpoint tersebut adalah milik '.AiSetting::providerLabel($foreignProvider)
                .'. Gunakan endpoint resmi '.AiSetting::providerLabel($provider)
                .' ('.AiSetting::defaultBaseUrl($provider).').',
        ]);
    }

    /**
     * Persist the latest connection test result for the provider.
     */
    protected function recordTestResult(AiSetting $setting, bool $success, string $message): void
    {
        $setting->forceFill([
            'last_tested_at' => now(),
            'last_test_status' => $success ? 'success' : 'failed',
            'last_test_message' => $message,
        ])->save();
    }

    /**
     * Build the JSON response returned to the connection test UI.
     */
    protected function testResponse(
        string $provider,
        string $model,
        bool $success,
        string $message,
        string $keySource,
        AiSetting $setting,
        ?int $httpStatus = null,
        string $errorCategory = 'unknown_error',
        ?string $endpoint = null
    ): JsonResponse {
        return response()->json([
            'success' => $success,
            'status' => $success ? 'success' : 'failed',
            'message' => $message,
            'provider' => $provider,
            'provider_label' => AiSetting::providerLabel($provider),
            'model' => $model,
            'endpoint' => $endpoint ?? $setting->getEffectiveBaseUrl(),
            'endpoint_host' => parse_url($endpoint ?? $setting->getEffectiveBaseUrl(), PHP_URL_HOST) ?: null,
            'http_status' => $httpStatus,
            'error_category' => $errorCategory,
            'key_source' => $keySource,
            'masked_key' => $keySource === 'request' ? null : $setting->getMaskedApiKey(),
            'tested_at' => now()->toIso8601String(),
            'tested_at_formatted' => now()->translatedFormat('d M Y H:i:s'),
        ]);
    }
}
