@extends('layouts.admin')

@section('title', 'Konfigurasi Multi-Provider AI')

@section('content')
<div class="container-fluid p-0">
    {{-- Page Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="bi bi-robot text-primary me-2"></i>Konfigurasi Multi-Provider AI
            </h4>
            <p class="text-muted small mb-0">Kelola model AI, API key terenkripsi, dan integrasi Chatbot Cerdas SmartAttendance.</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex align-items-center gap-2">
            <span class="text-muted small">Provider Aktif:</span>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 fs-6 rounded-pill fw-semibold" id="activeProviderHeaderBadge">
                <i class="bi bi-cpu me-1"></i>
                @if($activeSetting && $activeSetting->hasConfiguredModel())
                    {{ $activeSetting->getProviderLabel() }} ({{ $activeSetting->model }})
                @elseif($activeSetting)
                    {{ $activeSetting->getProviderLabel() }} — Belum dikonfigurasi
                @else
                    Belum dikonfigurasi
                @endif
            </span>
        </div>
    </div>

    {{-- Provider Quick Selector Cards --}}
    <div class="row g-3 mb-4">
        {{-- Gemini Card --}}
        @php
            $geminiSetting = $settings->get('gemini');
            $isGeminiActive = ($activeSetting?->provider === 'gemini');
        @endphp
        <div class="col-12 col-md-4">
            <div class="card admin-card p-3 h-100 provider-card {{ $isGeminiActive ? 'border-primary shadow-sm bg-light-subtle' : '' }}"
                 role="button" onclick="selectProvider('gemini')">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 bg-primary text-white rounded-3 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="bi bi-google fs-5"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">Google Gemini</h6>
                            <span class="text-muted" style="font-size: 0.75rem;">Generative Language API</span>
                        </div>
                    </div>
                    @if($isGeminiActive)
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Aktif</span>
                    @else
                        <span class="badge bg-secondary-subtle text-secondary rounded-pill">Tersedia</span>
                    @endif
                </div>
                <div class="small text-muted mt-2">
                    <div>Model tersimpan: <strong>{{ $geminiSetting?->model ?: 'Belum dikonfigurasi' }}</strong></div>
                    <div>Status Kunci:
                        @if($geminiSetting?->hasApiKey())
                            @if($geminiSetting->getKeySource() === 'environment')
                                <span class="text-info"><i class="bi bi-file-earmark-code me-1"></i>.env ({{ $geminiSetting->getMaskedApiKey() }})</span>
                            @else
                                <span class="text-success"><i class="bi bi-shield-check me-1"></i>Tersimpan ({{ $geminiSetting->getMaskedApiKey() }})</span>
                            @endif
                        @else
                            <span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Belum diisi</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- OpenAI Card --}}
        @php
            $openaiSetting = $settings->get('openai');
            $isOpenAiActive = ($activeSetting?->provider === 'openai');
        @endphp
        <div class="col-12 col-md-4">
            <div class="card admin-card p-3 h-100 provider-card {{ $isOpenAiActive ? 'border-primary shadow-sm bg-light-subtle' : '' }}"
                 role="button" onclick="selectProvider('openai')">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 bg-dark text-white rounded-3 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="bi bi-cpu-fill fs-5"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">OpenAI</h6>
                            <span class="text-muted" style="font-size: 0.75rem;">GPT-4o & Mini API</span>
                        </div>
                    </div>
                    @if($isOpenAiActive)
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Aktif</span>
                    @else
                        <span class="badge bg-secondary-subtle text-secondary rounded-pill">Tersedia</span>
                    @endif
                </div>
                <div class="small text-muted mt-2">
                    <div>Model tersimpan: <strong>{{ $openaiSetting?->model ?: 'Belum dikonfigurasi' }}</strong></div>
                    <div>Status Kunci:
                        @if($openaiSetting?->hasApiKey())
                            <span class="text-success"><i class="bi bi-shield-check me-1"></i>Tersimpan ({{ $openaiSetting->getMaskedApiKey() }})</span>
                        @else
                            <span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Belum diisi</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- NVIDIA NIM Card --}}
        @php
            $nvidiaSetting = $settings->get('nvidia');
            $isNvidiaActive = ($activeSetting?->provider === 'nvidia');
        @endphp
        <div class="col-12 col-md-4">
            <div class="card admin-card p-3 h-100 provider-card {{ $isNvidiaActive ? 'border-primary shadow-sm bg-light-subtle' : '' }}"
                 role="button" onclick="selectProvider('nvidia')">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 text-white rounded-3 d-flex align-items-center justify-content-center" style="background-color: #76b900; width: 38px; height: 38px;">
                            <i class="bi bi-gpu-card fs-5"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">NVIDIA NIM</h6>
                            <span class="text-muted" style="font-size: 0.75rem;">NVIDIA Integrate API</span>
                        </div>
                    </div>
                    @if($isNvidiaActive)
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Aktif</span>
                    @else
                        <span class="badge bg-secondary-subtle text-secondary rounded-pill">Tersedia</span>
                    @endif
                </div>
                <div class="small text-muted mt-2">
                    <div>Model tersimpan: <strong>{{ $nvidiaSetting?->model ?: 'Belum dikonfigurasi' }}</strong></div>
                    <div>Status Kunci:
                        @if($nvidiaSetting?->hasApiKey())
                            <span class="text-success"><i class="bi bi-shield-check me-1"></i>Tersimpan ({{ $nvidiaSetting->getMaskedApiKey() }})</span>
                        @else
                            <span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Belum diisi</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Configuration Form Card --}}
    <div class="row">
        <div class="col-12 col-lg-8">
            <div class="card admin-card p-4 mb-4">
                <form id="aiConfigForm" action="{{ route('admin.ai-configuration.update') }}" method="POST">
                    @csrf

                    <h5 class="fw-bold text-dark mb-3">
                        <i class="bi bi-sliders2 me-2 text-primary"></i>Pengaturan Provider & Kredensial
                    </h5>

                    {{-- Active Provider Selector --}}
                    <div class="mb-3">
                        <label for="activeProviderSelect" class="form-label fw-semibold">
                            Provider AI Aktif <span class="text-danger">*</span>
                        </label>
                        <select name="active_provider" id="activeProviderSelect" class="form-select @error('active_provider') is-invalid @enderror" onchange="onProviderChanged(this.value)">
                            <option value="gemini" {{ old('active_provider', $activeSetting?->provider) === 'gemini' ? 'selected' : '' }}>Google Gemini</option>
                            <option value="openai" {{ old('active_provider', $activeSetting?->provider) === 'openai' ? 'selected' : '' }}>OpenAI</option>
                            <option value="nvidia" {{ old('active_provider', $activeSetting?->provider) === 'nvidia' ? 'selected' : '' }}>NVIDIA NIM</option>
                        </select>
                        @error('active_provider')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text text-muted">
                            Provider aktif akan digunakan oleh Chatbot Cerdas SmartAttendance untuk menjawab pertanyaan absensi dan izin/sakit siswa.
                        </div>
                    </div>

                    {{-- Model Selection --}}
                    <div class="mb-3">
                        <label for="modelSelect" class="form-label fw-semibold">
                            Model AI Aktif <span class="text-danger">*</span>
                        </label>
                        <select name="model" id="modelSelect" class="form-select @error('model') is-invalid @enderror" required>
                            <option value="">-- Pilih model --</option>
                            @foreach($supportedModels[$activeSetting?->provider ?? 'gemini'] ?? [] as $modelId => $modelLabel)
                                <option value="{{ $modelId }}" @selected(old('model', $activeSetting?->model) === $modelId)>{{ $modelLabel }}</option>
                            @endforeach
                        </select>
                        @error('model')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <div class="form-text text-muted">
                            Daftar ini hanya berisi model resmi milik provider yang dipilih. Model tidak dapat tercampur antar-provider.
                        </div>
                    </div>

                    {{-- API Key Input --}}
                    <div class="mb-3">
                        <label for="apiKeyInput" class="form-label fw-semibold">
                            API Key Provider <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted">
                                <i class="bi bi-key-fill"></i>
                            </span>
                            <input
                                type="password"
                                name="api_key"
                                id="apiKeyInput"
                                class="form-control @error('api_key') is-invalid @enderror"
                                placeholder="Masukkan API Key baru atau biarkan kosong jika tetap..."
                                autocomplete="new-password"
                            >
                            <button class="btn btn-outline-secondary" type="button" id="toggleApiKeyBtn" onclick="toggleApiKeyVisibility()" title="Tampilkan/Sembunyikan API Key">
                                <i class="bi bi-eye" id="toggleApiKeyIcon"></i>
                            </button>
                        </div>
                        @error('api_key')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror

                        {{-- Stored Key Status Notice --}}
                        <div class="d-flex align-items-center justify-content-between mt-2" id="keyStatusRow">
                            <div id="keyStatusBadge">
                                @if($activeSetting?->hasApiKey())
                                    @if($activeSetting->getKeySource() === 'environment')
                                        <span class="badge bg-info-subtle text-info border border-info-subtle">
                                            <i class="bi bi-file-earmark-code me-1"></i>Tersimpan di .env: {{ $activeSetting->getMaskedApiKey() }}
                                        </span>
                                    @else
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            <i class="bi bi-check-circle-fill me-1"></i>Tersimpan aman di database: {{ $activeSetting->getMaskedApiKey() }}
                                        </span>
                                    @endif
                                @else
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                        <i class="bi bi-exclamation-circle-fill me-1"></i>Belum ada API Key tersimpan
                                    </span>
                                @endif
                            </div>
                            <div class="form-check form-check-inline m-0">
                                <input class="form-check-input" type="checkbox" name="remove_api_key" id="removeApiKeyCheckbox" value="1">
                                <label class="form-check-label small text-danger" for="removeApiKeyCheckbox">
                                    Hapus API key tersimpan
                                </label>
                            </div>
                        </div>
                        <div class="form-text text-muted">
                            <i class="bi bi-lock-fill text-secondary me-1"></i>Kredensial dienkripsi secara otomatis menggunakan AES-256 sebelum disimpan ke database. Nilai key asli tidak pernah ditampilkan ulang ke browser.
                        </div>
                    </div>

                    {{-- Base URL / Endpoint --}}
                    <div class="mb-3">
                        <label for="baseUrlInput" class="form-label fw-semibold">
                            Base URL / Endpoint Kustom (Opsional)
                        </label>
                        <input
                            type="url"
                            name="base_url"
                            id="baseUrlInput"
                            class="form-control @error('base_url') is-invalid @enderror"
                            placeholder="https://..."
                            value="{{ old('base_url', $activeSetting?->base_url) }}"
                        >
                        @error('base_url')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text text-muted">
                            Biarkan kosong atau gunakan URL resmi provider. Ubah hanya jika Anda menggunakan reverse proxy, gateway enterprise, atau Azure/OpenAI kompatibel.
                        </div>
                    </div>

                    {{-- Model Parameters: Temperature & Max Tokens --}}
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-6">
                            <label for="temperatureInput" class="form-label fw-semibold">
                                Temperature: <span id="tempValueDisplay" class="fw-bold text-primary">{{ number_format($activeSetting?->temperature ?? 0.70, 2) }}</span>
                            </label>
                            <input
                                type="range"
                                class="form-range"
                                min="0.0"
                                max="1.5"
                                step="0.05"
                                id="tempRange"
                                value="{{ old('temperature', $activeSetting?->temperature ?? 0.70) }}"
                                oninput="syncTemp(this.value)"
                            >
                            <input
                                type="hidden"
                                name="temperature"
                                id="temperatureInput"
                                value="{{ old('temperature', $activeSetting?->temperature ?? 0.70) }}"
                            >
                            <div class="small text-muted">
                                0.0 = Jawaban kaku & faktual | 0.7 = Alami & terarah (Rekomendasi).
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="maxTokensInput" class="form-label fw-semibold">
                                Batas Token Maksimal
                            </label>
                            <input
                                type="number"
                                name="max_tokens"
                                id="maxTokensInput"
                                class="form-control @error('max_tokens') is-invalid @enderror"
                                min="100"
                                max="8192"
                                step="1"
                                value="{{ old('max_tokens', $activeSetting?->max_tokens ?? 1024) }}"
                            >
                            @error('max_tokens')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="small text-muted">
                                Standar: 1024 token untuk respons penjelasan chatbot.
                            </div>
                        </div>
                    </div>

                    {{-- Action Buttons --}}
                    <div class="d-flex flex-wrap align-items-center gap-2 pt-2 border-top">
                        <button type="submit" class="btn btn-primary px-4 rounded-3 fw-semibold shadow-sm">
                            <i class="bi bi-save2-fill me-2"></i>Simpan Konfigurasi
                        </button>
                        <button type="button" class="btn btn-outline-success px-4 rounded-3 fw-semibold" id="btnTestConnection" onclick="runConnectionTest()">
                            <i class="bi bi-broadcast me-2" id="testIcon"></i>Tes Koneksi
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Side Cards: Connection Test Results & Quick Guide --}}
        <div class="col-12 col-lg-4">
            {{-- Connection Test Result Card --}}
            <div class="card admin-card p-4 mb-4">
                <h6 class="fw-bold text-dark mb-3">
                    <i class="bi bi-activity text-success me-2"></i>Status Koneksi Terakhir
                </h6>
                <div id="testResultBox">
                    @if($activeSetting?->last_tested_at)
                        <div class="alert {{ $activeSetting->last_test_status === 'success' ? 'alert-success' : 'alert-danger' }} py-2 px-3 mb-2 small" role="alert">
                            <div class="fw-bold d-flex align-items-center gap-2">
                                <i class="bi {{ $activeSetting->last_test_status === 'success' ? 'bi-check-circle-fill' : 'bi-x-circle-fill' }}"></i>
                                <span>{{ $activeSetting->last_test_status === 'success' ? 'Koneksi Berhasil' : 'Koneksi Gagal' }}</span>
                            </div>
                            <div class="mt-1">{{ $activeSetting->last_test_message }}</div>
                        </div>
                        <div class="small text-muted">
                            <i class="bi bi-clock-history me-1"></i>Diuji pada: <strong>{{ $activeSetting->last_tested_at->translatedFormat('d M Y H:i:s') }}</strong>
                        </div>
                    @else
                        <div class="alert alert-light border text-muted py-2 px-3 mb-0 small">
                            <i class="bi bi-info-circle me-1"></i>Belum pernah dilakukan pengujian koneksi untuk provider aktif ini. Klik tombol <strong>Tes Koneksi</strong> untuk memverifikasi.
                        </div>
                    @endif
                </div>
            </div>

            {{-- Guide Card --}}
            <div class="card admin-card p-4">
                <h6 class="fw-bold text-dark mb-2">
                    <i class="bi bi-info-circle-fill text-primary me-2"></i>Panduan Kredensial AI
                </h6>
                <p class="small text-muted mb-3">
                    Dapatkan API key resmi dari masing-masing provider:
                </p>
                <div class="d-flex flex-column gap-2 small">
                    <div class="p-2 bg-light rounded-3 border">
                        <strong class="d-block text-dark">Google Gemini</strong>
                        <span>Dapatkan API key gratis di <a href="https://aistudio.google.com/" target="_blank" rel="noopener" class="text-primary text-decoration-none fw-semibold">Google AI Studio</a> (berawalan <code>AIza</code> atau <code>AQ.</code>).</span>
                    </div>
                    <div class="p-2 bg-light rounded-3 border">
                        <strong class="d-block text-dark">OpenAI</strong>
                        <span>Dapatkan API key di <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener" class="text-primary text-decoration-none fw-semibold">OpenAI Platform</a> (berawalan <code>sk-</code> atau <code>sk-proj-</code>).</span>
                    </div>
                    <div class="p-2 bg-light rounded-3 border">
                        <strong class="d-block text-dark">NVIDIA NIM</strong>
                        <span>Dapatkan API key di <a href="https://build.nvidia.com/" target="_blank" rel="noopener" class="text-primary text-decoration-none fw-semibold">NVIDIA Build</a> (berawalan <code>nvapi-</code>). Endpoint: <code>https://integrate.api.nvidia.com/v1</code>.</span>
                    </div>
                </div>

                <div class="alert alert-info-subtle border-info-subtle mt-3 mb-0 p-2 small">
                    <i class="bi bi-shield-lock-fill text-info me-1"></i>
                    <strong>Fitur Cerdas:</strong> Chatbot mengambil data absensi langsung dari database sebelum AI merangkai jawaban, mencegah kesalahan informasi.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Provider configurations dictionary
    const PROVIDER_DATA = {
        gemini: {
            name: 'Google Gemini',
            model: @json($settings->get('gemini')?->model),
            baseUrl: @json($settings->get('gemini')?->base_url ?? \App\Models\AiSetting::defaultBaseUrl('gemini')),
            hasKey: @json($settings->get('gemini')?->hasApiKey() ?? false),
            keySource: @json($settings->get('gemini')?->getKeySource() ?? 'none'),
            maskedKey: @json($settings->get('gemini')?->getMaskedApiKey() ?? ''),
            temperature: @json($settings->get('gemini')?->temperature ?? 0.70),
            maxTokens: @json($settings->get('gemini')?->max_tokens ?? 1024),
            models: @json($supportedModels['gemini'] ?? []),
            lastTestedAt: @json($settings->get('gemini')?->last_tested_at?->translatedFormat('d M Y H:i:s') ?? null),
            lastTestStatus: @json($settings->get('gemini')?->last_test_status ?? null),
            lastTestMessage: @json($settings->get('gemini')?->last_test_message ?? null),
        },
        openai: {
            name: 'OpenAI',
            model: @json($settings->get('openai')?->model),
            baseUrl: @json($settings->get('openai')?->base_url ?? \App\Models\AiSetting::defaultBaseUrl('openai')),
            hasKey: @json($settings->get('openai')?->hasApiKey() ?? false),
            keySource: @json($settings->get('openai')?->getKeySource() ?? 'none'),
            maskedKey: @json($settings->get('openai')?->getMaskedApiKey() ?? ''),
            temperature: @json($settings->get('openai')?->temperature ?? 0.70),
            maxTokens: @json($settings->get('openai')?->max_tokens ?? 1024),
            models: @json($supportedModels['openai'] ?? []),
            lastTestedAt: @json($settings->get('openai')?->last_tested_at?->translatedFormat('d M Y H:i:s') ?? null),
            lastTestStatus: @json($settings->get('openai')?->last_test_status ?? null),
            lastTestMessage: @json($settings->get('openai')?->last_test_message ?? null),
        },
        nvidia: {
            name: 'NVIDIA NIM',
            model: @json($settings->get('nvidia')?->model),
            baseUrl: @json($settings->get('nvidia')?->base_url ?? \App\Models\AiSetting::defaultBaseUrl('nvidia')),
            hasKey: @json($settings->get('nvidia')?->hasApiKey() ?? false),
            keySource: @json($settings->get('nvidia')?->getKeySource() ?? 'none'),
            maskedKey: @json($settings->get('nvidia')?->getMaskedApiKey() ?? ''),
            temperature: @json($settings->get('nvidia')?->temperature ?? 0.70),
            maxTokens: @json($settings->get('nvidia')?->max_tokens ?? 1024),
            models: @json($supportedModels['nvidia'] ?? []),
            lastTestedAt: @json($settings->get('nvidia')?->last_tested_at?->translatedFormat('d M Y H:i:s') ?? null),
            lastTestStatus: @json($settings->get('nvidia')?->last_test_status ?? null),
            lastTestMessage: @json($settings->get('nvidia')?->last_test_message ?? null),
        }
    };

    let connectionTestRequestId = 0;

    function selectProvider(provider) {
        const select = document.getElementById('activeProviderSelect');
        select.value = provider;
        onProviderChanged(provider);
    }

    function onProviderChanged(provider) {
        const data = PROVIDER_DATA[provider];
        if (!data) return;

        const modelSelect = document.getElementById('modelSelect');
        modelSelect.innerHTML = '';

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = '-- Pilih model --';
        modelSelect.appendChild(placeholder);

        let foundMatch = false;
        for (const [modelId, label] of Object.entries(data.models || {})) {
            const option = document.createElement('option');
            option.value = modelId;
            option.textContent = label;

            if (modelId === data.model) {
                option.selected = true;
                foundMatch = true;
            }

            modelSelect.appendChild(option);
        }

        if (!foundMatch) {
            modelSelect.value = '';
        }

        document.getElementById('baseUrlInput').value = data.baseUrl;
        document.getElementById('apiKeyInput').value = '';
        document.getElementById('apiKeyInput').type = 'password';
        document.getElementById('toggleApiKeyIcon').className = 'bi bi-eye';
        document.getElementById('removeApiKeyCheckbox').checked = false;

        updateKeyStatusBadge(data);
        syncTemp(data.temperature);
        document.getElementById('tempRange').value = data.temperature;
        document.getElementById('maxTokensInput').value = data.maxTokens;
        renderTestStatus(data.lastTestStatus, data.lastTestMessage, data.lastTestedAt);
    }

    function escapeHtml(value) {
        const replacements = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };

        return String(value ?? '').replace(/[&<>"']/g, (character) => replacements[character]);
    }

    function updateKeyStatusBadge(data) {
        const keyBadge = document.getElementById('keyStatusBadge');

        if (data.keySource === 'request') {
            keyBadge.innerHTML = `<span class="badge bg-info-subtle text-info border border-info-subtle"><i class="bi bi-hourglass-split me-1"></i>API Key baru diuji, belum disimpan</span>`;
            return;
        }

        if (data.hasKey) {
            if (data.keySource === 'environment') {
                keyBadge.innerHTML = `<span class="badge bg-info-subtle text-info border border-info-subtle"><i class="bi bi-file-earmark-code me-1"></i>Tersimpan di .env: ${escapeHtml(data.maskedKey)}</span>`;
            } else {
                keyBadge.innerHTML = `<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle-fill me-1"></i>Tersimpan aman di database: ${escapeHtml(data.maskedKey)}</span>`;
            }
        } else {
            keyBadge.innerHTML = `<span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="bi bi-exclamation-circle-fill me-1"></i>Belum ada API Key tersimpan</span>`;
        }
    }

    function syncTemp(val) {
        const num = parseFloat(val).toFixed(2);
        document.getElementById('tempValueDisplay').textContent = num;
        document.getElementById('temperatureInput').value = num;
    }

    function toggleApiKeyVisibility() {
        const input = document.getElementById('apiKeyInput');
        const icon = document.getElementById('toggleApiKeyIcon');

        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'bi bi-eye';
        }
    }

    function renderTestStatus(status, message, testedAt, meta) {
        const box = document.getElementById('testResultBox');

        if (!status) {
            box.innerHTML = `
                <div class="alert alert-light border text-muted py-2 px-3 mb-0 small">
                    <i class="bi bi-info-circle me-1"></i>Belum pernah dilakukan pengujian koneksi untuk provider ini. Klik tombol <strong>Tes Koneksi</strong> untuk memverifikasi.
                </div>
            `;
            return;
        }

        const isSuccess = status === 'success';
        const alertClass = isSuccess ? 'alert-success' : 'alert-danger';
        const iconClass = isSuccess ? 'bi-check-circle-fill' : 'bi-x-circle-fill';
        const titleText = isSuccess ? 'Koneksi Berhasil' : 'Koneksi Gagal';

        let metaHtml = '';

        if (meta) {
            const httpStatus = meta.httpStatus === null || meta.httpStatus === undefined
                ? 'Tidak ada respons HTTP'
                : `HTTP ${meta.httpStatus}`;

            metaHtml = `
                <div class="small text-muted mt-2 pt-2 border-top">
                    <div><i class="bi bi-cpu me-1"></i>Provider: <strong>${escapeHtml(meta.providerLabel || meta.provider || '-')}</strong> &middot; Model: <code>${escapeHtml(meta.model || '-')}</code></div>
                    <div><i class="bi bi-wifi me-1"></i>Status: <strong>${escapeHtml(httpStatus)}</strong> &middot; Kategori: <code>${escapeHtml(meta.errorCategory || '-')}</code></div>
                    <div><i class="bi bi-link-45deg me-1"></i>Endpoint: <code>${escapeHtml(meta.endpointHost || '-')}</code></div>
                </div>
            `;
        }

        box.innerHTML = `
            <div class="alert ${alertClass} py-2 px-3 mb-2 small" role="alert">
                <div class="fw-bold d-flex align-items-center gap-2">
                    <i class="bi ${iconClass}"></i>
                    <span>${titleText}</span>
                </div>
                <div class="mt-1" style="word-break: break-word;">${escapeHtml(message)}</div>
                ${metaHtml}
            </div>
            <div class="small text-muted">
                <i class="bi bi-clock-history me-1"></i>Diuji pada: <strong>${escapeHtml(testedAt || 'Baru saja')}</strong>
            </div>
        `;
    }

    async function runConnectionTest() {
        const btn = document.getElementById('btnTestConnection');
        const icon = document.getElementById('testIcon');
        const provider = document.getElementById('activeProviderSelect').value;
        const model = document.getElementById('modelSelect').value;
        const apiKey = document.getElementById('apiKeyInput').value;
        const baseUrl = document.getElementById('baseUrlInput').value;

        if (!model) {
            renderTestStatus('failed', 'Pilih model terlebih dahulu sebelum menguji koneksi.', 'Sekarang');
            return;
        }

        const requestId = ++connectionTestRequestId;

        btn.disabled = true;
        icon.className = 'spinner-border spinner-border-sm me-2';

        try {
            const response = await fetch("{{ route('admin.ai-configuration.test') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    provider: provider,
                    model: model,
                    api_key: apiKey,
                    base_url: baseUrl
                })
            });

            let data = {};

            try {
                data = await response.json();
            } catch (parseError) {
                data = {};
            }

            // Ignore a response that belongs to a superseded test request.
            if (requestId !== connectionTestRequestId) {
                return;
            }

            if (data.errors) {
                const firstError = Object.values(data.errors).flat()[0];

                if (firstError) {
                    data.message = firstError;
                }
            }

            if (!data.message) {
                data.message = response.ok
                    ? 'Server tidak mengembalikan pesan pengujian.'
                    : `Pengujian gagal dengan status HTTP ${response.status}.`;
            }

            const actualProvider = data.provider || provider;
            const actualModel = data.model || model;
            const actualStatus = data.status || (data.success ? 'success' : 'failed');
            const testedAt = data.tested_at_formatted || data.tested_at || 'Baru saja';
            const meta = {
                providerLabel: data.provider_label || (PROVIDER_DATA[actualProvider] ? PROVIDER_DATA[actualProvider].name : actualProvider),
                model: actualModel,
                httpStatus: data.http_status,
                errorCategory: data.error_category || (data.success ? 'success' : 'unknown_error'),
                endpointHost: data.endpoint_host || baseUrl
            };

            if (PROVIDER_DATA[actualProvider]) {
                PROVIDER_DATA[actualProvider].lastTestStatus = actualStatus;
                PROVIDER_DATA[actualProvider].lastTestMessage = data.message;
                PROVIDER_DATA[actualProvider].lastTestedAt = testedAt;

                if (data.key_source === 'database' || data.key_source === 'environment') {
                    PROVIDER_DATA[actualProvider].hasKey = true;
                    PROVIDER_DATA[actualProvider].maskedKey = data.masked_key || PROVIDER_DATA[actualProvider].maskedKey;
                    PROVIDER_DATA[actualProvider].keySource = data.key_source;
                    updateKeyStatusBadge(PROVIDER_DATA[actualProvider]);
                } else if (data.key_source === 'request') {
                    updateKeyStatusBadge({ keySource: 'request' });
                }
            }

            // Never paint an old provider/model result over the currently selected one.
            const currentProvider = document.getElementById('activeProviderSelect').value;
            const currentModel = document.getElementById('modelSelect').value;

            if (currentProvider === provider && currentModel === model) {
                renderTestStatus(actualStatus, data.message, testedAt, meta);
            }
        } catch (err) {
            if (requestId !== connectionTestRequestId) {
                return;
            }

            renderTestStatus('failed', 'Terjadi kesalahan jaringan saat menguji koneksi: ' + err.message, 'Sekarang');
        } finally {
            if (requestId === connectionTestRequestId) {
                btn.disabled = false;
                icon.className = 'bi bi-broadcast me-2';
            }
        }
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function () {
        const initialProvider = document.getElementById('activeProviderSelect').value || 'gemini';
        onProviderChanged(initialProvider);
    });
</script>
@endpush
