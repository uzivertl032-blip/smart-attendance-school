@php
    $currentUser = Auth::user();
    $userRole = $currentUser->role ?? 'guest';
    $userName = trim((string) ($currentUser->name ?? ''));
    $displayGreetingName = $userName !== '' ? $userName : 'Pengguna';

    // Role specific configuration
    $roleLabels = [
        'admin' => 'Administrator',
        'teacher' => 'Guru',
        'student' => 'Siswa',
    ];
    $roleLabel = $roleLabels[$userRole] ?? 'Pengguna';

    // Dynamic initial greeting message
    $initialGreetings = [
        'admin' => $userName !== ''
            ? "Halo **{$userName}**! Saya Chatbot+, asisten SmartAttendance. Saya siap membantu menampilkan statistik siswa, guru, kehadiran hari ini, siapa yang belum absen, serta panduan menu admin."
            : "Halo Administrator! Saya Chatbot+, asisten SmartAttendance. Saya siap membantu menampilkan statistik siswa, guru, kehadiran hari ini, siapa yang belum absen, serta panduan menu admin.",
        'teacher' => $userName !== ''
            ? "Halo Bapak/Ibu **{$userName}**! Saya Chatbot+, asisten portal guru SmartAttendance. Tanyakan kehadiran siswa hari ini, permohonan izin/sakit yang menunggu persetujuan, atau panduan guru."
            : "Halo Guru! Saya Chatbot+, asisten portal guru SmartAttendance. Tanyakan kehadiran siswa hari ini, permohonan izin/sakit yang menunggu persetujuan, atau panduan guru.",
        'student' => $userName !== ''
            ? "Halo **{$userName}**! Saya Chatbot+, asisten presensi kamu. Tanyakan status absensi hari ini, kehadiran tepat waktu, keterlambatan, persentase kehadiran, atau izin/sakit."
            : "Halo Siswa! Saya Chatbot+, asisten presensi kamu. Tanyakan status absensi hari ini, kehadiran tepat waktu, keterlambatan, persentase kehadiran, atau izin/sakit.",
    ];
    $initialGreeting = $initialGreetings[$userRole] ?? "Halo **{$displayGreetingName}**! Saya Chatbot+ SmartAttendance. Ada yang bisa saya bantu terkait sistem absensi sekolah?";

    // Suggestion chips per role
    $suggestionChips = match ($userRole) {
        'admin' => [
            ['icon' => 'bi-person-badge', 'label' => 'Siapa Saya?', 'prompt' => 'Siapa saya?'],
            ['icon' => 'bi-mortarboard', 'label' => 'Total Siswa', 'prompt' => 'Berapa total siswa?'],
            ['icon' => 'bi-person-video3', 'label' => 'Total Guru', 'prompt' => 'Berapa total guru?'],
            ['icon' => 'bi-check2-circle', 'label' => 'Hadir Hari Ini', 'prompt' => 'Berapa yang hadir hari ini?'],
            ['icon' => 'bi-clock-history', 'label' => 'Terlambat Hari Ini', 'prompt' => 'Berapa yang terlambat hari ini?'],
            ['icon' => 'bi-person-x', 'label' => 'Siapa Belum Absen?', 'prompt' => 'Siapa yang belum absen?'],
            ['icon' => 'bi-compass', 'label' => 'Panduan Admin', 'prompt' => 'Panduan menu admin'],
        ],
        'teacher' => [
            ['icon' => 'bi-person-badge', 'label' => 'Siapa Saya?', 'prompt' => 'Siapa saya?'],
            ['icon' => 'bi-mortarboard', 'label' => 'Total Siswa', 'prompt' => 'Berapa total siswa terdaftar?'],
            ['icon' => 'bi-hourglass-split', 'label' => 'Izin Menunggu', 'prompt' => 'Pengajuan izin dan sakit yang menunggu persetujuan'],
            ['icon' => 'bi-patch-check', 'label' => 'Disetujui Hari Ini', 'prompt' => 'Jumlah pengajuan yang disetujui hari ini'],
            ['icon' => 'bi-check2-circle', 'label' => 'Kehadiran Hari Ini', 'prompt' => 'Jumlah kehadiran hari ini'],
            ['icon' => 'bi-compass', 'label' => 'Panduan Guru', 'prompt' => 'Panduan portal guru'],
        ],
        'student' => [
            ['icon' => 'bi-person-badge', 'label' => 'Siapa Saya?', 'prompt' => 'Siapa saya?'],
            ['icon' => 'bi-calendar-check', 'label' => 'Status Hari Ini', 'prompt' => 'Status absensi hari ini'],
            ['icon' => 'bi-check2-all', 'label' => 'Hadir Tepat Waktu', 'prompt' => 'Berapa kali hadir tepat waktu?'],
            ['icon' => 'bi-clock-history', 'label' => 'Keterlambatan', 'prompt' => 'Jumlah keterlambatan saya'],
            ['icon' => 'bi-envelope-paper', 'label' => 'Izin & Sakit', 'prompt' => 'Berapa jumlah izin dan sakit saya?'],
            ['icon' => 'bi-percent', 'label' => 'Tingkat Kehadiran', 'prompt' => 'Berapa persen kehadiran saya?'],
            ['icon' => 'bi-compass', 'label' => 'Panduan Siswa', 'prompt' => 'Panduan dashboard siswa'],
        ],
        default => [
            ['icon' => 'bi-person-badge', 'label' => 'Siapa Saya?', 'prompt' => 'Siapa saya?'],
            ['icon' => 'bi-clock', 'label' => 'Jam Absensi', 'prompt' => 'Jam berapa batas absensi?'],
            ['icon' => 'bi-camera', 'label' => 'Cara Absensi', 'prompt' => 'Bagaimana cara melakukan absensi mandiri?'],
        ]
    };
@endphp

{{-- Chatbot+ Component Container --}}
<div id="chatbot-plus-root" data-ask-url="{{ route('chatbot.ask') }}">
    {{-- Floating Action Button (Clean & Modern) --}}
    <button id="cbp-toggle-btn"
            class="cbp-fab"
            type="button"
            aria-label="Buka Chatbot+ Asisten SmartAttendance"
            title="Tanya Chatbot+ SmartAttendance">
        <div class="cbp-fab-content">
            <span class="cbp-fab-icon-box">
                <i class="bi bi-chat-dots-fill cbp-icon-open"></i>
                <i class="bi bi-x-lg cbp-icon-close d-none"></i>
            </span>
            <span class="cbp-fab-text d-none d-sm-inline">Chatbot+</span>
        </div>
    </button>

    {{-- Chatbot+ Modal Panel --}}
    <div id="cbp-panel" class="cbp-panel d-none" role="dialog" aria-labelledby="cbp-title" aria-modal="true">
        {{-- Header Ringkas --}}
        <div class="cbp-header">
            <div class="d-flex align-items-center gap-2">
                <div class="cbp-header-icon">
                    <i class="bi bi-chat-dots-fill"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-1.5">
                        <span id="cbp-title" class="cbp-title">Chatbot+</span>
                        <span class="cbp-role-badge">{{ $roleLabel }}</span>
                    </div>
                    <div class="cbp-status">
                        <span class="cbp-status-dot"></span>
                        <span class="cbp-status-text">Online</span>
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-1">
                <button type="button" id="cbp-clear-btn" class="cbp-btn-icon" title="Bersihkan Percakapan" aria-label="Bersihkan Percakapan">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </button>
                <button type="button" id="cbp-close-btn" class="cbp-btn-icon" title="Tutup Chatbot+" aria-label="Tutup Chatbot+">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>

        {{-- Conversation Body --}}
        <div id="cbp-messages" class="cbp-messages">
            {{-- Initial Bot Welcome Message --}}
            <div class="cbp-msg cbp-msg-bot">
                <div class="cbp-msg-avatar">
                    <i class="bi bi-robot"></i>
                </div>
                <div class="cbp-msg-content">
                    <div class="cbp-bubble">
                        {!! nl2br(e(str_replace('**', '', $initialGreeting))) !!}
                    </div>
                    <span class="cbp-time">Baru saja</span>
                </div>
            </div>

            {{-- Dynamic Role Quick Chips (Ringkas & Scrollable) --}}
            <div id="cbp-suggestions" class="cbp-suggestions">
                <div class="cbp-suggestions-label">Pertanyaan Cepat:</div>
                <div class="cbp-chips-container">
                    @foreach($suggestionChips as $chip)
                        <button type="button" class="cbp-chip" data-prompt="{{ $chip['prompt'] }}">
                            <i class="bi {{ $chip['icon'] }} me-1"></i>{{ $chip['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Typing Indicator --}}
            <div id="cbp-typing" class="cbp-msg cbp-msg-bot d-none">
                <div class="cbp-msg-avatar">
                    <i class="bi bi-robot"></i>
                </div>
                <div class="cbp-msg-content">
                    <div class="cbp-typing-bubble">
                        <span class="cbp-dot"></span>
                        <span class="cbp-dot"></span>
                        <span class="cbp-dot"></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Footer & Input --}}
        <div class="cbp-footer">
            <form id="cbp-form" class="m-0" onsubmit="return false;">
                <div class="cbp-input-wrapper">
                    <textarea
                        id="cbp-input"
                        class="cbp-input"
                        placeholder="Ketik pertanyaan... (Enter untuk kirim)"
                        rows="1"
                        maxlength="1000"
                        aria-label="Pertanyaan untuk Chatbot+"
                    ></textarea>
                    <button id="cbp-send-btn" class="cbp-send-btn" type="submit" disabled aria-label="Kirim Pesan">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-1.5 px-1">
                    <span class="cbp-footer-hint">
                        <i class="bi bi-shield-check text-success me-1"></i>Data aktual dari sistem
                    </span>
                    <span id="cbp-char-count" class="cbp-footer-count">0/1000</span>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Chatbot+ Scoped Design */
#chatbot-plus-root {
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

/* Floating Action Button (FAB) */
.cbp-fab {
    position: fixed !important;
    bottom: 24px !important;
    right: 24px !important;
    height: 46px;
    padding: 0 16px 0 12px;
    border-radius: 9999px;
    background: #2563eb;
    color: #ffffff;
    border: none;
    cursor: pointer;
    z-index: 1060 !important;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
    margin: 0 !important;
}

.cbp-fab:hover {
    background: #1d4ed8;
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(37, 99, 235, 0.45);
}

.cbp-fab-content {
    display: flex;
    align-items: center;
    gap: 8px;
}

.cbp-fab-icon-box {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

.cbp-fab-text {
    font-weight: 600;
    font-size: 0.9rem;
    color: #ffffff;
}

/* Chatbot+ Panel */
.cbp-panel {
    position: fixed !important;
    bottom: 82px !important;
    right: 24px !important;
    width: 380px;
    max-width: calc(100vw - 32px);
    height: 520px;
    max-height: calc(100dvh - 110px);
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    z-index: 1065 !important;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    margin: 0 !important;
    box-shadow: 0 12px 32px -8px rgba(15, 23, 42, 0.15), 0 0 0 1px rgba(0, 0, 0, 0.04);
    animation: cbp-fade-in 0.2s ease-out;
}

@keyframes cbp-fade-in {
    from {
        opacity: 0;
        transform: translateY(10px) scale(0.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Header Ringkas */
.cbp-header {
    background: #ffffff;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #e2e8f0;
}

.cbp-header-icon {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: #eff6ff;
    color: #2563eb;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
}

.cbp-title {
    color: #0f172a;
    font-weight: 700;
    font-size: 0.95rem;
    line-height: 1.2;
}

.cbp-role-badge {
    background: #f1f5f9;
    color: #64748b;
    font-size: 0.68rem;
    font-weight: 600;
    padding: 1px 6px;
    border-radius: 4px;
    letter-spacing: 0.02em;
}

.cbp-status {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-top: 1px;
}

.cbp-status-dot {
    width: 6px;
    height: 6px;
    background-color: #10b981;
    border-radius: 50%;
}

.cbp-status-text {
    font-size: 0.7rem;
    color: #64748b;
}

.cbp-btn-icon {
    background: transparent;
    border: none;
    color: #64748b;
    width: 30px;
    height: 30px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s ease;
    font-size: 0.875rem;
}

.cbp-btn-icon:hover {
    color: #0f172a;
    background: #f1f5f9;
}

/* Messages Area */
.cbp-messages {
    flex: 1;
    overflow-y: auto;
    padding: 14px 16px;
    background: #f8fafc;
    display: flex;
    flex-direction: column;
    gap: 12px;
    scroll-behavior: smooth;
}

.cbp-messages::-webkit-scrollbar {
    width: 5px;
}
.cbp-messages::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}
.cbp-messages::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

.cbp-msg {
    display: flex;
    gap: 8px;
    max-width: 88%;
}

.cbp-msg-bot {
    align-self: flex-start;
}

.cbp-msg-user {
    align-self: flex-end;
    flex-direction: row-reverse;
}

.cbp-msg-avatar {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: #e2e8f0;
    color: #475569;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    flex-shrink: 0;
    margin-top: 2px;
}

.cbp-msg-user .cbp-msg-avatar {
    background: #2563eb;
    color: #ffffff;
}

.cbp-msg-content {
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-width: 0;
}

.cbp-bubble {
    padding: 8px 12px;
    border-radius: 12px;
    font-size: 0.85rem;
    line-height: 1.45;
    word-break: break-word;
    overflow-wrap: break-word;
}

.cbp-msg-bot .cbp-bubble {
    background: #ffffff;
    color: #1e293b;
    border: 1px solid #e2e8f0;
    border-top-left-radius: 3px;
}

.cbp-msg-user .cbp-bubble {
    background: #2563eb;
    color: #ffffff;
    border-top-right-radius: 3px;
}

.cbp-time {
    font-size: 0.65rem;
    color: #94a3b8;
    padding: 0 2px;
}

.cbp-msg-user .cbp-time {
    align-self: flex-end;
}

/* Quick Suggestion Chips (Minimal & Scrollable) */
.cbp-suggestions {
    margin: 2px 0;
}

.cbp-suggestions-label {
    font-size: 0.7rem;
    font-weight: 600;
    color: #64748b;
    margin-bottom: 6px;
}

.cbp-chips-container {
    display: flex;
    gap: 6px;
    overflow-x: auto;
    padding-bottom: 4px;
    scrollbar-width: thin;
    -webkit-overflow-scrolling: touch;
}

.cbp-chips-container::-webkit-scrollbar {
    height: 3px;
}

.cbp-chips-container::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}

.cbp-chip {
    flex: 0 0 auto;
    background: #ffffff;
    color: #334155;
    border: 1px solid #e2e8f0;
    border-radius: 9999px;
    padding: 4px 10px;
    font-size: 0.72rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    white-space: nowrap;
}

.cbp-chip:hover {
    background: #f1f5f9;
    color: #1d4ed8;
    border-color: #cbd5e1;
}

/* Typing Indicator */
.cbp-typing-bubble {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    border-top-left-radius: 3px;
    padding: 8px 12px;
    display: flex;
    align-items: center;
    gap: 4px;
    width: fit-content;
}

.cbp-dot {
    width: 5px;
    height: 5px;
    background: #94a3b8;
    border-radius: 50%;
    animation: cbp-dot-bounce 1.2s infinite ease-in-out;
}

.cbp-dot:nth-child(2) { animation-delay: 0.2s; }
.cbp-dot:nth-child(3) { animation-delay: 0.4s; }

@keyframes cbp-dot-bounce {
    0%, 80%, 100% { transform: translateY(0); opacity: 0.4; }
    40% { transform: translateY(-4px); opacity: 1; }
}

/* Footer & Input */
.cbp-footer {
    padding: 10px 14px;
    background: #ffffff;
    border-top: 1px solid #e2e8f0;
}

.cbp-input-wrapper {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    padding: 3px 6px 3px 12px;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.cbp-input-wrapper:focus-within {
    background: #ffffff;
    border-color: #2563eb;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
}

.cbp-input {
    flex: 1;
    border: none;
    background: transparent;
    outline: none;
    font-size: 0.85rem;
    color: #1e293b;
    resize: none;
    max-height: 80px;
    padding: 5px 0;
    line-height: 1.4;
}

.cbp-input::placeholder {
    color: #94a3b8;
}

.cbp-send-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: #2563eb;
    color: #ffffff;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.15s ease;
    flex-shrink: 0;
}

.cbp-send-btn:hover:not(:disabled) {
    background: #1d4ed8;
}

.cbp-send-btn:disabled {
    background: #f1f5f9;
    color: #cbd5e1;
    cursor: not-allowed;
}

.cbp-footer-hint {
    font-size: 0.68rem;
    color: #64748b;
}

.cbp-footer-count {
    font-size: 0.68rem;
    color: #94a3b8;
}

/* Tablet Responsiveness */
@media (min-width: 577px) and (max-width: 991.98px) {
    .cbp-fab {
        bottom: 20px !important;
        right: 20px !important;
    }

    .cbp-panel {
        bottom: 76px !important;
        right: 20px !important;
        width: 380px;
        max-width: calc(100vw - 40px);
        max-height: calc(100dvh - 98px);
    }
}

/* Mobile Responsiveness */
@media (max-width: 576px) {
    .cbp-fab {
        bottom: 16px !important;
        right: 16px !important;
        height: 42px;
        padding: 0 12px;
    }

    .cbp-panel {
        bottom: 68px !important;
        right: 12px !important;
        left: 12px !important;
        width: auto !important;
        max-width: none !important;
        height: calc(100dvh - 86px) !important;
        max-height: 530px !important;
        border-radius: 14px;
    }

    .cbp-messages {
        padding: 10px 12px;
    }

    .cbp-footer {
        padding: 8px 10px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.getElementById('chatbot-plus-root');
    if (!root) return;

    // Move to document.body so position:fixed is always relative to viewport
    if (root.parentElement && root.parentElement !== document.body) {
        document.body.appendChild(root);
    }

    const askUrl = root.getAttribute('data-ask-url');
    const toggleBtn = document.getElementById('cbp-toggle-btn');
    const panel = document.getElementById('cbp-panel');
    const closeBtn = document.getElementById('cbp-close-btn');
    const clearBtn = document.getElementById('cbp-clear-btn');
    const form = document.getElementById('cbp-form');
    const input = document.getElementById('cbp-input');
    const sendBtn = document.getElementById('cbp-send-btn');
    const messages = document.getElementById('cbp-messages');
    const typing = document.getElementById('cbp-typing');
    const charCount = document.getElementById('cbp-char-count');

    const iconOpen = toggleBtn?.querySelector('.cbp-icon-open');
    const iconClose = toggleBtn?.querySelector('.cbp-icon-close');

    let isProcessing = false;
    const initialHtml = messages ? messages.innerHTML : '';

    // Toggle Panel
    function togglePanel() {
        const isHidden = panel.classList.contains('d-none');
        if (isHidden) {
            panel.classList.remove('d-none');
            iconOpen?.classList.add('d-none');
            iconClose?.classList.remove('d-none');
            scrollToBottom();
            setTimeout(() => input?.focus(), 150);
        } else {
            panel.classList.add('d-none');
            iconOpen?.classList.remove('d-none');
            iconClose?.classList.add('d-none');
        }
    }

    toggleBtn?.addEventListener('click', togglePanel);
    closeBtn?.addEventListener('click', togglePanel);

    // Close on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && panel && !panel.classList.contains('d-none')) {
            togglePanel();
        }
    });

    // Clear chat history
    clearBtn?.addEventListener('click', function () {
        messages.innerHTML = initialHtml;
        attachSuggestionHandlers();
        input.value = '';
        updateInputState();
        scrollToBottom();
    });

    // Input state & char counter
    function updateInputState() {
        const val = input.value;
        const len = val.length;
        if (charCount) charCount.textContent = `${len}/1000`;
        if (sendBtn) {
            sendBtn.disabled = len === 0 || val.trim() === '' || isProcessing;
        }
    }

    input?.addEventListener('input', function () {
        updateInputState();
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 80) + 'px';
    });

    // Enter to send (Shift+Enter for newline)
    input?.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (!sendBtn.disabled) {
                sendMessage(input.value);
            }
        }
    });

    // Form submit
    form?.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!sendBtn.disabled) {
            sendMessage(input.value);
        }
    });

    // Quick suggestion chips handler
    function attachSuggestionHandlers() {
        const chips = root.querySelectorAll('.cbp-chip');
        chips.forEach(chip => {
            chip.onclick = function () {
                const prompt = this.getAttribute('data-prompt');
                if (prompt && !isProcessing) {
                    sendMessage(prompt);
                }
            };
        });
    }
    attachSuggestionHandlers();

    // Scroll chat to bottom
    function scrollToBottom() {
        if (messages) {
            messages.scrollTop = messages.scrollHeight;
        }
    }

    // Escape HTML for safety
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Format time
    function getCurrentTime() {
        const now = new Date();
        return now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    }

    // Append User Message
    function appendUserMessage(text) {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'cbp-msg cbp-msg-user';
        msgDiv.innerHTML = `
            <div class="cbp-msg-avatar">
                <i class="bi bi-person-fill"></i>
            </div>
            <div class="cbp-msg-content">
                <div class="cbp-bubble">${escapeHtml(text)}</div>
                <span class="cbp-time">${getCurrentTime()}</span>
            </div>
        `;
        if (typing) {
            messages.insertBefore(msgDiv, typing);
        } else {
            messages.appendChild(msgDiv);
        }
        scrollToBottom();
    }

    // Append Bot Message
    function appendBotMessage(text, isError = false) {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'cbp-msg cbp-msg-bot';

        const formattedText = escapeHtml(text).replace(/\n/g, '<br>');
        const errorStyle = isError ? 'style="border-color: #fca5a5; background: #fff1f2; color: #991b1b;"' : '';

        msgDiv.innerHTML = `
            <div class="cbp-msg-avatar" ${isError ? 'style="background: #fee2e2; color: #ef4444;"' : ''}>
                <i class="bi bi-robot"></i>
            </div>
            <div class="cbp-msg-content">
                <div class="cbp-bubble" ${errorStyle}>${formattedText}</div>
                <span class="cbp-time">${getCurrentTime()}</span>
            </div>
        `;
        if (typing) {
            messages.insertBefore(msgDiv, typing);
        } else {
            messages.appendChild(msgDiv);
        }
        scrollToBottom();
    }

    // Send Message via AJAX to Laravel backend
    async function sendMessage(text) {
        const clean = text.trim();
        if (!clean) {
            appendBotMessage('Silakan ketik pertanyaan terlebih dahulu.', true);
            return;
        }

        appendUserMessage(clean);

        input.value = '';
        input.style.height = 'auto';
        isProcessing = true;
        updateInputState();

        if (typing) {
            typing.classList.remove('d-none');
            scrollToBottom();
        }

        const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = csrfTokenMeta ? csrfTokenMeta.getAttribute('content') : '';

        try {
            const response = await fetch(askUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ message: clean })
            });

            const data = await response.json();

            if (typing) typing.classList.add('d-none');

            if (response.ok && data.success) {
                appendBotMessage(data.reply || 'Tidak ada balasan.');
            } else {
                const errorMsg = data.reply || data.message || 'Mohon maaf, terjadi kendala saat memproses pertanyaan Anda.';
                appendBotMessage(errorMsg, true);
            }
        } catch (err) {
            console.error('[Chatbot+]', err);
            if (typing) typing.classList.add('d-none');
            appendBotMessage('Tidak dapat terhubung ke server SmartAttendance. Silakan periksa koneksi atau muat ulang halaman.', true);
        } finally {
            isProcessing = false;
            updateInputState();
            input.focus();
        }
    }
});
</script>
