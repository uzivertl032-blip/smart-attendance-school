@extends('layouts.app')

@section('title', 'Kiosk Presensi & Verifikasi Wajah')

@section('content')
<div class="row g-4 align-items-start py-2">
    <!-- Camera Scanner / Kiosk Area -->
    <div class="col-12 col-lg-7">
        <div class="card card-custom p-4 border-0 shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary p-2 rounded-3">
                        <i class="bi bi-camera-fill text-white fs-6"></i>
                    </span>
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">Kamera Pemindai Wajah</h5>
                        <small class="text-muted">Arahkan wajah Anda ke bingkai kamera</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-secondary border px-2 py-1 small d-none d-md-inline-block">
                        <i class="bi bi-clock me-1 text-primary"></i> Masuk: {{ $openingTime ?? '06:30' }} - {{ $lateAfter ?? '07:15' }} WIB
                    </span>
                    <span id="camPillStatus" class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2 rounded-pill">
                        <i class="bi bi-circle-fill me-1" style="font-size: 0.45rem;"></i> Menghubungkan Kamera...
                    </span>
                </div>
            </div>

            <!-- Viewfinder Container -->
            <div class="position-relative bg-dark rounded-4 overflow-hidden shadow-inner" style="min-height: 380px; aspect-ratio: 4/3;">
                {{-- Video Element --}}
                <video id="webcam" autoplay playsinline muted class="w-100 h-100 object-fit-cover" style="transform: scaleX(-1);"></video>

                {{-- Canvas Overlay --}}
                <canvas id="overlayCanvas" class="position-absolute top-0 start-0 w-100 h-100" style="pointer-events: none; transform: scaleX(-1);"></canvas>

                {{-- Scanner Oval & Corner Overlay --}}
                <div id="scannerOverlay" class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center pointer-events-none">
                    <div id="scannerBox" class="scanner-box position-relative d-flex align-items-center justify-content-center"
                         style="width: 230px; height: 280px; border: 2px solid #38bdf8; border-radius: 24px; background: rgba(56, 189, 248, 0.04); transition: all 0.3s ease;">
                        <div class="scanner-corner top-left"></div>
                        <div class="scanner-corner top-right"></div>
                        <div class="scanner-corner bottom-left"></div>
                        <div class="scanner-corner bottom-right"></div>

                        <div class="text-center p-3">
                            <i id="scannerIcon" class="bi bi-person-bounding-box display-4 text-info opacity-75"></i>
                            <p id="scannerGuideText" class="small text-light mt-2 mb-0 fw-medium">Posisikan Wajah di Sini</p>
                        </div>
                    </div>
                </div>

                {{-- Camera Error Overlay --}}
                <div id="cameraErrorOverlay" class="position-absolute top-0 start-0 w-100 h-100 d-none flex-column align-items-center justify-content-center bg-dark p-4 text-center text-white" style="z-index: 10; overflow-y: auto;">
                    <i id="cameraErrorIcon" class="bi bi-camera-video-off display-2 text-danger mb-2"></i>
                    <h5 id="cameraErrorTitle" class="fw-bold mb-1 text-danger">Kamera Tidak Tersedia</h5>
                    <p id="cameraErrorMessage" class="small text-white-75 mb-3" style="max-width: 440px;">
                        Akses kamera tidak diizinkan atau perangkat kamera tidak ditemukan.
                    </p>

                    {{-- Insecure Context Action Box (Chrome HTTP .test restriction) --}}
                    <div id="insecureContextNoticeKiosk" class="d-none bg-dark bg-opacity-75 border border-warning rounded-3 p-3 text-start mb-3" style="max-width: 480px; font-size: 0.8rem;">
                        <div class="d-flex align-items-center gap-2 text-warning fw-bold mb-1">
                            <i class="bi bi-shield-exclamation fs-5"></i>
                            <span>Google Chrome Membutuhkan Secure Context</span>
                        </div>
                        <p class="text-white-50 mb-2">
                            Akses webcam pada <code id="insecureOriginLabelKiosk" class="text-warning bg-black px-1 rounded">{{ request()->getSchemeAndHttpHost() }}</code> diblokir otomatis oleh Chrome karena menggunakan protokol HTTP.
                        </p>
                        <div class="fw-semibold text-white mb-1">Solusi Cepat di Google Chrome:</div>
                        <ol class="text-white-50 ps-3 mb-2" style="font-size: 0.77rem;">
                            <li>Buka tab baru: <code class="text-info user-select-all">chrome://flags/#unsafely-treat-insecure-origin-as-secure</code></li>
                            <li>Tempelkan origin: <code class="text-warning user-select-all">{{ request()->getSchemeAndHttpHost() }}</code></li>
                            <li>Ubah status menjadi <strong>Enabled</strong>, lalu klik tombol <strong>Relaunch</strong>.</li>
                        </ol>
                        <div class="d-flex gap-2 flex-wrap mb-2">
                            <button type="button" id="btnCopyOriginKiosk" class="btn btn-warning btn-sm fw-semibold rounded-pill px-3">
                                <i class="bi bi-clipboard-check me-1"></i> Salin URL Origin
                            </button>
                            <a id="btnHttpsLinkKiosk" href="{{ 'https://' . request()->getHost() . request()->getRequestUri() }}" class="btn btn-outline-light btn-sm rounded-pill px-3">
                                <i class="bi bi-lock-fill me-1"></i> Buka via HTTPS
                            </a>
                        </div>
                        <div id="copySuccessToastKiosk" class="text-success small d-none">
                            <i class="bi bi-check-circle-fill me-1"></i> URL Origin berhasil disalin ke clipboard!
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" id="btnOverlayRetryCamera" class="btn btn-primary btn-sm rounded-pill px-4 py-2 fw-semibold">
                            <i class="bi bi-arrow-clockwise me-1"></i> Coba Hubungkan Ulang
                        </button>
                    </div>
                </div>
            </div>

            {{-- Camera Controls & Multi-Device Selector Bar --}}
            <div class="mt-3 p-2.5 px-3 bg-light rounded-3 border d-flex flex-wrap align-items-center justify-content-between gap-2 shadow-sm">
                <div class="d-flex align-items-center gap-2 flex-grow-1">
                    <i class="bi bi-camera-video-fill text-primary fs-5"></i>
                    <label for="cameraSelect" class="form-label mb-0 small fw-semibold text-nowrap text-dark">Pilih Kamera:</label>
                    <select id="cameraSelect" class="form-select form-select-sm border-secondary-subtle" style="max-width: 320px;">
                        <option value="">Memuat perangkat kamera...</option>
                    </select>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" id="btnRetryCamera" class="btn btn-sm btn-outline-primary rounded-2 px-3 fw-semibold">
                        <i class="bi bi-arrow-clockwise me-1"></i> Hubungkan Ulang
                    </button>
                </div>
            </div>

            <!-- Instructions Step -->
            <div class="row g-2 mt-3 pt-2 text-center text-md-start">
                <div class="col-12 col-md-4">
                    <div class="p-2 px-3 rounded-3 bg-light border">
                        <div class="fw-bold text-primary small">1. Dekati Kamera</div>
                        <small class="text-muted" style="font-size: 0.75rem;">Pastikan pencahayaan cukup</small>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="p-2 px-3 rounded-3 bg-light border">
                        <div class="fw-bold text-primary small">2. Verifikasi Wajah</div>
                        <small class="text-muted" style="font-size: 0.75rem;">Sistem mengenali identitas Anda</small>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="p-2 px-3 rounded-3 bg-light border">
                        <div class="fw-bold text-primary small">3. Pilih Status</div>
                        <small class="text-muted" style="font-size: 0.75rem;">Hadir, Izin, atau Sakit</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Live Status & Verification Result Panel -->
    <div class="col-12 col-lg-5">
        <!-- Live Clock Card -->
        <div class="card card-custom p-4 border-0 shadow-sm mb-4 text-white" style="background: linear-gradient(135deg, #1e1b4b, #312e81) !important;">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="text-white-50 small text-uppercase fw-bold tracking-wide">Waktu Sistem Absensi</span>
                <i class="bi bi-clock-history fs-4 text-white-50"></i>
            </div>
            <div class="display-5 fw-bold mb-1" id="liveClock">--:--:--</div>
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-white-50 small" id="liveDate">{{ \Carbon\Carbon::now()->translatedFormat('l, d F Y') }}</div>
                <span class="badge bg-white bg-opacity-25 text-white small">
                    Batas: {{ $lateAfter ?? '07:15' }} WIB
                </span>
            </div>
        </div>

        <!-- Verification Result Dynamic Card -->
        <div class="card card-custom p-4 border-0 shadow-sm mb-4">
            <h6 class="fw-bold text-dark mb-3">
                <i class="bi bi-shield-check text-primary me-2"></i>Hasil Presensi Wajah
            </h6>

            {{-- Dynamic Status Card Container --}}
            <div id="verificationResultCard" class="p-4 rounded-4 bg-light border text-center transition-all">
                <div class="mb-3">
                    <span id="resultBadge" class="badge bg-secondary-subtle text-secondary border px-3 py-2 fs-6 rounded-pill">
                        <i class="bi bi-camera me-1"></i> Menunggu Deteksi...
                    </span>
                </div>

                <div id="resultIconWrapper" class="rounded-circle bg-secondary-subtle text-secondary d-flex align-items-center justify-content-center mx-auto mb-3" style="width:72px;height:72px;font-size:2rem;">
                    <i class="bi bi-person-bounding-box"></i>
                </div>

                <h5 id="resultTitle" class="fw-bold text-dark mb-1">Posisikan Wajah Anda</h5>
                <p id="resultMessage" class="text-muted small mb-3">Sistem akan melakukan verifikasi biometrik dan mencatat absensi.</p>

                {{-- Verified Identity Info Box (shown after face verified) --}}
                <div id="profileInfoBox" class="p-3 bg-white rounded-3 border d-none text-start shadow-sm mt-3">
                    <div class="row g-2">
                        <div class="col-6">
                            <span class="text-muted extra-small">Nama:</span>
                            <div id="profileName" class="fw-bold text-dark small">—</div>
                        </div>
                        <div class="col-6">
                            <span class="text-muted extra-small">Status Kehadiran:</span>
                            <div id="profileStatus">
                                <span class="badge bg-success small">Hadir</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <span class="text-muted extra-small">Tanggal:</span>
                            <div id="profileDate" class="fw-bold text-dark small">—</div>
                        </div>
                        <div class="col-6">
                            <span class="text-muted extra-small">Waktu Masuk:</span>
                            <div id="profileTime" class="fw-bold text-primary small">—</div>
                        </div>
                    </div>
                </div>

                {{-- Status Selection Panel (shown after face verified, before recording) --}}
                <div id="statusSelectionPanel" class="d-none mt-3">
                    <div class="alert alert-info py-2 px-3 mb-3 text-start small">
                        <i class="bi bi-person-check-fill me-1"></i>
                        <strong id="verifiedUserName">Wajah Terverifikasi!</strong> Silakan pilih status kehadiran Anda.
                    </div>
                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                        <button id="btnHadir" class="btn btn-success btn-sm px-4 py-2 rounded-pill fw-semibold" type="button">
                            <i class="bi bi-check-circle-fill me-1"></i> Hadir
                        </button>
                        <button id="btnIzin" class="btn btn-warning btn-sm px-4 py-2 rounded-pill fw-semibold" type="button">
                            <i class="bi bi-envelope-fill me-1"></i> Izin
                        </button>
                        <button id="btnSakit" class="btn btn-danger btn-sm px-4 py-2 rounded-pill fw-semibold" type="button">
                            <i class="bi bi-bandaid-fill me-1"></i> Sakit
                        </button>
                    </div>
                    <div class="mt-2">
                        <button id="btnCancelScan" class="btn btn-link btn-sm text-muted" type="button">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Pindai Ulang
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Logs Card -->
        <div class="card card-custom p-4 border-0 shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="bi bi-journal-check text-primary me-2"></i> Log Presensi Hari Ini
                </h6>
                <span id="recentLogCount" class="badge bg-primary-subtle text-primary">{{ $todayAttendances->count() }} Tercatat</span>
            </div>

            <div id="recentLogEmptyNotice" class="text-center py-4 text-muted {{ $todayAttendances->isEmpty() ? '' : 'd-none' }}">
                <i class="bi bi-calendar-x fs-2 text-secondary d-block mb-2"></i>
                <p class="small mb-0">Belum ada data presensi hari ini.</p>
            </div>

            <div id="recentLogContainer" class="list-group list-group-flush small">
                @foreach($todayAttendances as $att)
                    <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold text-dark">{{ $att->user->name ?? '-' }}</div>
                            <small class="text-muted">
                                {{ $att->user->role === 'teacher' ? 'Guru' : 'Siswa' }} &bull; {{ $att->attendance_time }}
                            </small>
                        </div>
                        <span class="badge {{ $att->status === 'late' ? 'bg-warning-subtle text-warning border border-warning-subtle' : ($att->status === 'excused' ? 'bg-info-subtle text-info border border-info-subtle' : ($att->status === 'sick' ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-success-subtle text-success border border-success-subtle')) }}">
                            {{ $att->status === 'late' ? 'Terlambat' : ($att->status === 'excused' ? 'Izin' : ($att->status === 'sick' ? 'Sakit' : 'Hadir')) }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

{{-- Leave Reason Modal (for Izin / Sakit) --}}
<div class="modal fade" id="leaveReasonModal" tabindex="-1" aria-labelledby="leaveReasonModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="leaveReasonModalLabel">
                    <i class="bi bi-envelope-fill text-warning me-2"></i>
                    <span id="leaveModalTitle">Pengajuan Izin</span>
                </h5>
                <button type="button" id="btnCloseLeaveModal" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="text-muted small mb-3">Untuk <strong id="leaveModalUserName">Anda</strong>, silakan isi alasan pengajuan.</p>
                <div class="mb-3">
                    <label for="leaveReasonInput" class="form-label fw-semibold small">Alasan <span class="text-danger">*</span></label>
                    <textarea id="leaveReasonInput" class="form-control rounded-3" rows="3" placeholder="Tulis alasan izin/sakit minimal 5 karakter..." maxlength="500"></textarea>
                    <div id="leaveReasonError" class="text-danger small mt-1 d-none">Alasan wajib diisi minimal 5 karakter.</div>
                </div>
                <div class="mb-3">
                    <label for="leaveAttendanceDateInput" class="form-label fw-semibold small">Tanggal <span class="text-muted fw-normal">(opsional, default: hari ini)</span></label>
                    <input type="date" id="leaveAttendanceDateInput" class="form-control rounded-3" value="{{ \Carbon\Carbon::today()->toDateString() }}">
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                <button type="button" id="btnSubmitLeave" class="btn btn-warning rounded-pill px-4 fw-semibold">
                    <i class="bi bi-send-fill me-1"></i> Ajukan
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .scanner-corner {
        position: absolute;
        width: 18px;
        height: 18px;
        border-color: #38bdf8;
        border-style: solid;
        transition: all 0.3s ease;
    }
    .scanner-corner.top-left {
        top: -2px;
        left: -2px;
        border-width: 3px 0 0 3px;
        border-top-left-radius: 8px;
    }
    .scanner-corner.top-right {
        top: -2px;
        right: -2px;
        border-width: 3px 3px 0 0;
        border-top-right-radius: 8px;
    }
    .scanner-corner.bottom-left {
        bottom: -2px;
        left: -2px;
        border-width: 0 0 3px 3px;
        border-bottom-left-radius: 8px;
    }
    .scanner-corner.bottom-right {
        bottom: -2px;
        right: -2px;
        border-width: 0 3px 3px 0;
        border-bottom-right-radius: 8px;
    }
    .extra-small {
        font-size: 0.72rem;
        display: block;
    }
    .transition-all {
        transition: all 0.25s ease-in-out;
    }
    #statusSelectionPanel .btn {
        font-size: 0.85rem;
        min-width: 90px;
    }
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/camera-helper.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const video = document.getElementById('webcam');
    const overlayCanvas = document.getElementById('overlayCanvas');
    const camPillStatus = document.getElementById('camPillStatus');
    const scannerBox = document.getElementById('scannerBox');
    const scannerIcon = document.getElementById('scannerIcon');
    const scannerGuideText = document.getElementById('scannerGuideText');

    const cameraSelect = document.getElementById('cameraSelect');
    const cameraErrorOverlay = document.getElementById('cameraErrorOverlay');
    const cameraErrorIcon = document.getElementById('cameraErrorIcon');
    const cameraErrorTitle = document.getElementById('cameraErrorTitle');
    const cameraErrorMessage = document.getElementById('cameraErrorMessage');
    const btnRetryCamera = document.getElementById('btnRetryCamera');
    const btnOverlayRetryCamera = document.getElementById('btnOverlayRetryCamera');
    const insecureContextNoticeKiosk = document.getElementById('insecureContextNoticeKiosk');
    const btnCopyOriginKiosk = document.getElementById('btnCopyOriginKiosk');
    const copySuccessToastKiosk = document.getElementById('copySuccessToastKiosk');

    const resultCard = document.getElementById('verificationResultCard');
    const resultBadge = document.getElementById('resultBadge');
    const resultIconWrapper = document.getElementById('resultIconWrapper');
    const resultTitle = document.getElementById('resultTitle');
    const resultMessage = document.getElementById('resultMessage');

    const profileInfoBox = document.getElementById('profileInfoBox');
    const profileName = document.getElementById('profileName');
    const profileStatus = document.getElementById('profileStatus');
    const profileDate = document.getElementById('profileDate');
    const profileTime = document.getElementById('profileTime');

    const statusSelectionPanel = document.getElementById('statusSelectionPanel');
    const verifiedUserName = document.getElementById('verifiedUserName');
    const btnHadir = document.getElementById('btnHadir');
    const btnIzin = document.getElementById('btnIzin');
    const btnSakit = document.getElementById('btnSakit');
    const btnCancelScan = document.getElementById('btnCancelScan');

    const recentLogContainer = document.getElementById('recentLogContainer');
    const recentLogEmptyNotice = document.getElementById('recentLogEmptyNotice');
    const recentLogCount = document.getElementById('recentLogCount');

    // Leave reason modal elements
    const leaveReasonModal = new bootstrap.Modal(document.getElementById('leaveReasonModal'));
    const leaveModalTitle = document.getElementById('leaveModalTitle');
    const leaveModalUserName = document.getElementById('leaveModalUserName');
    const leaveReasonInput = document.getElementById('leaveReasonInput');
    const leaveReasonError = document.getElementById('leaveReasonError');
    const leaveAttendanceDateInput = document.getElementById('leaveAttendanceDateInput');
    const btnSubmitLeave = document.getElementById('btnSubmitLeave');
    const btnCloseLeaveModal = document.getElementById('btnCloseLeaveModal');

    let stream = null;
    let scanLoop = null;
    let isVerifying = false;     // True while calling /verify or /record
    let isFaceVerified = false;  // True after /verify returns MATCH — scanner paused for status selection
    let lastVerifyTime = 0;
    let pendingVerificationToken = null;
    let pendingLeaveStatus = null; // 'excused' | 'sick'
    const VERIFY_DEBOUNCE_MS = 2500;

    // ─── 1. Live Clock ───────────────────────────────────────────────────────
    function updateLiveClock() {
        const now = new Date();
        const el = document.getElementById('liveClock');
        if (el) {
            el.textContent = [now.getHours(), now.getMinutes(), now.getSeconds()]
                .map(v => String(v).padStart(2, '0')).join(':');
        }
    }
    setInterval(updateLiveClock, 1000);
    updateLiveClock();

    // Helper: Copy Origin for Chrome Flags
    if (btnCopyOriginKiosk) {
        btnCopyOriginKiosk.addEventListener('click', function () {
            const originText = window.location.origin;
            const copyCallback = () => {
                if (copySuccessToastKiosk) {
                    copySuccessToastKiosk.classList.remove('d-none');
                    setTimeout(() => copySuccessToastKiosk.classList.add('d-none'), 4000);
                }
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(originText).then(copyCallback).catch(() => {
                    prompt('Salin URL berikut untuk chrome://flags:', originText);
                });
            } else {
                prompt('Salin URL berikut untuk chrome://flags:', originText);
            }
        });
    }

    // ─── 2. Camera Initialization with Unified Camera Helper ──────────────────
    async function startKioskCamera(preferredDeviceId = null) {
        if (scanLoop) {
            clearInterval(scanLoop);
            scanLoop = null;
        }

        cameraErrorOverlay.classList.add('d-none');
        cameraErrorOverlay.classList.remove('d-flex');
        if (insecureContextNoticeKiosk) {
            insecureContextNoticeKiosk.classList.add('d-none');
        }
        resetToScanningState();

        camPillStatus.className = 'badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2 rounded-pill';
        camPillStatus.innerHTML = '<i class="bi bi-circle-fill me-1" style="font-size: 0.45rem;"></i> Menghubungkan Kamera...';

        try {
            const result = await window.schoolCamera.initializeCamera(video, preferredDeviceId);
            if (!result) return;

            stream = result.stream;

            overlayCanvas.width = video.videoWidth || 640;
            overlayCanvas.height = video.videoHeight || 480;

            camPillStatus.className = 'badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill';
            camPillStatus.innerHTML = '<i class="bi bi-circle-fill me-1" style="font-size: 0.45rem;"></i> Kamera Kiosk Aktif';

            // Populate multi-camera dropdown
            if (cameraSelect) {
                await window.schoolCamera.setupCameraSelect(cameraSelect, (selectedDeviceId) => {
                    startKioskCamera(selectedDeviceId);
                });
            }

            startScannerLoop();
        } catch (err) {
            console.error('Kiosk Camera Error:', err);
            camPillStatus.className = 'badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 rounded-pill';
            camPillStatus.innerHTML = '<i class="bi bi-x-circle-fill me-1"></i> Kamera Tidak Aktif';

            const errInfo = window.schoolCamera.parseError(err);
            renderKioskCameraError(errInfo);
            updateUIResult('CAMERA_ERROR', errInfo.message);
        }
    }

    function renderKioskCameraError(errInfo) {
        if (cameraErrorTitle) cameraErrorTitle.textContent = errInfo.title;
        if (cameraErrorMessage) {
            let msg = errInfo.message;
            if (errInfo.details) msg += ' ' + errInfo.details;
            cameraErrorMessage.textContent = msg;
        }

        if (cameraErrorIcon) {
            if (errInfo.code === 'INSECURE_CONTEXT') {
                cameraErrorIcon.className = 'bi bi-shield-exclamation display-2 text-warning mb-2';
            } else if (errInfo.code === 'DEVICE_IN_USE') {
                cameraErrorIcon.className = 'bi bi-exclamation-triangle-fill display-2 text-warning mb-2';
            } else if (errInfo.code === 'PERMISSION_DENIED') {
                cameraErrorIcon.className = 'bi bi-slash-circle display-2 text-danger mb-2';
            } else {
                cameraErrorIcon.className = 'bi bi-camera-video-off display-2 text-danger mb-2';
            }
        }

        if (errInfo.code === 'INSECURE_CONTEXT' && insecureContextNoticeKiosk) {
            insecureContextNoticeKiosk.classList.remove('d-none');
        }

        cameraErrorOverlay.classList.remove('d-none');
        cameraErrorOverlay.classList.add('d-flex');
    }

    if (btnRetryCamera) {
        btnRetryCamera.addEventListener('click', function () {
            const targetId = cameraSelect ? cameraSelect.value : null;
            startKioskCamera(targetId || null);
        });
    }

    if (btnOverlayRetryCamera) {
        btnOverlayRetryCamera.addEventListener('click', function () {
            const targetId = cameraSelect ? cameraSelect.value : null;
            startKioskCamera(targetId || null);
        });
    }

    window.addEventListener('beforeunload', function () {
        if (window.schoolCamera) {
            window.schoolCamera.stopActiveStream();
        }
    });

    // ─── 3. Scanner Loop ─────────────────────────────────────────────────────
    function startScannerLoop() {
        if (scanLoop) clearInterval(scanLoop);
        const ctx = overlayCanvas.getContext('2d');

        scanLoop = setInterval(() => {
            // Pause scanning while face is verified and awaiting user action
            if (!video.videoWidth || isVerifying || isFaceVerified) return;

            ctx.clearRect(0, 0, overlayCanvas.width, overlayCanvas.height);

            const detectCanvas = document.createElement('canvas');
            detectCanvas.width = 160;
            detectCanvas.height = 120;
            const dCtx = detectCanvas.getContext('2d');
            dCtx.drawImage(video, 0, 0, detectCanvas.width, detectCanvas.height);
            const imgData = dCtx.getImageData(0, 0, detectCanvas.width, detectCanvas.height);

            const faceRegions = detectSkinAndFaceBlob(imgData, detectCanvas.width, detectCanvas.height);
            const facesCount = faceRegions.length;
            const now = Date.now();

            if (facesCount === 0) {
                setScannerBoxState('neutral', 'Posisikan Wajah di Sini');
                updateUIResult('NO_FACE', 'Silakan posisikan wajah di depan kamera.');
            } else if (facesCount > 1) {
                setScannerBoxState('danger', '⚠️ Banyak Wajah!');
                faceRegions.forEach(r => drawBox(ctx, r, '#dc3545', 'Ganda'));
                updateUIResult('MULTIPLE_FACES', 'Pastikan hanya satu wajah berada di depan kamera.');
            } else {
                const r = faceRegions[0];
                drawBox(ctx, r, '#0284c7', 'Wajah Terdeteksi');
                setScannerBoxState('active', 'Memindai Biometrik...');

                if (!isVerifying && (now - lastVerifyTime > VERIFY_DEBOUNCE_MS)) {
                    lastVerifyTime = now;
                    sendFaceForVerification(r, facesCount);
                }
            }
        }, 200);
    }

    // ─── 4. Step 1: Verify face identity (POST /attendance/verify) ────────────
    async function sendFaceForVerification(faceBox, facesCount) {
        isVerifying = true;
        showVerificationLoadingState();

        try {
            const descriptorPayload = generateBiometricDescriptor(video, faceBox);

            const response = await fetch('{{ route("attendance.verify") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    descriptor: descriptorPayload,
                    faces_detected: facesCount,
                    liveness_metadata: {
                        captured_at: descriptorPayload.captured_at,
                        variance: 0.05
                    }
                })
            });

            const data = await response.json();
            handleVerifyResponse(data);

        } catch (err) {
            console.error('Verify API Error:', err);
            updateUIResult('FAILED', 'Terjadi kesalahan jaringan saat memverifikasi wajah.');
        } finally {
            // Only release scan lock if face was not verified (stay locked if MATCH)
            if (!isFaceVerified) {
                setTimeout(() => { isVerifying = false; }, 1500);
            } else {
                isVerifying = false;
            }
        }
    }

    // Handle the /verify response
    function handleVerifyResponse(data) {
        if (data.status === 'MATCH' && data.user) {
            // Face matched — lock scanner and show status selection
            isFaceVerified = true;
            pendingVerificationToken = data.verification_token;

            if (data.already_attended && data.existing_attendance) {
                // Already has a record today
                const att = data.existing_attendance;
                showAlreadyAttendedUI(data.user, att);
            } else {
                // New attendance — show status selection
                showStatusSelectionUI(data);
            }
        } else if (data.status === 'LIVENESS_FAILED') {
            updateUIResult('FAILED', data.message || 'Verifikasi ditolak oleh sistem keamanan.');
        } else if (data.status === 'NO_FACE') {
            updateUIResult('NO_FACE', 'Silakan posisikan wajah di depan kamera.');
        } else if (data.status === 'MULTIPLE_FACES') {
            updateUIResult('MULTIPLE_FACES', 'Pastikan hanya satu wajah berada di depan kamera.');
        } else if (data.status === 'NOT_REGISTERED') {
            updateUIResult('FAILED', data.message || 'Wajah tidak terdaftar dalam sistem.');
        } else {
            updateUIResult('FAILED', data.message || 'Verifikasi gagal. Silakan coba lagi.');
        }
    }

    // Show identity confirmed + status selection buttons
    function showStatusSelectionUI(data) {
        const user = data.user;

        resultCard.className = 'p-4 rounded-4 border text-center transition-all bg-primary-subtle border-primary';
        resultBadge.className = 'badge bg-primary text-white px-3 py-2 fs-6 rounded-pill';
        resultBadge.innerHTML = '<i class="bi bi-person-check-fill me-1"></i> Identitas Terverifikasi';

        resultIconWrapper.className = 'rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto mb-3';
        resultIconWrapper.style.width = '72px';
        resultIconWrapper.style.height = '72px';
        resultIconWrapper.style.fontSize = '2rem';
        resultIconWrapper.innerHTML = '<i class="bi bi-person-check-fill"></i>';

        resultTitle.textContent = user.name;
        resultMessage.textContent = (user.role === 'teacher' ? 'Guru' : 'Siswa') + (data.person_class && data.person_class !== '-' ? ' · ' + data.person_class : '') + (data.person_code ? ' · ' + data.person_code : '');

        profileInfoBox.classList.add('d-none');

        // Show status selection
        verifiedUserName.textContent = user.name;
        statusSelectionPanel.classList.remove('d-none');

        setScannerBoxState('success', '✓ Wajah Terverifikasi');
    }

    // Show already-attended result
    function showAlreadyAttendedUI(user, att) {
        isFaceVerified = false; // Release lock so scanner can resume after timeout
        const isLate = att.status === 'late';

        resultCard.className = 'p-4 rounded-4 border text-center transition-all bg-info-subtle border-info-subtle';
        resultBadge.className = 'badge bg-info text-dark px-3 py-2 fs-6 rounded-pill';
        resultBadge.innerHTML = '<i class="bi bi-info-circle-fill me-1"></i> Sudah Absen Hari Ini';

        resultIconWrapper.className = 'rounded-circle bg-info text-dark d-flex align-items-center justify-content-center mx-auto mb-3';
        resultIconWrapper.style.width = '72px';
        resultIconWrapper.style.height = '72px';
        resultIconWrapper.style.fontSize = '2rem';
        resultIconWrapper.innerHTML = '<i class="bi bi-calendar2-check-fill"></i>';

        resultTitle.textContent = user.name;
        resultMessage.textContent = 'Anda sudah melakukan absensi hari ini.';

        profileName.textContent = att.name || user.name;
        profileStatus.innerHTML = `<span class="badge ${isLate ? 'bg-warning text-dark' : 'bg-success'} small px-2 py-1">${att.status_label || (isLate ? 'Terlambat' : 'Hadir')}</span>`;
        profileDate.textContent = att.date_formatted || att.date;
        profileTime.textContent = (att.time || '') + ' WIB';
        profileInfoBox.classList.remove('d-none');
        statusSelectionPanel.classList.add('d-none');

        setScannerBoxState('active', 'ℹ Sudah Absen');

        // Auto-resume scanning after 5s
        setTimeout(() => resumeScanning(), 5000);
    }

    // ─── 5. Step 2: Record attendance (POST /attendance/record) ───────────────
    async function submitAttendance(status, reason = null, attendanceDate = null) {
        showRecordingLoadingState(status);

        try {
            const body = {
                verification_token: pendingVerificationToken,
                status: status,
            };
            if (reason) body.reason = reason;
            if (attendanceDate) body.attendance_date = attendanceDate;

            const response = await fetch('{{ route("attendance.record") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(body)
            });

            const data = await response.json();
            handleRecordResponse(data);

        } catch (err) {
            console.error('Record API Error:', err);
            updateUIResult('FAILED', 'Terjadi kesalahan jaringan saat menyimpan absensi.');
            setTimeout(() => resumeScanning(), 4000);
        }
    }

    function showRecordingLoadingState(status) {
        statusSelectionPanel.classList.add('d-none');
        resultCard.className = 'p-4 rounded-4 border text-center transition-all bg-primary-subtle border-primary-subtle';
        resultBadge.className = 'badge bg-primary text-white px-3 py-2 fs-6 rounded-pill';
        resultBadge.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Menyimpan Absensi...';
        resultIconWrapper.className = 'rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto mb-3';
        resultIconWrapper.innerHTML = '<span class="spinner-grow spinner-grow-sm" role="status"></span>';
        resultTitle.textContent = 'Memproses...';
        resultMessage.textContent = 'Sedang menyimpan data kehadiran ke sistem...';
    }

    function handleRecordResponse(data) {
        if (data.status === 'SUCCESS' && data.attendance) {
            const att = data.attendance;
            const isLeave = att.status === 'excused' || att.status === 'sick';
            const isLate = att.status === 'late';

            if (isLeave) {
                resultCard.className = 'p-4 rounded-4 border text-center transition-all bg-warning-subtle border-warning';
                resultBadge.className = 'badge bg-warning text-dark px-3 py-2 fs-6 rounded-pill';
                resultBadge.innerHTML = `<i class="bi bi-envelope-check-fill me-1"></i> ${att.status === 'sick' ? 'Sakit' : 'Izin'} Tercatat`;
                resultIconWrapper.className = 'rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center mx-auto mb-3';
                resultIconWrapper.style.width = '72px';
                resultIconWrapper.style.height = '72px';
                resultIconWrapper.style.fontSize = '2rem';
                resultIconWrapper.innerHTML = '<i class="bi bi-envelope-check-fill"></i>';
            } else {
                resultCard.className = 'p-4 rounded-4 border text-center transition-all ' +
                    (isLate ? 'bg-warning-subtle border-warning' : 'bg-success-subtle border-success');
                resultBadge.className = 'badge ' + (isLate ? 'bg-warning text-dark' : 'bg-success text-white') + ' px-3 py-2 fs-6 rounded-pill';
                resultBadge.innerHTML = isLate
                    ? '<i class="bi bi-clock-history me-1"></i> Absensi Berhasil (Terlambat)'
                    : '<i class="bi bi-check-circle-fill me-1"></i> Absensi Berhasil (Tepat Waktu)';
                resultIconWrapper.className = 'rounded-circle ' + (isLate ? 'bg-warning text-dark' : 'bg-success text-white') + ' d-flex align-items-center justify-content-center mx-auto mb-3';
                resultIconWrapper.style.width = '72px';
                resultIconWrapper.style.height = '72px';
                resultIconWrapper.style.fontSize = '2rem';
                resultIconWrapper.innerHTML = '<i class="bi bi-check2-circle"></i>';
            }

            resultTitle.textContent = att.name;
            resultMessage.textContent = data.message;

            profileName.textContent = att.name;
            profileStatus.innerHTML = `<span class="badge ${isLeave ? 'bg-warning text-dark' : (isLate ? 'bg-warning text-dark' : 'bg-success')} small px-2 py-1">${att.status_label}</span>`;
            profileDate.textContent = att.date_formatted || att.date;
            profileTime.textContent = (att.time || '') + ' WIB';
            profileInfoBox.classList.remove('d-none');
            statusSelectionPanel.classList.add('d-none');

            setScannerBoxState('success', isLeave ? '✓ Pengajuan Tercatat' : '✓ Absensi Tercatat');

            if (!isLeave) {
                prependAttendanceLog(att);
            }

        } else if (data.status === 'ALREADY_ATTENDED') {
            updateUIResult('ALREADY_ATTENDED', data.message, data);
        } else {
            updateUIResult('FAILED', data.message || 'Gagal menyimpan absensi. Silakan coba lagi.');
        }

        // Reset to scanning after 6s
        setTimeout(() => resumeScanning(), 6000);
    }

    // ─── 6. Status button handlers ────────────────────────────────────────────
    btnHadir.addEventListener('click', () => {
        statusSelectionPanel.classList.add('d-none');
        submitAttendance('present');
    });

    btnIzin.addEventListener('click', () => {
        pendingLeaveStatus = 'excused';
        leaveModalTitle.textContent = 'Pengajuan Izin';
        leaveModalUserName.textContent = resultTitle.textContent;
        leaveReasonInput.value = '';
        leaveReasonError.classList.add('d-none');
        leaveReasonModal.show();
    });

    btnSakit.addEventListener('click', () => {
        pendingLeaveStatus = 'sick';
        leaveModalTitle.textContent = 'Keterangan Sakit';
        leaveModalUserName.textContent = resultTitle.textContent;
        leaveReasonInput.value = '';
        leaveReasonError.classList.add('d-none');
        leaveReasonModal.show();
    });

    btnSubmitLeave.addEventListener('click', () => {
        const reason = leaveReasonInput.value.trim();
        if (reason.length < 5) {
            leaveReasonError.classList.remove('d-none');
            return;
        }
        leaveReasonError.classList.add('d-none');
        const attendanceDate = leaveAttendanceDateInput.value || null;
        leaveReasonModal.hide();
        submitAttendance(pendingLeaveStatus, reason, attendanceDate);
    });

    btnCloseLeaveModal.addEventListener('click', () => {
        // If modal is closed without submitting, show panel again
        statusSelectionPanel.classList.remove('d-none');
    });

    document.getElementById('leaveReasonModal').addEventListener('hidden.bs.modal', function () {
        // If no submission happened (panel still hidden), reveal it again
        if (!isFaceVerified) return;
        // Check if still in status-selection state (no spinner)
        if (!resultBadge.innerHTML.includes('spinner')) {
            statusSelectionPanel.classList.remove('d-none');
        }
    });

    btnCancelScan.addEventListener('click', () => {
        resumeScanning();
    });

    // ─── 7. Resume scanning state ─────────────────────────────────────────────
    function resetToScanningState() {
        isFaceVerified = false;
        isVerifying = false;
        pendingVerificationToken = null;
        pendingLeaveStatus = null;
        statusSelectionPanel.classList.add('d-none');
        profileInfoBox.classList.add('d-none');
    }

    function resumeScanning() {
        resetToScanningState();
        updateUIResult('NO_FACE', 'Sistem siap. Silakan posisikan wajah di depan kamera.');
        setScannerBoxState('neutral', 'Posisikan Wajah di Sini');
    }

    // ─── 8. Loading State UI ──────────────────────────────────────────────────
    function showVerificationLoadingState() {
        profileInfoBox.classList.add('d-none');
        statusSelectionPanel.classList.add('d-none');
        resultCard.className = 'p-4 rounded-4 border text-center transition-all bg-primary-subtle border-primary-subtle';
        resultBadge.className = 'badge bg-primary text-white px-3 py-2 fs-6 rounded-pill';
        resultBadge.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Memverifikasi Wajah...';

        resultIconWrapper.className = 'rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto mb-3';
        resultIconWrapper.style.width = '72px';
        resultIconWrapper.style.height = '72px';
        resultIconWrapper.style.fontSize = '2rem';
        resultIconWrapper.innerHTML = '<span class="spinner-grow spinner-grow-sm" role="status"></span>';

        resultTitle.textContent = 'Memverifikasi Wajah...';
        resultMessage.textContent = 'Mencocokkan biometrik dengan data terdaftar...';
        setScannerBoxState('active', 'Memverifikasi Biometrik...');
    }

    // ─── 9. Update Result Card UI ─────────────────────────────────────────────
    function updateUIResult(status, message, data = null) {
        profileInfoBox.classList.add('d-none');
        statusSelectionPanel.classList.add('d-none');

        if (status === 'ALREADY_ATTENDED' && data && data.attendance) {
            const att = data.attendance;
            const isLate = att.status === 'late';
            resultCard.className = 'p-4 rounded-4 border text-center transition-all bg-info-subtle border-info-subtle';
            resultBadge.className = 'badge bg-info text-dark px-3 py-2 fs-6 rounded-pill';
            resultBadge.innerHTML = '<i class="bi bi-info-circle-fill me-1"></i> Sudah Absen Hari Ini';
            resultIconWrapper.className = 'rounded-circle bg-info text-dark d-flex align-items-center justify-content-center mx-auto mb-3';
            resultIconWrapper.style.width = '72px';
            resultIconWrapper.style.height = '72px';
            resultIconWrapper.style.fontSize = '2rem';
            resultIconWrapper.innerHTML = '<i class="bi bi-calendar2-check-fill"></i>';
            resultTitle.textContent = att.name;
            resultMessage.textContent = message;
            profileName.textContent = att.name;
            profileStatus.innerHTML = `<span class="badge ${isLate ? 'bg-warning text-dark' : 'bg-success'} small px-2 py-1">${att.status_label || (isLate ? 'Terlambat' : 'Hadir')}</span>`;
            profileDate.textContent = att.date_formatted || att.date;
            profileTime.textContent = (att.time || '') + ' WIB';
            profileInfoBox.classList.remove('d-none');
            setScannerBoxState('active', 'ℹ Sudah Absen');

        } else if (status === 'FAILED') {
            resultCard.className = 'p-4 rounded-4 border text-center transition-all bg-danger-subtle border-danger-subtle';
            resultBadge.className = 'badge bg-danger text-white px-3 py-2 fs-6 rounded-pill';
            resultBadge.innerHTML = '<i class="bi bi-x-circle-fill me-1"></i> Absensi Gagal';
            resultIconWrapper.className = 'rounded-circle bg-danger text-white d-flex align-items-center justify-content-center mx-auto mb-3';
            resultIconWrapper.style.width = '72px';
            resultIconWrapper.style.height = '72px';
            resultIconWrapper.style.fontSize = '2rem';
            resultIconWrapper.innerHTML = '<i class="bi bi-shield-x"></i>';
            resultTitle.textContent = 'Absensi Gagal';
            resultMessage.textContent = message || 'Wajah tidak cocok dengan data terdaftar.';
            setScannerBoxState('danger', '✖ Absensi Gagal');

        } else if (status === 'MULTIPLE_FACES') {
            resultCard.className = 'p-4 rounded-4 border text-center transition-all bg-warning-subtle border-warning-subtle';
            resultBadge.className = 'badge bg-warning text-dark px-3 py-2 fs-6 rounded-pill';
            resultBadge.innerHTML = '<i class="bi bi-people-fill me-1"></i> Wajah Ganda';
            resultIconWrapper.className = 'rounded-circle bg-warning text-white d-flex align-items-center justify-content-center mx-auto mb-3';
            resultIconWrapper.style.width = '72px';
            resultIconWrapper.style.height = '72px';
            resultIconWrapper.style.fontSize = '2rem';
            resultIconWrapper.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i>';
            resultTitle.textContent = 'Banyak Wajah Terdeteksi';
            resultMessage.textContent = message;
            setScannerBoxState('danger', '⚠️ Harap 1 Orang');

        } else if (status === 'CAMERA_ERROR') {
            resultCard.className = 'p-4 rounded-4 border text-center transition-all bg-danger-subtle border-danger';
            resultBadge.className = 'badge bg-danger text-white px-3 py-2 fs-6 rounded-pill';
            resultBadge.innerHTML = '<i class="bi bi-camera-video-off me-1"></i> Kamera Tidak Aktif';
            resultIconWrapper.className = 'rounded-circle bg-danger text-white d-flex align-items-center justify-content-center mx-auto mb-3';
            resultIconWrapper.style.width = '72px';
            resultIconWrapper.style.height = '72px';
            resultIconWrapper.style.fontSize = '2rem';
            resultIconWrapper.innerHTML = '<i class="bi bi-exclamation-octagon-fill"></i>';
            resultTitle.textContent = 'Kamera Tidak Tersedia';
            resultMessage.textContent = message;
            setScannerBoxState('danger', '✖ Kamera Mati');

        } else {
            // NO_FACE / default idle state
            resultCard.className = 'p-4 rounded-4 border text-center transition-all bg-light';
            resultBadge.className = 'badge bg-secondary-subtle text-secondary border px-3 py-2 fs-6 rounded-pill';
            resultBadge.innerHTML = '<i class="bi bi-camera me-1"></i> Menunggu Wajah...';
            resultIconWrapper.className = 'rounded-circle bg-secondary-subtle text-secondary d-flex align-items-center justify-content-center mx-auto mb-3';
            resultIconWrapper.style.width = '72px';
            resultIconWrapper.style.height = '72px';
            resultIconWrapper.style.fontSize = '2rem';
            resultIconWrapper.innerHTML = '<i class="bi bi-person-bounding-box"></i>';
            resultTitle.textContent = 'Posisikan Wajah Anda';
            resultMessage.textContent = message || 'Sistem akan melakukan pemindaian biometrik secara otomatis.';
        }
    }

    // ─── 10. Prepend new log entry ────────────────────────────────────────────
    function prependAttendanceLog(att) {
        if (!recentLogContainer) return;
        if (recentLogEmptyNotice) recentLogEmptyNotice.classList.add('d-none');

        const isLate = att.status === 'late';
        const newItem = document.createElement('div');
        newItem.className = 'list-group-item px-0 d-flex justify-content-between align-items-center';
        newItem.innerHTML = `
            <div>
                <div class="fw-semibold text-dark">${att.name}</div>
                <small class="text-muted">
                    ${att.role === 'teacher' ? 'Guru' : 'Siswa'} &bull; ${att.time}
                </small>
            </div>
            <span class="badge ${isLate ? 'bg-warning-subtle text-warning border border-warning-subtle' : 'bg-success-subtle text-success border border-success-subtle'}">
                ${isLate ? 'Terlambat' : 'Hadir'}
            </span>
        `;
        recentLogContainer.prepend(newItem);

        if (recentLogCount) {
            recentLogCount.textContent = `${recentLogContainer.children.length} Tercatat`;
        }
    }

    // ─── 11. Scanner Box State Styling ───────────────────────────────────────
    function setScannerBoxState(state, text) {
        scannerGuideText.textContent = text;
        const corners = scannerBox.querySelectorAll('.scanner-corner');
        if (state === 'success') {
            scannerBox.style.borderColor = '#22c55e';
            scannerBox.style.background = 'rgba(34, 197, 94, 0.08)';
            scannerIcon.className = 'bi bi-check-circle-fill display-4 text-success';
            corners.forEach(c => c.style.borderColor = '#22c55e');
        } else if (state === 'danger') {
            scannerBox.style.borderColor = '#ef4444';
            scannerBox.style.background = 'rgba(239, 68, 68, 0.08)';
            scannerIcon.className = 'bi bi-x-circle-fill display-4 text-danger';
            corners.forEach(c => c.style.borderColor = '#ef4444');
        } else if (state === 'active') {
            scannerBox.style.borderColor = '#38bdf8';
            scannerBox.style.background = 'rgba(56, 189, 248, 0.08)';
            scannerIcon.className = 'bi bi-person-bounding-box display-4 text-info';
            corners.forEach(c => c.style.borderColor = '#38bdf8');
        } else {
            scannerBox.style.borderColor = '#94a3b8';
            scannerBox.style.background = 'rgba(148, 163, 184, 0.04)';
            scannerIcon.className = 'bi bi-person-bounding-box display-4 text-secondary opacity-75';
            corners.forEach(c => c.style.borderColor = '#94a3b8');
        }
    }

    // ─── 12. Skin/Face Detection ─────────────────────────────────────────────
    function detectSkinAndFaceBlob(imgData, width, height) {
        const data = imgData.data;
        const gridX = 16, gridY = 12;
        const cellW = width / gridX, cellH = height / gridY;
        const activeCells = [];

        for (let gy = 0; gy < gridY; gy++) {
            for (let gx = 0; gx < gridX; gx++) {
                let skinPixels = 0;
                const startX = Math.floor(gx * cellW);
                const startY = Math.floor(gy * cellH);

                for (let y = startY; y < startY + cellH; y += 2) {
                    for (let x = startX; x < startX + cellW; x += 2) {
                        const idx = (y * width + x) * 4;
                        const r = data[idx], g = data[idx + 1], b = data[idx + 2];
                        if (r > 60 && g > 35 && b > 20 && r > g && r > b && Math.abs(r - g) > 12) {
                            skinPixels++;
                        }
                    }
                }
                if (skinPixels > 6) activeCells.push({ gx, gy });
            }
        }

        if (activeCells.length === 0) return [];

        const clusters = [];
        activeCells.forEach(cell => {
            let added = false;
            for (let c of clusters) {
                if (c.some(item => Math.abs(item.gx - cell.gx) <= 2 && Math.abs(item.gy - cell.gy) <= 2)) {
                    c.push(cell);
                    added = true;
                    break;
                }
            }
            if (!added) clusters.push([cell]);
        });

        const scaleX = overlayCanvas.width / width;
        const scaleY = overlayCanvas.height / height;

        return clusters
            .filter(c => c.length >= 4)
            .map(c => {
                let minX = gridX, maxX = 0, minY = gridY, maxY = 0;
                c.forEach(cell => {
                    if (cell.gx < minX) minX = cell.gx;
                    if (cell.gx > maxX) maxX = cell.gx;
                    if (cell.gy < minY) minY = cell.gy;
                    if (cell.gy > maxY) maxY = cell.gy;
                });
                return {
                    x: minX * cellW * scaleX,
                    y: minY * cellH * scaleY,
                    w: (maxX - minX + 1) * cellW * scaleX,
                    h: (maxY - minY + 1) * cellH * scaleY
                };
            });
    }

    function drawBox(ctx, r, color, label) {
        ctx.strokeStyle = color;
        ctx.lineWidth = 3;
        ctx.strokeRect(r.x, r.y, r.w, r.h);
        ctx.fillStyle = color;
        ctx.fillRect(r.x, Math.max(0, r.y - 24), Math.min(110, r.w), 24);
        ctx.save();
        ctx.scale(-1, 1);
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 12px sans-serif';
        ctx.fillText(label, -(r.x + Math.min(110, r.w) - 8), Math.max(16, r.y - 8));
        ctx.restore();
    }

    // ─── 13. Biometric descriptor generation ─────────────────────────────────
    function generateBiometricDescriptor(videoElem, box) {
        const tempCanvas = document.createElement('canvas');
        tempCanvas.width = 64;
        tempCanvas.height = 64;
        const tCtx = tempCanvas.getContext('2d');
        tCtx.drawImage(videoElem, 0, 0, 64, 64);
        const pData = tCtx.getImageData(0, 0, 64, 64).data;

        const vector = [];
        for (let i = 0; i < 128; i++) {
            let sum = 0;
            const step = Math.floor(pData.length / 128);
            for (let j = 0; j < step; j += 4) {
                const idx = i * step + j;
                sum += (pData[idx] * 0.299 + pData[idx + 1] * 0.587 + pData[idx + 2] * 0.114);
            }
            const normalized = ((sum / (step / 4)) - 128) / 128;
            vector.push(parseFloat(normalized.toFixed(6)));
        }

        return {
            engine: 'BrowserLandmarkVector_v1',
            descriptor: vector,
            captured_at: new Date().toISOString()
        };
    }

    // ─── Start ────────────────────────────────────────────────────────────────
    startKioskCamera();
});
</script>
@endpush
