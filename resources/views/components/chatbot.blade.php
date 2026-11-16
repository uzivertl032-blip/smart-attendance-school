{{-- Chatbot AI Floating Widget for Smart Attendance School --}}
<div id="smartbot-root" data-api-url="{{ config('services.chatbot.url', 'http://localhost:3001') }}/api/chat" data-health-url="{{ config('services.chatbot.url', 'http://localhost:3001') }}/api/health">
    {{-- Floating Action Button (FAB) --}}
    <button id="smartbot-toggle-btn" class="smartbot-fab shadow-lg" type="button" aria-label="Buka Chatbot AI Smart Attendance" title="Tanya Asisten AI Smart Attendance">
        <div class="smartbot-fab-inner">
            <i class="bi bi-chat-dots-fill smartbot-icon-open fs-4"></i>
            <i class="bi bi-chevron-down smartbot-icon-close fs-4 d-none"></i>
            <span class="smartbot-badge-pulse" title="Online"></span>
        </div>
    </button>

    {{-- Chatbot Panel --}}
    <div id="smartbot-panel" class="smartbot-panel shadow-lg d-none" role="dialog" aria-labelledby="smartbot-title" aria-modal="true">
        {{-- Header --}}
        <div class="smartbot-header">
            <div class="d-flex align-items-center gap-2">
                <div class="smartbot-avatar">
                    <i class="bi bi-robot fs-5 text-white"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-1.5">
                        <span id="smartbot-title" class="fw-bold text-white fs-6">SmartBot</span>
                        <span class="badge bg-info bg-opacity-25 text-info border border-info border-opacity-25 rounded-pill px-2 py-0.5" style="font-size: 0.65rem;">AI Asisten</span>
                    </div>
                    <div class="d-flex align-items-center gap-1 text-white-50" style="font-size: 0.75rem;">
                        <span class="smartbot-status-dot"></span>
                        <span id="smartbot-status-text">Siap Membantu</span>
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-1">
                <button type="button" id="smartbot-clear-btn" class="btn btn-sm btn-icon-custom text-white-50" title="Bersihkan Percakapan" aria-label="Bersihkan Percakapan">
                    <i class="bi bi-trash3"></i>
                </button>
                <button type="button" id="smartbot-close-btn" class="btn btn-sm btn-icon-custom text-white-50" title="Tutup Chat" aria-label="Tutup Chat">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>

        {{-- Conversation History / Body --}}
        <div id="smartbot-body" class="smartbot-body">
            {{-- Initial Bot Welcome Message --}}
            <div class="smartbot-msg smartbot-msg-bot">
                <div class="smartbot-msg-avatar">
                    <i class="bi bi-robot"></i>
                </div>
                <div class="smartbot-msg-content">
                    <div class="smartbot-msg-bubble">
                        Halo! Saya asisten Smart Attendance. Ada yang bisa saya bantu?
                    </div>
                    <span class="smartbot-msg-time" data-init-time>Baru saja</span>
                </div>
            </div>

            {{-- Quick Suggestion Chips --}}
            <div id="smartbot-suggestions" class="smartbot-suggestions">
                <div class="small text-muted mb-1.5 fw-semibold" style="font-size: 0.72rem;">Pertanyaan yang sering diajukan:</div>
                <div class="d-flex flex-wrap gap-1.5">
                    <button type="button" class="smartbot-chip" data-prompt="Bagaimana cara melakukan absensi mandiri dengan kamera?">
                        <i class="bi bi-camera me-1"></i> Cara Absensi
                    </button>
                    <button type="button" class="smartbot-chip" data-prompt="Kamera absensi saya tidak aktif di browser, bagaimana solusinya?">
                        <i class="bi bi-camera-video-off me-1"></i> Kamera Tidak Aktif
                    </button>
                    <button type="button" class="smartbot-chip" data-prompt="Verifikasi wajah saya selalu gagal, apa yang harus diperiksa?">
                        <i class="bi bi-person-x me-1"></i> Verifikasi Gagal
                    </button>
                    <button type="button" class="smartbot-chip" data-prompt="Jam berapa batas masuk dan toleransi keterlambatan?">
                        <i class="bi bi-clock me-1"></i> Jam Kehadiran
                    </button>
                    <button type="button" class="smartbot-chip" data-prompt="Bagaimana alur pengajuan izin atau sakit di sistem?">
                        <i class="bi bi-file-earmark-medical me-1"></i> Pengajuan Izin
                    </button>
                </div>
            </div>


            {{-- Typing Indicator --}}
            <div id="smartbot-typing" class="smartbot-msg smartbot-msg-bot d-none">
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

        {{-- Footer / Input Form --}}
        <div class="smartbot-footer">
            <form id="smartbot-form" class="m-0" onsubmit="return false;">
                <div class="input-group">
                    <textarea
                        id="smartbot-input"
                        class="form-control smartbot-input"
                        placeholder="Ketik pertanyaan Anda di sini... (Enter untuk kirim)"
                        rows="1"
                        maxlength="1000"
                        aria-label="Pesan untuk chatbot"
                    ></textarea>
                    <button id="smartbot-send-btn" class="btn btn-primary smartbot-send-btn" type="submit" disabled aria-label="Kirim Pesan">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-1 px-1">
                    <span class="text-muted" style="font-size: 0.68rem;">
                        <i class="bi bi-info-circle me-0.5"></i> Asisten umum sistem presensi
                    </span>
                    <span id="smartbot-char-count" class="text-muted" style="font-size: 0.68rem;">0/1000</span>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* SmartBot Styling matching Smart Attendance School theme */
#smartbot-root {
    font-family: inherit;
    position: static !important;
    transform: none !important;
    animation: none !important;
    filter: none !important;
    perspective: none !important;
    contain: none !important;
}

/* Floating Action Button */
.smartbot-fab {
    position: fixed !important;
    bottom: 24px !important;
    right: 24px !important;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4338ca 0%, #312e81 100%);
    color: #ffffff;
    border: 2px solid rgba(255, 255, 255, 0.2);
    cursor: pointer;
    z-index: 1060 !important;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    box-shadow: 0 10px 25px -5px rgba(67, 56, 202, 0.5), 0 8px 10px -6px rgba(67, 56, 202, 0.3);
}

.smartbot-fab:hover {
    transform: scale(1.08) translateY(-3px);
    background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
    box-shadow: 0 15px 30px -5px rgba(67, 56, 202, 0.6), 0 10px 12px -6px rgba(67, 56, 202, 0.4);
}

.smartbot-fab:active {
    transform: scale(0.96);
}

.smartbot-fab-inner {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}

.smartbot-badge-pulse {
    position: absolute;
    top: -4px;
    right: -4px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background-color: #10b981;
    border: 2px solid #ffffff;
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    animation: smartbot-pulse 2s infinite;
}

@keyframes smartbot-pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    }
    70% {
        box-shadow: 0 0 0 8px rgba(16, 185, 129, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
    }
}

/* Chatbot Panel */
.smartbot-panel {
    position: fixed !important;
    bottom: 92px !important;
    right: 24px !important;
    width: 380px;
    max-width: calc(100vw - 32px);
    height: 500px;
    max-height: min(520px, calc(100dvh - 110px));
    background: #ffffff;
    border-radius: 18px;
    border: 1px solid rgba(226, 232, 240, 0.95);
    z-index: 1065 !important;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 20px 45px -10px rgba(15, 23, 42, 0.25), 0 10px 20px -5px rgba(15, 23, 42, 0.1);
    animation: smartbot-slide-up 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes smartbot-slide-up {
    from {
        opacity: 0;
        transform: translateY(14px) scale(0.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Header */
.smartbot-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 55%, #2563eb 100%);
    padding: 0.9rem 1.15rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid rgba(255, 255, 255, 0.12);
}

.smartbot-avatar {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: linear-gradient(135deg, #2563eb, #06b6d4);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.smartbot-status-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background-color: #10b981;
    display: inline-block;
}

.btn-icon-custom {
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 8px;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff !important;
    transition: all 0.2s ease;
    cursor: pointer;
}

.btn-icon-custom:hover {
    background: rgba(255, 255, 255, 0.25);
    color: #ffffff !important;
    transform: scale(1.05);
}

/* Body / Chat History */
.smartbot-body {
    flex: 1;
    overflow-y: auto;
    padding: 1.15rem;
    background: #f8fafc;
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
    scroll-behavior: smooth;
}

.smartbot-body::-webkit-scrollbar {
    width: 6px;
}

.smartbot-body::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

/* Messages */
.smartbot-msg {
    display: flex;
    gap: 0.6rem;
    max-width: 88%;
}

.smartbot-msg-bot {
    align-self: flex-start;
}

.smartbot-msg-user {
    align-self: flex-end;
    flex-direction: row-reverse;
}

.smartbot-msg-avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4f46e5, #06b6d4);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    flex-shrink: 0;
    margin-top: 2px;
}

.smartbot-msg-content {
    display: flex;
    flex-direction: column;
}

.smartbot-msg-bubble {
    padding: 0.75rem 0.95rem;
    border-radius: 14px;
    font-size: 0.88rem;
    line-height: 1.45;
    word-break: break-word;
}

.smartbot-msg-bot .smartbot-msg-bubble {
    background: #ffffff;
    color: #1e293b;
    border: 1px solid #e2e8f0;
    border-top-left-radius: 4px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.03);
}

.smartbot-msg-user .smartbot-msg-bubble {
    background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
    color: #ffffff;
    border-top-right-radius: 4px;
    box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2);
}

.smartbot-msg-time {
    font-size: 0.65rem;
    color: #94a3b8;
    margin-top: 3px;
    padding: 0 4px;
}

.smartbot-msg-user .smartbot-msg-time {
    text-align: right;
}

/* Suggestions */
.smartbot-suggestions {
    background: #ffffff;
    border: 1px dashed #cbd5e1;
    border-radius: 12px;
    padding: 0.75rem;
}

.smartbot-chip {
    background: #f1f5f9;
    color: #334155;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    padding: 0.35rem 0.65rem;
    font-size: 0.75rem;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.15s ease;
}

.smartbot-chip:hover {
    background: #e0e7ff;
    color: #4338ca;
    border-color: #c7d2fe;
    transform: translateY(-1px);
}

/* Typing indicator */
.smartbot-typing-bubble {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    padding: 0.6rem 0.9rem;
    border-radius: 14px;
    border-top-left-radius: 4px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.03);
}

.smartbot-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: #6366f1;
    animation: smartbot-bounce 1.4s infinite ease-in-out both;
}

.smartbot-dot:nth-child(1) { animation-delay: -0.32s; }
.smartbot-dot:nth-child(2) { animation-delay: -0.16s; }

@keyframes smartbot-bounce {
    0%, 80%, 100% {
        transform: scale(0);
        opacity: 0.4;
    }
    40% {
        transform: scale(1);
        opacity: 1;
    }
}

/* Footer / Input */
.smartbot-footer {
    background: #ffffff;
    padding: 0.75rem 1rem 0.6rem;
    border-top: 1px solid #e2e8f0;
}

.smartbot-input {
    resize: none;
    border-radius: 10px 0 0 10px !important;
    font-size: 0.85rem;
    padding: 0.55rem 0.8rem;
    border: 1px solid #cbd5e1;
    line-height: 1.35;
    max-height: 80px;
}

.smartbot-input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

.smartbot-send-btn {
    border-radius: 0 10px 10px 0 !important;
    background: linear-gradient(135deg, #4f46e5, #4338ca);
    border: none;
    padding: 0.55rem 0.95rem;
    transition: all 0.2s ease;
}

.smartbot-send-btn:hover:not(:disabled) {
    background: linear-gradient(135deg, #4338ca, #3730a3);
    box-shadow: 0 2px 8px rgba(79, 70, 229, 0.3);
}

.smartbot-send-btn:disabled {
    background: #cbd5e1;
    border: none;
    opacity: 0.7;
    cursor: not-allowed;
}

/* Error callout in chat */
.smartbot-msg-error .smartbot-msg-bubble {
    background: #fef2f2 !important;
    color: #991b1b !important;
    border-color: #fecaca !important;
}

/* Mobile responsiveness: compact, non-intrusive floating modal */
@media (max-width: 576px) {
    .smartbot-panel {
        bottom: 76px !important;
        right: 12px !important;
        left: 12px !important;
        width: auto !important;
        max-width: calc(100vw - 24px) !important;
        height: auto !important;
        max-height: min(480px, 70vh) !important;
        border-radius: 16px !important;
        box-shadow: 0 16px 40px rgba(15, 23, 42, 0.28) !important;
    }

    .smartbot-fab {
        bottom: 16px;
        right: 16px;
        width: 52px;
        height: 52px;
    }
}
</style>

<script>
(function() {
    // Prevent multiple initializations if re-rendered
    if (window.__smartbotInitialized) return;
    window.__smartbotInitialized = true;

    document.addEventListener('DOMContentLoaded', function() {
        const root = document.getElementById('smartbot-root');
        if (!root) return;

        const apiUrl = root.getAttribute('data-api-url') || 'http://localhost:3001/api/chat';
        const toggleBtn = document.getElementById('smartbot-toggle-btn');
        const closeBtn = document.getElementById('smartbot-close-btn');
        const clearBtn = document.getElementById('smartbot-clear-btn');
        const panel = document.getElementById('smartbot-panel');
        const body = document.getElementById('smartbot-body');
        const input = document.getElementById('smartbot-input');
        const sendBtn = document.getElementById('smartbot-send-btn');
        const typingIndicator = document.getElementById('smartbot-typing');
        const charCount = document.getElementById('smartbot-char-count');
        const form = document.getElementById('smartbot-form');
        const iconOpen = toggleBtn.querySelector('.smartbot-icon-open');
        const iconClose = toggleBtn.querySelector('.smartbot-icon-close');
        const suggestionsBox = document.getElementById('smartbot-suggestions');

        // Conversation history in memory
        let conversationHistory = [];
        let isSending = false;

        // Helper: Format current time (HH:mm)
        function formatTime() {
            const now = new Date();
            const h = String(now.getHours()).padStart(2, '0');
            const m = String(now.getMinutes()).padStart(2, '0');
            return `${h}:${m}`;
        }

        // Initialize timestamps
        document.querySelectorAll('[data-init-time]').forEach(el => {
            el.textContent = formatTime();
        });

        // Toggle Open/Close
        function togglePanel(open) {
            const isCurrentlyOpen = !panel.classList.contains('d-none');
            const targetState = (typeof open === 'boolean') ? open : !isCurrentlyOpen;

            if (targetState) {
                panel.classList.remove('d-none');
                iconOpen.classList.add('d-none');
                iconClose.classList.remove('d-none');
                scrollToBottom();
                setTimeout(() => input.focus(), 150);
            } else {
                panel.classList.add('d-none');
                iconOpen.classList.remove('d-none');
                iconClose.classList.add('d-none');
            }
        }

        toggleBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            togglePanel();
        });
        closeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            togglePanel(false);
        });

        // Click outside to dismiss popup
        document.addEventListener('click', function(e) {
            if (!panel.classList.contains('d-none')) {
                if (!panel.contains(e.target) && !toggleBtn.contains(e.target)) {
                    togglePanel(false);
                }
            }
        });

        // ESC key to dismiss popup
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !panel.classList.contains('d-none')) {
                togglePanel(false);
            }
        });

        // Auto-scroll to bottom of conversation
        function scrollToBottom() {
            body.scrollTop = body.scrollHeight;
        }

        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Simple Markdown formatter (bold, lists, code, linebreaks)
        function formatMessageText(rawText) {
            let escaped = escapeHtml(rawText);
            // Bold: **text**
            escaped = escaped.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            // Bullet points
            escaped = escaped.replace(/^[\*\-]\s+(.*)$/gm, '• $1');
            // Line breaks
            escaped = escaped.replace(/\n/g, '<br>');
            return escaped;
        }

        // Append a message bubble to conversation
        function appendMessage(sender, text, isError = false) {
            const msgEl = document.createElement('div');
            msgEl.className = `smartbot-msg smartbot-msg-${sender} ${isError ? 'smartbot-msg-error' : ''}`;

            const avatarHtml = (sender === 'bot')
                ? `<div class="smartbot-msg-avatar"><i class="bi bi-robot"></i></div>`
                : '';

            const contentHtml = `
                <div class="smartbot-msg-content">
                    <div class="smartbot-msg-bubble">
                        ${formatMessageText(text)}
                    </div>
                    <span class="smartbot-msg-time">${formatTime()}</span>
                </div>
            `;

            msgEl.innerHTML = avatarHtml + contentHtml;

            // Insert before typing indicator
            body.insertBefore(msgEl, typingIndicator);
            scrollToBottom();
        }

        // Character count and input validation
        input.addEventListener('input', function() {
            const len = input.value.length;
            charCount.textContent = `${len}/1000`;
            sendBtn.disabled = isSending || input.value.trim().length === 0;

            // Auto-resize up to 80px
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 80) + 'px';
        });

        // Enter key to send (Shift+Enter for newline)
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (!sendBtn.disabled) {
                    sendMessage();
                }
            }
        });

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            if (!sendBtn.disabled) {
                sendMessage();
            }
        });

        // Quick suggestions click handler
        document.querySelectorAll('.smartbot-chip').forEach(btn => {
            btn.addEventListener('click', function() {
                const prompt = this.getAttribute('data-prompt');
                if (prompt && !isSending) {
                    input.value = prompt;
                    input.dispatchEvent(new Event('input'));
                    sendMessage();
                }
            });
        });

        // Send message function
        async function sendMessage() {
            const userText = input.value.trim();
            if (!userText || isSending) return;

            // Append user bubble
            appendMessage('user', userText);

            // Record in history
            conversationHistory.push({ role: 'user', content: userText });

            // Reset input
            input.value = '';
            input.style.height = 'auto';
            charCount.textContent = '0/1000';
            sendBtn.disabled = true;
            isSending = true;

            // Hide suggestions after first query to keep chat clean
            if (suggestionsBox) {
                suggestionsBox.classList.add('d-none');
            }

            // Show typing indicator
            typingIndicator.classList.remove('d-none');
            scrollToBottom();

            try {
                const response = await fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        message: userText,
                        history: conversationHistory.slice(-6), // keep last turns
                    }),
                });

                let data = null;
                try {
                    data = await response.json();
                } catch (jsonErr) {
                    // response is not JSON
                }

                typingIndicator.classList.add('d-none');

                if (response.ok && data && data.success && data.reply) {
                    appendMessage('bot', data.reply);
                    conversationHistory.push({ role: 'assistant', content: data.reply });
                } else if (data && data.message) {
                    appendMessage('bot', `⚠️ ${data.message}`, true);
                } else {
                    const fallbackError = `Terjadi kendala pada layanan chatbot (HTTP ${response.status}).`;
                    appendMessage('bot', `⚠️ ${fallbackError}`, true);
                }
            } catch (err) {
                typingIndicator.classList.add('d-none');
                console.error('[SmartBot Client Error]:', err);
                appendMessage(
                    'bot',
                    '⚠️ Layanan chatbot belum dapat dihubungi. Pastikan backend chatbot-service sudah dijalankan di port 3001 atau hubungi administrator sekolah.',
                    true
                );
            } finally {
                isSending = false;
                sendBtn.disabled = input.value.trim().length === 0;
                input.focus();
                scrollToBottom();
            }
        }

        // Clear conversation
        clearBtn.addEventListener('click', function() {
            conversationHistory = [];
            // Remove all messages except the welcome and typing indicator
            const messages = body.querySelectorAll('.smartbot-msg');
            messages.forEach((msg, idx) => {
                if (idx > 0 && msg.id !== 'smartbot-typing') {
                    msg.remove();
                }
            });
            if (suggestionsBox) {
                suggestionsBox.classList.remove('d-none');
            }
            scrollToBottom();
        });
    });
})();
</script>
