@php
    use Illuminate\Support\Facades\Auth;

    $currentUser = Auth::user();
    $isAuthenticated = $currentUser !== null;
    $userRole = $currentUser?->role ?? 'guest';
    $userName = trim((string) ($currentUser?->name ?? ''));
    $displayName = $userName !== '' ? $userName : 'Pengguna';

    $roleLabels = [
        'admin'   => 'Administrator',
        'teacher' => 'Guru',
        'student' => 'Siswa',
    ];
    $roleLabel = $roleLabels[$userRole] ?? null;

    // --- Initial greeting ---
    $initialGreetings = [
        'admin' => $userName !== ''
            ? "Halo **{$userName}**! Saya **SmartBot**, asisten cerdas SmartAttendance.\nSaya dapat membantu statistik siswa, guru, kehadiran hari ini, siapa yang belum absen, alasan izin/sakit siswa, serta panduan menu admin."
            : "Halo Administrator! Saya **SmartBot**, asisten cerdas SmartAttendance. Tanyakan statistik kehadiran atau panduan sistem.",
        'teacher' => $userName !== ''
            ? "Halo Bapak/Ibu **{$userName}**! Saya **SmartBot**, asisten portal guru.\nTanyakan kehadiran siswa, permohonan izin/sakit, alasan absen siswa, atau panduan portal guru."
            : "Halo Guru! Saya **SmartBot**, asisten portal guru SmartAttendance. Ada yang ingin Anda tanyakan?",
        'student' => $userName !== ''
            ? "Halo **{$userName}**! Saya **SmartBot**, asisten presensimu.\nTanyakan status absensi hari ini, kehadiran, keterlambatan, atau izin/sakitmu."
            : "Halo Siswa! Saya **SmartBot**, asisten presensi SmartAttendance. Ada yang bisa saya bantu?",
        'guest' => "Halo! Saya **SmartBot**, asisten virtual **SmartAttendance**.\nTanyakan cara absensi, jam masuk, cara izin, atau panduan menggunakan sistem ini.",
    ];
    $initialGreeting = $initialGreetings[$userRole]
        ?? "Halo **{$displayName}**! Saya SmartBot SmartAttendance. Ada yang bisa saya bantu?";

    // --- Suggestion chips per role ---
    $allChips = [
        'admin' => [
            ['icon' => 'bi-person-badge',    'label' => 'Siapa Saya?',         'prompt' => 'Siapa saya?'],
            ['icon' => 'bi-mortarboard',     'label' => 'Total Siswa',         'prompt' => 'Berapa total siswa terdaftar?'],
            ['icon' => 'bi-person-video3',   'label' => 'Total Guru',          'prompt' => 'Berapa total guru?'],
            ['icon' => 'bi-check2-circle',   'label' => 'Hadir Hari Ini',      'prompt' => 'Berapa yang hadir hari ini?'],
            ['icon' => 'bi-clock-history',   'label' => 'Terlambat',           'prompt' => 'Berapa yang terlambat hari ini?'],
            ['icon' => 'bi-person-x',        'label' => 'Belum Absen',         'prompt' => 'Siapa yang belum absen hari ini?'],
            ['icon' => 'bi-envelope-paper',  'label' => 'Siswa Izin Hari Ini', 'prompt' => 'Siapa saja siswa yang izin atau sakit hari ini?'],
            ['icon' => 'bi-compass',         'label' => 'Panduan Admin',       'prompt' => 'Panduan menu administrator'],
        ],
        'teacher' => [
            ['icon' => 'bi-person-badge',    'label' => 'Siapa Saya?',         'prompt' => 'Siapa saya?'],
            ['icon' => 'bi-mortarboard',     'label' => 'Total Siswa',         'prompt' => 'Berapa total siswa terdaftar?'],
            ['icon' => 'bi-hourglass-split', 'label' => 'Izin Menunggu',       'prompt' => 'Pengajuan izin dan sakit yang menunggu persetujuan'],
            ['icon' => 'bi-patch-check',     'label' => 'Disetujui Hari Ini',  'prompt' => 'Jumlah pengajuan yang disetujui hari ini'],
            ['icon' => 'bi-check2-circle',   'label' => 'Kehadiran Hari Ini',  'prompt' => 'Jumlah kehadiran siswa hari ini'],
            ['icon' => 'bi-envelope-paper',  'label' => 'Siswa Izin Hari Ini', 'prompt' => 'Siapa saja siswa yang izin atau sakit hari ini?'],
            ['icon' => 'bi-compass',         'label' => 'Panduan Guru',        'prompt' => 'Panduan portal guru'],
        ],
        'student' => [
            ['icon' => 'bi-person-badge',    'label' => 'Siapa Saya?',         'prompt' => 'Siapa saya?'],
            ['icon' => 'bi-calendar-check',  'label' => 'Status Hari Ini',     'prompt' => 'Status absensi saya hari ini'],
            ['icon' => 'bi-check2-all',      'label' => 'Hadir Tepat Waktu',   'prompt' => 'Berapa kali hadir tepat waktu?'],
            ['icon' => 'bi-clock-history',   'label' => 'Keterlambatan',       'prompt' => 'Jumlah keterlambatan saya'],
            ['icon' => 'bi-envelope-paper',  'label' => 'Izin & Sakit',        'prompt' => 'Berapa jumlah izin dan sakit saya?'],
            ['icon' => 'bi-percent',         'label' => 'Tingkat Kehadiran',   'prompt' => 'Berapa persen tingkat kehadiran saya?'],
            ['icon' => 'bi-file-earmark-medical', 'label' => 'Cara Izin',     'prompt' => 'Bagaimana alur pengajuan izin atau sakit?'],
            ['icon' => 'bi-compass',         'label' => 'Panduan Siswa',       'prompt' => 'Panduan dashboard siswa'],
        ],
        'guest' => [
            ['icon' => 'bi-camera',              'label' => 'Cara Absensi',     'prompt' => 'Bagaimana cara melakukan absensi mandiri dengan kamera?'],
            ['icon' => 'bi-camera-video-off',    'label' => 'Kamera Tidak Aktif','prompt' => 'Kamera absensi saya tidak aktif di browser, bagaimana solusinya?'],
            ['icon' => 'bi-person-x',            'label' => 'Verifikasi Gagal', 'prompt' => 'Verifikasi wajah saya selalu gagal, apa yang harus diperiksa?'],
            ['icon' => 'bi-clock',               'label' => 'Jam Kehadiran',    'prompt' => 'Jam berapa batas masuk dan toleransi keterlambatan?'],
            ['icon' => 'bi-file-earmark-medical','label' => 'Pengajuan Izin',   'prompt' => 'Bagaimana alur pengajuan izin atau sakit di sistem?'],
        ],
    ];
    $suggestionChips = $allChips[$userRole] ?? $allChips['guest'];

    // Endpoint: authenticated users -> Laravel backend, guests -> guest handler (same endpoint, will return auth error gracefully)
    $askUrl = $isAuthenticated ? route('chatbot.ask') : route('chatbot.ask');
    $csrfRequired = true;
@endphp

{{-- =====================================================================
     SmartBot — Unified Chatbot Component
     Combines: chatbot.blade.php (guest/home) + chatbot-plus.blade.php (auth)
     Backend: ChatbotController → ChatbotPlusService (multi-provider AI)
     ===================================================================== --}}
<div id="smartbot-root"
     class="chatbot-plus-root"
     data-chatbot="Chatbot+"
     data-ask-url="{{ $askUrl }}"
     data-authenticated="{{ $isAuthenticated ? 'true' : 'false' }}"
     data-role="{{ $userRole }}">

    {{-- Floating Action Button --}}
    <button id="smartbot-fab"
            class="smartbot-fab"
            type="button"
            aria-label="Buka Chatbot+ Asisten SmartAttendance"
            title="Chatbot+ — Asisten SmartAttendance">
        <span class="smartbot-fab-icon-wrap">
            <i class="bi bi-chat-dots-fill smartbot-icon-open" aria-hidden="true"></i>
            <i class="bi bi-x-lg smartbot-icon-close d-none" aria-hidden="true"></i>
        </span>
        <span class="smartbot-fab-label d-none d-sm-inline">Chatbot+</span>
        <span class="smartbot-online-dot" title="Online" aria-hidden="true"></span>
    </button>

    {{-- Chat Panel --}}
    <div id="smartbot-panel" class="smartbot-panel d-none"
         role="dialog"
         aria-labelledby="smartbot-panel-title"
         aria-modal="true">

        {{-- Header --}}
        <div class="smartbot-header">
            <div class="d-flex align-items-center gap-2">
                <div class="smartbot-header-icon" aria-hidden="true">
                    <i class="bi bi-robot"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span id="smartbot-panel-title" class="smartbot-header-title">Chatbot+</span>
                        <span class="smartbot-role-badge" title="Chatbot Terpadu">Chatbot+</span>
                        @if($roleLabel)
                            <span class="smartbot-role-badge">{{ $roleLabel }}</span>
                        @else
                            <span class="smartbot-role-badge">Umum</span>
                        @endif
                    </div>
                    <div class="smartbot-header-status">
                        <span class="smartbot-status-dot" aria-hidden="true"></span>
                        <span class="smartbot-status-text">Online</span>
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-1">
                <button type="button" id="smartbot-clear-btn"
                        class="smartbot-btn-icon"
                        title="Bersihkan percakapan"
                        aria-label="Bersihkan percakapan">
                    <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                </button>
                <button type="button" id="smartbot-close-btn"
                        class="smartbot-btn-icon"
                        title="Tutup SmartBot"
                        aria-label="Tutup SmartBot">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        {{-- Messages Area --}}
        <div id="smartbot-messages" class="smartbot-messages" role="log" aria-live="polite" aria-label="Percakapan SmartBot">

            {{-- Initial Bot Welcome Message --}}
            <div class="smartbot-msg smartbot-msg-bot" data-welcome>
                <div class="smartbot-msg-avatar" aria-hidden="true">
                    <i class="bi bi-robot"></i>
                </div>
                <div class="smartbot-msg-content">
                    <div class="smartbot-bubble smartbot-bubble-bot">
                        {!! nl2br(e(str_replace('**', '', $initialGreeting))) !!}
                    </div>
                    <span class="smartbot-time">Baru saja</span>
                </div>
            </div>

            {{-- Quick Suggestion Chips --}}
            <div id="smartbot-chips-wrap" class="smartbot-chips-section">
                <div class="smartbot-chips-label">Pertanyaan Cepat:</div>
                <div class="smartbot-chips-row" role="list">
                    @foreach($suggestionChips as $chip)
                        <button type="button"
                                class="smartbot-chip"
                                data-prompt="{{ $chip['prompt'] }}"
                                role="listitem"
                                aria-label="Tanya: {{ $chip['prompt'] }}">
                            <i class="bi {{ $chip['icon'] }}" aria-hidden="true"></i>
                            {{ $chip['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Typing Indicator (hidden by default) --}}
            <div id="smartbot-typing" class="smartbot-msg smartbot-msg-bot d-none" aria-hidden="true">
                <div class="smartbot-msg-avatar">
                    <i class="bi bi-robot"></i>
                </div>
                <div class="smartbot-msg-content">
                    <div class="smartbot-typing-bubble">
                        <span class="smartbot-dot"></span>
                        <span class="smartbot-dot"></span>
                        <span class="smartbot-dot"></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Footer / Input --}}
        <div class="smartbot-footer">
            @if(!$isAuthenticated)
                <div class="smartbot-guest-notice">
                    <i class="bi bi-info-circle-fill text-primary me-1" aria-hidden="true"></i>
                    <span>Login untuk mengakses data absensi pribadi Anda.</span>
                </div>
            @endif
            <form id="smartbot-form" class="m-0" onsubmit="return false;" novalidate>
                <div class="smartbot-input-row">
                    <textarea
                        id="smartbot-input"
                        class="smartbot-input"
                        placeholder="Ketik pertanyaan… (Enter untuk kirim)"
                        rows="1"
                        maxlength="1000"
                        aria-label="Pertanyaan untuk SmartBot"
                        aria-required="true"
                        autocomplete="off"
                        spellcheck="true"
                    ></textarea>
                    <button id="smartbot-send-btn"
                            class="smartbot-send-btn"
                            type="submit"
                            disabled
                            aria-label="Kirim pesan">
                        <i class="bi bi-send-fill" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="smartbot-footer-meta">
                    <span class="smartbot-footer-hint">
                        @if($isAuthenticated)
                            <i class="bi bi-shield-check text-success" aria-hidden="true"></i> Data aktual dari sistem
                        @else
                            <i class="bi bi-robot text-primary" aria-hidden="true"></i> Asisten umum sistem presensi
                        @endif
                    </span>
                    <span id="smartbot-char-count" class="smartbot-char-count" aria-live="polite">0/1000</span>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* ============================================================
   SmartBot — Unified Chatbot Widget
   Supports: guest (home), student, teacher, admin roles
   ============================================================ */

#smartbot-root,
.chatbot-plus-root {
    font-family: inherit;
    color: #1e293b;
    position: static !important;
    transform: none !important;
    animation: none !important;
    filter: none !important;
    perspective: none !important;
    contain: none !important;
    will-change: auto !important;
}

/* --- Floating Action Button --- */
.smartbot-fab {
    position: fixed !important;
    bottom: 24px !important;
    right: 24px !important;
    height: 48px;
    padding: 0 18px 0 14px;
    border-radius: 9999px;
    background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
    color: #ffffff;
    border: none;
    cursor: pointer;
    z-index: 1060 !important;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: background 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
    box-shadow: 0 4px 16px rgba(37, 99, 235, 0.38);
    margin: 0 !important;
}

.smartbot-fab:hover {
    background: linear-gradient(135deg, #1e40af 0%, #1d4ed8 100%);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(37, 99, 235, 0.45);
}

.smartbot-fab:active {
    transform: scale(0.96);
}

.smartbot-fab-icon-wrap {
    width: 22px;
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    position: relative;
}

.smartbot-fab-label {
    font-weight: 700;
    font-size: 0.875rem;
    letter-spacing: 0.01em;
}

.smartbot-online-dot {
    position: absolute;
    top: -2px;
    right: -2px;
    width: 11px;
    height: 11px;
    border-radius: 50%;
    background: #10b981;
    border: 2px solid #ffffff;
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    animation: smartbot-ping 2.5s infinite;
}

@keyframes smartbot-ping {
    0%   { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70%  { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
    100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

/* --- Panel --- */
.smartbot-panel {
    position: fixed !important;
    bottom: 84px !important;
    right: 24px !important;
    width: 390px;
    max-width: calc(100vw - 32px);
    height: 530px;
    max-height: calc(100dvh - 110px);
    background: #ffffff;
    border-radius: 18px;
    border: 1px solid #e2e8f0;
    z-index: 1065 !important;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    margin: 0 !important;
    box-shadow:
        0 4px 6px -1px rgba(0, 0, 0, 0.07),
        0 20px 40px -8px rgba(15, 23, 42, 0.14),
        0 0 0 1px rgba(0, 0, 0, 0.03);
    animation: smartbot-rise 0.22s cubic-bezier(0.16, 1, 0.3, 1) both;
}

@keyframes smartbot-rise {
    from { opacity: 0; transform: translateY(12px) scale(0.97); }
    to   { opacity: 1; transform: translateY(0)   scale(1); }
}

/* --- Header --- */
.smartbot-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 60%, #1d4ed8 100%);
    padding: 13px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    flex-shrink: 0;
}

.smartbot-header-icon {
    width: 32px;
    height: 32px;
    border-radius: 9px;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.18);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    flex-shrink: 0;
}

.smartbot-header-title {
    color: #ffffff;
    font-weight: 700;
    font-size: 0.9rem;
}

.smartbot-role-badge {
    background: rgba(255, 255, 255, 0.15);
    color: rgba(255, 255, 255, 0.85);
    font-size: 0.65rem;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 4px;
    letter-spacing: 0.03em;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.smartbot-header-status {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-top: 2px;
}

.smartbot-status-dot {
    width: 6px;
    height: 6px;
    background: #10b981;
    border-radius: 50%;
    display: inline-block;
}

.smartbot-status-text {
    font-size: 0.68rem;
    color: rgba(255, 255, 255, 0.6);
}

.smartbot-btn-icon {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 7px;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255, 255, 255, 0.75);
    cursor: pointer;
    font-size: 0.8rem;
    transition: all 0.15s ease;
}

.smartbot-btn-icon:hover {
    background: rgba(255, 255, 255, 0.22);
    color: #ffffff;
}

/* --- Messages Area --- */
.smartbot-messages {
    flex: 1;
    overflow-y: auto;
    padding: 14px 16px;
    background: #f8fafc;
    display: flex;
    flex-direction: column;
    gap: 10px;
    scroll-behavior: smooth;
}

.smartbot-messages::-webkit-scrollbar        { width: 5px; }
.smartbot-messages::-webkit-scrollbar-track  { background: transparent; }
.smartbot-messages::-webkit-scrollbar-thumb  { background: #cbd5e1; border-radius: 4px; }
.smartbot-messages::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

/* --- Message Rows --- */
.smartbot-msg {
    display: flex;
    gap: 8px;
    max-width: 90%;
}

.smartbot-msg-bot  { align-self: flex-start; }
.smartbot-msg-user {
    align-self: flex-end;
    flex-direction: row-reverse;
}

.smartbot-msg-avatar {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: #dde5f0;
    color: #475569;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.72rem;
    flex-shrink: 0;
    margin-top: 2px;
}

.smartbot-msg-user .smartbot-msg-avatar {
    background: #2563eb;
    color: #ffffff;
}

.smartbot-msg-content {
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-width: 0;
}

.smartbot-bubble {
    padding: 9px 13px;
    border-radius: 14px;
    font-size: 0.845rem;
    line-height: 1.5;
    word-break: break-word;
    overflow-wrap: break-word;
}

.smartbot-bubble-bot {
    background: #ffffff;
    color: #1e293b;
    border: 1px solid #e2e8f0;
    border-top-left-radius: 4px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.smartbot-bubble-user {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    color: #ffffff;
    border-top-right-radius: 4px;
    box-shadow: 0 3px 10px rgba(37, 99, 235, 0.22);
}

.smartbot-bubble-error {
    background: #fff1f2 !important;
    color: #991b1b !important;
    border-color: #fca5a5 !important;
}

.smartbot-time {
    font-size: 0.63rem;
    color: #94a3b8;
    padding: 0 2px;
}

.smartbot-msg-user .smartbot-time { align-self: flex-end; }

/* --- Suggestion Chips --- */
.smartbot-chips-section {
    margin: 2px 0 4px;
}

.smartbot-chips-label {
    font-size: 0.68rem;
    font-weight: 600;
    color: #64748b;
    margin-bottom: 7px;
}

.smartbot-chips-row {
    display: flex;
    gap: 6px;
    overflow-x: auto;
    padding-bottom: 4px;
    scrollbar-width: thin;
    -webkit-overflow-scrolling: touch;
}

.smartbot-chips-row::-webkit-scrollbar       { height: 3px; }
.smartbot-chips-row::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }

.smartbot-chip {
    flex: 0 0 auto;
    background: #ffffff;
    color: #374151;
    border: 1px solid #e2e8f0;
    border-radius: 9999px;
    padding: 4px 11px;
    font-size: 0.72rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
}

.smartbot-chip:hover {
    background: #eff6ff;
    color: #1d4ed8;
    border-color: #bfdbfe;
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(37, 99, 235, 0.12);
}

/* --- Typing Indicator --- */
.smartbot-typing-bubble {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    border-top-left-radius: 3px;
    padding: 9px 13px;
    display: flex;
    align-items: center;
    gap: 4px;
    width: fit-content;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.smartbot-dot {
    width: 5px;
    height: 5px;
    background: #94a3b8;
    border-radius: 50%;
    animation: smartbot-bounce 1.2s infinite ease-in-out;
}
.smartbot-dot:nth-child(2) { animation-delay: 0.2s; }
.smartbot-dot:nth-child(3) { animation-delay: 0.4s; }

@keyframes smartbot-bounce {
    0%, 80%, 100% { transform: translateY(0); opacity: 0.4; }
    40%            { transform: translateY(-5px); opacity: 1; }
}

/* --- Guest Notice Bar --- */
.smartbot-guest-notice {
    font-size: 0.72rem;
    color: #475569;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 7px;
    padding: 5px 10px;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 4px;
}

/* --- Footer / Input --- */
.smartbot-footer {
    padding: 10px 14px 12px;
    background: #ffffff;
    border-top: 1px solid #e2e8f0;
    flex-shrink: 0;
}

.smartbot-input-row {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #f8fafc;
    border: 1.5px solid #d1d5db;
    border-radius: 11px;
    padding: 4px 6px 4px 12px;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.smartbot-input-row:focus-within {
    background: #ffffff;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.smartbot-input {
    flex: 1;
    border: none;
    background: transparent;
    outline: none;
    font-size: 0.845rem;
    color: #1e293b;
    resize: none;
    max-height: 80px;
    padding: 5px 0;
    line-height: 1.45;
    font-family: inherit;
}

.smartbot-input::placeholder { color: #9ca3af; }

.smartbot-send-btn {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    background: #2563eb;
    color: #ffffff;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.15s ease;
    flex-shrink: 0;
}

.smartbot-send-btn:hover:not(:disabled) {
    background: #1d4ed8;
    box-shadow: 0 3px 10px rgba(37, 99, 235, 0.3);
}

.smartbot-send-btn:disabled {
    background: #e5e7eb;
    color: #9ca3af;
    cursor: not-allowed;
}

.smartbot-footer-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 6px;
    padding: 0 2px;
}

.smartbot-footer-hint {
    font-size: 0.67rem;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 3px;
}

.smartbot-char-count {
    font-size: 0.67rem;
    color: #9ca3af;
}

/* --- Tablet (577px - 991px) --- */
@media (min-width: 577px) and (max-width: 991.98px) {
    .smartbot-fab {
        bottom: 20px !important;
        right: 20px !important;
    }

    .smartbot-panel {
        bottom: 78px !important;
        right: 20px !important;
        width: 380px;
        max-width: calc(100vw - 40px);
        max-height: calc(100dvh - 100px);
    }
}

/* --- Mobile (<= 576px) --- */
@media (max-width: 576px) {
    .smartbot-fab {
        bottom: 16px !important;
        right: 16px !important;
        height: 44px;
        padding: 0 14px;
    }

    .smartbot-panel {
        bottom: 70px !important;
        right: 12px !important;
        left: 12px !important;
        width: auto !important;
        max-width: none !important;
        height: calc(100dvh - 86px) !important;
        max-height: 540px !important;
        border-radius: 16px;
    }

    .smartbot-messages { padding: 10px 12px; }
    .smartbot-footer    { padding: 8px 10px 10px; }
}
</style>

<script>
(function () {
    'use strict';

    function initSmartBot() {
        const root = document.getElementById('smartbot-root');
        if (!root) return;

        // Move widget to document.body so position:fixed is always relative to viewport
        // and completely immune to any parent's overflow:hidden, transform, or stacking context
        if (root.parentElement && root.parentElement !== document.body) {
            document.body.appendChild(root);
        }

        if (root.dataset.smartbotInitialized === 'true') return;
        root.dataset.smartbotInitialized = 'true';

        const askUrl        = root.dataset.askUrl;
        const isAuth        = root.dataset.authenticated === 'true';

        const fab           = document.getElementById('smartbot-fab');
        const panel         = document.getElementById('smartbot-panel');
        const closeBtn      = document.getElementById('smartbot-close-btn');
        const clearBtn      = document.getElementById('smartbot-clear-btn');
        const messages      = document.getElementById('smartbot-messages');
        const typingEl      = document.getElementById('smartbot-typing');
        const chipsWrap     = document.getElementById('smartbot-chips-wrap');
        const form          = document.getElementById('smartbot-form');
        const input         = document.getElementById('smartbot-input');
        const sendBtn       = document.getElementById('smartbot-send-btn');
        const charCountEl   = document.getElementById('smartbot-char-count');
        const iconOpen      = fab?.querySelector('.smartbot-icon-open');
        const iconClose     = fab?.querySelector('.smartbot-icon-close');

        const initialHtml   = messages ? messages.innerHTML : '';
        let isProcessing    = false;

        /**
         * Restore the composer to a usable state.
         * Called on init and whenever the page is restored (bfcache/reload) so a
         * stale in-flight request can never leave the input or send button locked.
         */
        function resetComposerState() {
            isProcessing = false;
            typingEl?.classList.add('d-none');

            if (input) {
                input.disabled = false;
                input.readOnly = false;
                input.removeAttribute('disabled');
                input.removeAttribute('readonly');
            }

            updateInputState();
        }

        /* ---- Helpers ---- */
        function getCurrentTime() {
            return new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        }

        function escapeHtml(str) {
            const d = document.createElement('div');
            d.textContent = str;
            return d.innerHTML;
        }

        function formatBotText(raw) {
            // Escape HTML first, then render markdown-like formatting
            let txt = escapeHtml(raw);
            txt = txt.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            txt = txt.replace(/^[•\-\*]\s+(.*)$/gm, '• $1');
            txt = txt.replace(/\n/g, '<br>');
            return txt;
        }

        function scrollBottom() {
            if (messages) messages.scrollTop = messages.scrollHeight;
        }

        function getCsrf() {
            const m = document.querySelector('meta[name="csrf-token"]');
            return m ? m.getAttribute('content') : '';
        }

        /* ---- Panel toggle ---- */
        function openPanel() {
            panel.classList.remove('d-none');
            iconOpen?.classList.add('d-none');
            iconClose?.classList.remove('d-none');
            panel.removeAttribute('aria-hidden');
            scrollBottom();
            setTimeout(() => input?.focus(), 150);
        }

        function closePanel() {
            panel.classList.add('d-none');
            iconOpen?.classList.remove('d-none');
            iconClose?.classList.add('d-none');
            panel.setAttribute('aria-hidden', 'true');
        }

        function togglePanel() {
            panel.classList.contains('d-none') ? openPanel() : closePanel();
        }

        fab?.addEventListener('click', (e) => { e.stopPropagation(); togglePanel(); });
        closeBtn?.addEventListener('click', closePanel);

        // Dismiss on outside click
        document.addEventListener('click', function (e) {
            if (!panel.classList.contains('d-none') &&
                !panel.contains(e.target) &&
                !fab.contains(e.target)) {
                closePanel();
            }
        });

        // ESC key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !panel.classList.contains('d-none')) closePanel();
        });

        /* ---- Clear conversation ---- */
        clearBtn?.addEventListener('click', function () {
            messages.innerHTML = initialHtml;
            attachChipHandlers();
            input.value = '';
            updateInputState();
            scrollBottom();
        });

        /* ---- Input state ---- */
        function updateInputState() {
            const len = input.value.length;
            if (charCountEl) charCountEl.textContent = `${len}/1000`;
            if (sendBtn) sendBtn.disabled = len === 0 || input.value.trim() === '' || isProcessing;
        }

        input?.addEventListener('input', function () {
            updateInputState();
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 80) + 'px';
        });

        input?.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (!sendBtn.disabled) sendMessage(input.value);
            }
        });

        form?.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!sendBtn.disabled) sendMessage(input.value);
        });

        /* ---- Quick chips ---- */
        function attachChipHandlers() {
            root.querySelectorAll('.smartbot-chip').forEach(function (chip) {
                chip.onclick = function () {
                    const prompt = this.dataset.prompt;
                    if (prompt && !isProcessing) sendMessage(prompt);
                };
            });
        }
        attachChipHandlers();

        /* ---- Append messages ---- */
        function appendUserMsg(text) {
            const div = document.createElement('div');
            div.className = 'smartbot-msg smartbot-msg-user';
            div.innerHTML = `
                <div class="smartbot-msg-avatar" aria-hidden="true"><i class="bi bi-person-fill"></i></div>
                <div class="smartbot-msg-content">
                    <div class="smartbot-bubble smartbot-bubble-user">${escapeHtml(text)}</div>
                    <span class="smartbot-time">${getCurrentTime()}</span>
                </div>`;
            messages.insertBefore(div, typingEl);
            scrollBottom();
        }

        function appendBotMsg(text, isError = false) {
            const div = document.createElement('div');
            div.className = 'smartbot-msg smartbot-msg-bot';
            const bubbleClass = isError ? 'smartbot-bubble smartbot-bubble-bot smartbot-bubble-error' : 'smartbot-bubble smartbot-bubble-bot';
            const avatarStyle = isError ? 'style="background:#fee2e2;color:#ef4444;"' : '';
            div.innerHTML = `
                <div class="smartbot-msg-avatar" ${avatarStyle} aria-hidden="true"><i class="bi bi-robot"></i></div>
                <div class="smartbot-msg-content">
                    <div class="${bubbleClass}">${isError ? escapeHtml(text) : formatBotText(text)}</div>
                    <span class="smartbot-time">${getCurrentTime()}</span>
                </div>`;
            messages.insertBefore(div, typingEl);
            scrollBottom();
        }

        /* ---- Send message ---- */
        async function sendMessage(rawText) {
            const text = rawText.trim();
            if (!text || isProcessing) return;

            // If guest, show a helpful notice instead of hitting the 401
            if (!isAuth) {
                appendUserMsg(text);
                hideChips();
                appendBotMsg(buildGuestReply(text));
                return;
            }

            appendUserMsg(text);
            hideChips();

            input.value = '';
            input.style.height = 'auto';
            isProcessing = true;
            updateInputState();

            typingEl?.classList.remove('d-none');
            scrollBottom();

            // Hard timeout so a stalled provider can never keep the composer locked.
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 60000);

            try {
                const resp = await fetch(askUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ message: text }),
                    signal: controller.signal,
                });

                const data = await resp.json().catch(() => ({}));

                typingEl?.classList.add('d-none');

                if (resp.ok && data.success) {
                    appendBotMsg(data.reply || 'Tidak ada balasan.');
                } else if (resp.status === 401) {
                    appendBotMsg('Sesi login Anda telah berakhir. Silakan muat ulang halaman dan login kembali.', true);
                } else if (resp.status === 429) {
                    appendBotMsg('Anda mengirim terlalu banyak pesan. Mohon tunggu sebentar sebelum mencoba lagi.', true);
                } else {
                    const msg = data.reply || data.message || 'Terjadi kendala saat memproses pertanyaan Anda.';
                    appendBotMsg(msg, !data.success);
                }
            } catch (err) {
                console.error('[SmartBot]', err);
                typingEl?.classList.add('d-none');

                if (err && err.name === 'AbortError') {
                    appendBotMsg('Permintaan memakan waktu terlalu lama. Silakan coba kirim ulang pesan Anda.', true);
                } else {
                    appendBotMsg('Tidak dapat terhubung ke server SmartAttendance. Periksa koneksi Anda atau muat ulang halaman.', true);
                }
            } finally {
                clearTimeout(timeoutId);
                isProcessing = false;
                typingEl?.classList.add('d-none');
                updateInputState();
                input?.focus();
            }
        }

        /* Guest mode: answer common system questions locally (no auth needed) */
        function buildGuestReply(text) {
            const t = text.toLowerCase();

            if (/jam|batas|toleransi|terlambat|masuk sekolah/.test(t)) {
                return 'Presensi SmartAttendance dibuka mulai pukul 06:30 WIB. Batas kehadiran tepat waktu adalah pukul 07:15 WIB. Presensi setelah pukul 07:15 WIB akan tercatat sebagai Terlambat.';
            }
            if (/cara absen|cara presensi|cara scan|bagaimana absen/.test(t)) {
                return 'Cara absensi mandiri:\n1. Buka halaman Scanner (/attendance).\n2. Izinkan akses kamera browser.\n3. Posisikan wajah di bingkai oval panduan.\n4. Tunggu verifikasi liveness dan pencocokan biometrik.\n5. Notifikasi berhasil muncul beserta jam kehadiran.';
            }
            if (/kamera|izin kamera|camera/.test(t)) {
                return 'Solusi kamera tidak aktif:\n1. Klik ikon gembok di address bar → set izin Kamera ke "Allow".\n2. Tutup aplikasi lain yang memakai kamera (Zoom, Meet).\n3. Muat ulang (refresh) halaman.\n4. Gunakan Chrome atau Safari versi terbaru.';
            }
            if (/verifikasi|wajah gagal|scan gagal|muka tidak/.test(t)) {
                return 'Solusi verifikasi wajah gagal:\n1. Pastikan cahaya ruangan cukup terang.\n2. Lepas kacamata hitam, topi, atau masker.\n3. Dekatkan wajah ke bingkai oval secara simetris.\n4. Pastikan foto biometrik wajah sudah didaftarkan Administrator.';
            }
            if (/izin|sakit|alur izin|pengajuan/.test(t)) {
                return 'Alur pengajuan izin atau sakit:\n1. Login ke portal siswa → menu "Ajukan Izin / Sakit".\n2. Pilih jenis (Izin/Sakit), isi tanggal & keterangan alasan.\n3. Unggah bukti pendukung (surat dokter/keterangan orang tua).\n4. Submit dan tunggu persetujuan dari Bapak/Ibu Guru.';
            }
            if (/siapa|nama saya|akun saya/.test(t)) {
                return 'Anda belum login. Silakan masuk ke akun Anda terlebih dahulu agar saya dapat memberikan informasi pribadi Anda.';
            }

            // Generic fallback for guests
            return `Untuk menjawab pertanyaan ini dengan data aktual, silakan **login** terlebih dahulu. Sebagai tamu, saya hanya dapat menjawab pertanyaan umum tentang cara penggunaan SmartAttendance.\n\nPertanyaan umum yang dapat saya bantu: cara absensi, jam masuk, masalah kamera, cara izin/sakit.`;
        }

        function hideChips() {
            if (chipsWrap) chipsWrap.style.display = 'none';
        }

        // Fresh page load, reload, or bfcache restore: always start unlocked.
        resetComposerState();
        window.addEventListener('pageshow', resetComposerState);
    }

    // The component may be parsed before OR after DOMContentLoaded fires
    // (cached restores, partial reloads, or late script execution).
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSmartBot, { once: true });
    } else {
        initSmartBot();
    }
})();
</script>
