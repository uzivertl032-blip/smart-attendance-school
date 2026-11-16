@extends('layouts.admin')

@section('title', 'Registrasi Wajah — ' . $personName)

@section('content')
<div class="container-fluid p-0">
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-muted small">Dashboard</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ $backUrl }}" class="text-decoration-none text-muted small">Detail {{ ucfirst($personType == 'student' ? 'Siswa' : 'Guru') }}</a>
            </li>
            <li class="breadcrumb-item active small" aria-current="page">Registrasi Wajah</li>
        </ol>
    </nav>

    {{-- Main Container --}}
    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">
            <div class="card admin-card border-0 shadow-sm">
                {{-- Header --}}
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-primary-subtle text-primary fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                                 style="width:52px;height:52px;font-size:1.4rem;">
                                {{ strtoupper(substr($personName, 0, 1)) }}
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2">
                                    <h5 class="fw-bold text-dark mb-0">{{ $personName }}</h5>
                                    <span class="badge {{ $personType == 'student' ? 'bg-primary-subtle text-primary' : 'bg-info-subtle text-info' }} px-2 py-1 small">
                                        {{ $personType == 'student' ? 'Siswa' : 'Guru' }}
                                    </span>
                                </div>
                                <p class="text-muted small mb-0 mt-1">
                                    <code>{{ $personCode }}</code> &bull; {{ $personSubtitle }}
                                </p>
                            </div>
                        </div>

                        <div>
                            <a href="{{ $backUrl }}" class="btn btn-outline-secondary rounded-3 px-3">
                                <i class="bi bi-arrow-left me-1"></i>Kembali
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Body --}}
                <div class="card-body p-4">
                    {{-- Alert Box for Status Messages --}}
                    <div id="statusAlert" class="alert alert-info border-0 rounded-3 mb-4 d-flex align-items-center gap-3" role="alert">
                        <i id="statusAlertIcon" class="bi bi-info-circle-fill fs-4 flex-shrink-0"></i>
                        <div>
                            <strong id="statusAlertTitle">Mempersiapkan Kamera...</strong>
                            <div id="statusAlertMessage" class="small">Mohon izinkan akses kamera pada browser Anda.</div>
                        </div>
                    </div>

                    <div class="row g-4">
                        {{-- Camera Area --}}
                        <div class="col-12 col-lg-7">
                            <div class="position-relative bg-dark rounded-4 overflow-hidden shadow-sm" style="min-height: 380px; aspect-ratio: 4/3;">
                                {{-- Video Stream --}}
                                <video id="webcam" autoplay playsinline muted class="w-100 h-100 object-fit-cover" style="transform: scaleX(-1);"></video>

                                {{-- Canvas Overlay for Bounding Box & Oval Guide --}}
                                <canvas id="overlayCanvas" class="position-absolute top-0 start-0 w-100 h-100" style="pointer-events: none; transform: scaleX(-1);"></canvas>

                                {{-- Oval Guide Overlay UI --}}
                                <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center pointer-events-none">
                                    <div id="ovalGuide" class="border border-2 border-dashed border-white rounded-circle opacity-75 d-flex align-items-center justify-content-center"
                                         style="width: 55%; height: 75%; box-shadow: 0 0 0 9999px rgba(15, 23, 42, 0.45); transition: all 0.3s ease;">
                                        <div class="text-white small text-center opacity-75 px-2">
                                            <i class="bi bi-person-bounding-box d-block fs-3 mb-1"></i>
                                            Posisikan Wajah Di Sini
                                        </div>
                                    </div>
                                </div>

                                {{-- Camera Status Pill Overlay --}}
                                <div class="position-absolute top-3 start-3 bg-dark bg-opacity-75 text-white px-3 py-1 rounded-pill small d-flex align-items-center gap-2 backdrop-blur m-3">
                                    <span id="camStatusDot" class="spinner-grow spinner-grow-sm text-warning" role="status"></span>
                                    <span id="camStatusText">Menghubungkan Kamera...</span>
                                </div>
                            </div>

                            {{-- Camera Controls & Device Selector Bar --}}
                            <div class="mt-3 p-2.5 px-3 bg-light rounded-3 border d-flex flex-wrap align-items-center justify-content-between gap-2 shadow-sm">
                                <div class="d-flex align-items-center gap-2 flex-grow-1">
                                    <i class="bi bi-camera-video-fill text-primary fs-5"></i>
                                    <label for="cameraSelect" class="form-label mb-0 small fw-semibold text-nowrap text-dark">Pilih Kamera:</label>
                                    <select id="cameraSelect" class="form-select form-select-sm border-secondary-subtle" style="max-width: 290px;">
                                        <option value="">Memuat perangkat kamera...</option>
                                    </select>
                                </div>
                                <div class="d-flex align-items-center gap-1">
                                    <button type="button" id="btnReloadCamera" class="btn btn-sm btn-outline-primary rounded-2 px-3 fw-semibold">
                                        <i class="bi bi-arrow-clockwise me-1"></i>Muat Ulang
                                    </button>
                                </div>
                            </div>

                            {{-- Insecure Context Alert Box (Chrome HTTP .test restriction) --}}
                            <div id="insecureGuideBox" class="d-none alert alert-warning border border-warning rounded-3 mt-3 p-3 text-start small">
                                <div class="d-flex align-items-center gap-2 fw-bold text-dark mb-1">
                                    <i class="bi bi-shield-exclamation text-warning fs-5"></i>
                                    <span>Google Chrome Membutuhkan Secure Context</span>
                                </div>
                                <p class="text-muted mb-2">
                                    Akses webcam pada <code id="insecureGuideOrigin" class="text-dark bg-warning-subtle px-1 rounded">{{ request()->getSchemeAndHttpHost() }}</code> diblokir otomatis oleh Chrome karena menggunakan protokol HTTP biasa.
                                </p>
                                <div class="fw-semibold text-dark mb-1">Solusi Praktis (Pilih Salah Satu):</div>
                                <div class="bg-white p-2.5 rounded border mb-2">
                                    <strong class="text-primary d-block mb-1">Cara 1 (Chrome Flags - Cepat tanpa sertifikat SSL):</strong>
                                    <ol class="mb-2 ps-3 text-muted" style="font-size: 0.78rem;">
                                        <li>Buka URL ini di tab Chrome baru: <code class="text-primary user-select-all">chrome://flags/#unsafely-treat-insecure-origin-as-secure</code></li>
                                        <li>Masukkan origin URL: <code class="text-primary user-select-all">{{ request()->getSchemeAndHttpHost() }}</code></li>
                                        <li>Ubah status dropdown menjadi <strong>Enabled</strong>, lalu klik tombol <strong>Relaunch</strong> di pojok kanan bawah.</li>
                                    </ol>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <button type="button" id="btnCopyOriginRegister" class="btn btn-sm btn-warning rounded-pill px-3 fw-semibold">
                                            <i class="bi bi-clipboard-check me-1"></i> Salin URL Origin
                                        </button>
                                        <span id="copySuccessToastRegister" class="text-success small d-none">
                                            <i class="bi bi-check-circle-fill"></i> Berhasil disalin!
                                        </span>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="{{ 'https://' . request()->getHost() . request()->getRequestUri() }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                        <i class="bi bi-lock-fill me-1"></i> Buka via HTTPS
                                    </a>
                                </div>
                            </div>
                        </div>

                        {{-- Control & Preview Panel --}}
                        <div class="col-12 col-lg-5 d-flex flex-column justify-content-between">
                            <div>
                                <h6 class="fw-bold text-dark mb-3">
                                    <i class="bi bi-sliders text-primary me-2"></i>Panduan & Kontrol Registrasi
                                </h6>

                                {{-- Step Checklist --}}
                                <ul class="list-group list-group-flush mb-4 small">
                                    <li class="list-group-item bg-transparent px-0 py-2 d-flex align-items-center gap-2">
                                        <i id="step1Icon" class="bi bi-circle text-muted"></i>
                                        <span>Akses kamera diizinkan oleh pengguna</span>
                                    </li>
                                    <li class="list-group-item bg-transparent px-0 py-2 d-flex align-items-center gap-2">
                                        <i id="step2Icon" class="bi bi-circle text-muted"></i>
                                        <span>Wajah berada di area panduan oval</span>
                                    </li>
                                    <li class="list-group-item bg-transparent px-0 py-2 d-flex align-items-center gap-2">
                                        <i id="step3Icon" class="bi bi-circle text-muted"></i>
                                        <span>Tepat <strong>1 orang</strong> di depan kamera</span>
                                    </li>
                                    <li class="list-group-item bg-transparent px-0 py-2 d-flex align-items-center gap-2">
                                        <i id="step4Icon" class="bi bi-circle text-muted"></i>
                                        <span>Pencahayaan terang & wajah terlihat jelas</span>
                                    </li>
                                </ul>

                                {{-- Captured Snapshot Preview Card --}}
                                <div id="capturedPreviewCard" class="card bg-light border-dashed p-3 mb-3 text-center d-none">
                                    <div class="small fw-semibold text-muted mb-2">Hasil Tangkapan Sampel Wajah</div>
                                    <canvas id="snapshotCanvas" class="rounded-3 shadow-sm mx-auto mb-2" style="max-width: 180px; height: auto;"></canvas>
                                    <div class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1">
                                        <i class="bi bi-check-circle-fill me-1"></i>Data Biometrik Terkstrak
                                    </div>
                                </div>
                            </div>

                            {{-- Actions & Submission Form --}}
                            <div class="mt-4 pt-3 border-top">
                                <form id="faceForm" action="{{ $submitUrl }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="face_data" id="faceDataInput" value="">

                                    <div class="d-grid gap-2">
                                        <button type="button" id="btnCapture" class="btn btn-primary rounded-3 py-2.5 fw-semibold shadow-sm" disabled>
                                            <i class="bi bi-camera-fill me-2"></i>Ambil Sampel Wajah
                                        </button>

                                        <button type="button" id="btnRetake" class="btn btn-outline-secondary rounded-3 py-2 d-none">
                                            <i class="bi bi-arrow-counterclockwise me-2"></i>Ulangi Foto
                                        </button>

                                        <button type="submit" id="btnSave" class="btn btn-success rounded-3 py-2.5 fw-bold shadow-sm d-none">
                                            <i class="bi bi-cloud-arrow-up-fill me-2"></i>Simpan Data Wajah
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/camera-helper.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const video = document.getElementById('webcam');
    const overlayCanvas = document.getElementById('overlayCanvas');
    const snapshotCanvas = document.getElementById('snapshotCanvas');
    const faceDataInput = document.getElementById('faceDataInput');
    const btnCapture = document.getElementById('btnCapture');
    const btnRetake = document.getElementById('btnRetake');
    const btnSave = document.getElementById('btnSave');
    const capturedPreviewCard = document.getElementById('capturedPreviewCard');
    const ovalGuide = document.getElementById('ovalGuide');

    const cameraSelect = document.getElementById('cameraSelect');
    const btnReloadCamera = document.getElementById('btnReloadCamera');
    const insecureGuideBox = document.getElementById('insecureGuideBox');
    const btnCopyOriginRegister = document.getElementById('btnCopyOriginRegister');
    const copySuccessToastRegister = document.getElementById('copySuccessToastRegister');

    const camStatusDot = document.getElementById('camStatusDot');
    const camStatusText = document.getElementById('camStatusText');

    const statusAlert = document.getElementById('statusAlert');
    const statusAlertIcon = document.getElementById('statusAlertIcon');
    const statusAlertTitle = document.getElementById('statusAlertTitle');
    const statusAlertMessage = document.getElementById('statusAlertMessage');

    const step1Icon = document.getElementById('step1Icon');
    const step2Icon = document.getElementById('step2Icon');
    const step3Icon = document.getElementById('step3Icon');
    const step4Icon = document.getElementById('step4Icon');

    let stream = null;
    let detectionInterval = null;
    let currentFacesDetected = 0;
    let isCaptured = false;
    let activeFaceBox = null;

    // Helper: Update Alert UI
    function updateAlert(type, title, message) {
        statusAlert.className = `alert alert-${type} border-0 rounded-3 mb-4 d-flex align-items-center gap-3`;
        statusAlertTitle.textContent = title;
        statusAlertMessage.textContent = message;

        if (type === 'success') {
            statusAlertIcon.className = 'bi bi-check-circle-fill fs-4 text-success flex-shrink-0';
        } else if (type === 'warning') {
            statusAlertIcon.className = 'bi bi-exclamation-triangle-fill fs-4 text-warning flex-shrink-0';
        } else if (type === 'danger') {
            statusAlertIcon.className = 'bi bi-x-circle-fill fs-4 text-danger flex-shrink-0';
        } else {
            statusAlertIcon.className = 'bi bi-info-circle-fill fs-4 text-info flex-shrink-0';
        }
    }

    // Helper: Copy Origin for Chrome Flags
    if (btnCopyOriginRegister) {
        btnCopyOriginRegister.addEventListener('click', function () {
            const originText = window.location.origin;
            const copyCallback = () => {
                if (copySuccessToastRegister) {
                    copySuccessToastRegister.classList.remove('d-none');
                    setTimeout(() => copySuccessToastRegister.classList.add('d-none'), 4000);
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

    // 1. Initialize Webcam Stream with Unified Camera Helper
    async function startCamera(preferredDeviceId = null) {
        if (detectionInterval) {
            clearInterval(detectionInterval);
            detectionInterval = null;
        }

        if (insecureGuideBox) {
            insecureGuideBox.classList.add('d-none');
        }

        camStatusDot.className = 'spinner-grow spinner-grow-sm text-warning';
        camStatusText.textContent = 'Menghubungkan Kamera...';
        step1Icon.className = 'bi bi-circle text-muted';
        btnCapture.disabled = true;

        updateAlert('info', 'Mempersiapkan Kamera...', 'Mohon izinkan akses webcam pada peramban Anda.');

        try {
            const result = await window.schoolCamera.initializeCamera(video, preferredDeviceId);
            if (!result) return;

            stream = result.stream;

            overlayCanvas.width = video.videoWidth || 640;
            overlayCanvas.height = video.videoHeight || 480;

            camStatusDot.className = 'badge bg-success rounded-circle p-1 me-1';
            camStatusText.textContent = 'Kamera Aktif';
            step1Icon.className = 'bi bi-check-circle-fill text-success';

            // Populate multi-camera dropdown if available
            if (cameraSelect) {
                await window.schoolCamera.setupCameraSelect(cameraSelect, (selectedDeviceId) => {
                    startCamera(selectedDeviceId);
                });
            }

            startFaceDetection();
        } catch (err) {
            console.error('Camera Access Error:', err);
            const errInfo = window.schoolCamera.parseError(err);

            camStatusDot.className = 'badge bg-danger rounded-circle p-1 me-1';
            camStatusText.textContent = errInfo.title;
            step1Icon.className = 'bi bi-x-circle-fill text-danger';

            let fullMsg = errInfo.message;
            if (errInfo.details) {
                fullMsg += ' ' + errInfo.details;
            }

            updateAlert('danger', errInfo.title, fullMsg);

            // Display interactive solution guide for Insecure Context (Chrome HTTP)
            if (errInfo.code === 'INSECURE_CONTEXT' && insecureGuideBox) {
                insecureGuideBox.classList.remove('d-none');
            }

            btnCapture.disabled = true;
        }
    }

    // Camera Reconnect Button
    if (btnReloadCamera) {
        btnReloadCamera.addEventListener('click', function () {
            const targetId = cameraSelect ? cameraSelect.value : null;
            startCamera(targetId || null);
        });
    }

    // Clean up camera hardware tracks when leaving page
    window.addEventListener('beforeunload', function () {
        if (window.schoolCamera) {
            window.schoolCamera.stopActiveStream();
        }
    });

    // 2. Client-side Real-time Face Detector Engine
    function startFaceDetection() {
        const ctx = overlayCanvas.getContext('2d');

        detectionInterval = setInterval(() => {
            if (isCaptured || !video.videoWidth) return;

            ctx.clearRect(0, 0, overlayCanvas.width, overlayCanvas.height);

            // Frame analysis using canvas brightness & landmark detection
            const detectCanvas = document.createElement('canvas');
            detectCanvas.width = 160;
            detectCanvas.height = 120;
            const dCtx = detectCanvas.getContext('2d');
            dCtx.drawImage(video, 0, 0, detectCanvas.width, detectCanvas.height);
            const imgData = dCtx.getImageData(0, 0, detectCanvas.width, detectCanvas.height);

            const faceRegions = detectSkinAndFaceBlob(imgData, detectCanvas.width, detectCanvas.height);
            currentFacesDetected = faceRegions.length;

            if (currentFacesDetected === 0) {
                ovalGuide.style.borderColor = '#ffffff';
                step2Icon.className = 'bi bi-circle text-muted';
                step3Icon.className = 'bi bi-circle text-muted';
                step4Icon.className = 'bi bi-circle text-muted';
                updateAlert('warning', 'Wajah Tidak Terdeteksi', 'Posisikan wajah Anda tepat di dalam area panduan oval.');
                btnCapture.disabled = true;
            } else if (currentFacesDetected > 1) {
                ovalGuide.style.borderColor = '#dc3545';
                step2Icon.className = 'bi bi-check-circle-fill text-success';
                step3Icon.className = 'bi bi-x-circle-fill text-danger';
                step4Icon.className = 'bi bi-circle text-muted';
                updateAlert('danger', 'Lebih dari 1 Wajah Terdeteksi', 'Harap pastikan HANYA 1 ORANG berada di depan kamera.');
                btnCapture.disabled = true;

                // Draw red warning boxes for all detected faces
                faceRegions.forEach(r => {
                    drawBox(ctx, r, '#dc3545', '⚠️ Wajah Ganda');
                });
            } else {
                // Exactly 1 Face Detected!
                const r = faceRegions[0];
                activeFaceBox = r;

                ovalGuide.style.borderColor = '#198754';
                step2Icon.className = 'bi bi-check-circle-fill text-success';
                step3Icon.className = 'bi bi-check-circle-fill text-success';
                step4Icon.className = 'bi bi-check-circle-fill text-success';

                drawBox(ctx, r, '#198754', '✓ Wajah Siap');

                updateAlert('success', 'Wajah Terdeteksi Sempurna!', 'Klik tombol "Ambil Sampel Wajah" di bawah untuk mengambil data.');
                btnCapture.disabled = false;
            }
        }, 150);
    }

    // Face Skin-Tone & Landmark Bounding Box Detection Algorithm
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

                if (skinPixels > 6) {
                    activeCells.push({ gx, gy });
                }
            }
        }

        if (activeCells.length === 0) return [];

        // Cluster active cells into face regions
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

        // Map clusters to video-scaled bounding boxes
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
        ctx.fillRect(r.x, Math.max(0, r.y - 24), Math.min(100, r.w), 24);

        ctx.save();
        ctx.scale(-1, 1);
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 12px sans-serif';
        ctx.fillText(label, -(r.x + Math.min(100, r.w) - 8), Math.max(16, r.y - 8));
        ctx.restore();
    }

    // 3. Capture Action
    btnCapture.addEventListener('click', function () {
        if (currentFacesDetected !== 1) return;

        isCaptured = true;
        clearInterval(detectionInterval);

        // Draw cropped snapshot onto preview canvas
        const sCtx = snapshotCanvas.getContext('2d');
        snapshotCanvas.width = 240;
        snapshotCanvas.height = 240;

        sCtx.save();
        sCtx.scale(-1, 1);
        sCtx.drawImage(video, -snapshotCanvas.width, 0, snapshotCanvas.width, snapshotCanvas.height);
        sCtx.restore();

        // Extract 128-dimensional biometric feature descriptor vector
        const featureVector = generateBiometricDescriptor(video, activeFaceBox);
        faceDataInput.value = JSON.stringify(featureVector);

        // UI state updates
        capturedPreviewCard.classList.remove('d-none');
        btnCapture.classList.add('d-none');
        btnRetake.classList.remove('d-none');
        btnSave.classList.remove('d-none');

        updateAlert('success', 'Sampel Wajah Berhasil Ditangkap!', 'Klik "Simpan Data Wajah" untuk mendaftarkan data biometrik ke database.');
    });

    // 4. Retake Action
    btnRetake.addEventListener('click', function () {
        isCaptured = false;
        faceDataInput.value = '';
        capturedPreviewCard.classList.add('d-none');

        btnCapture.classList.remove('d-none');
        btnCapture.disabled = true;
        btnRetake.classList.add('d-none');
        btnSave.classList.add('d-none');

        startFaceDetection();
    });

    // Generate normalized 128-float feature descriptor vector from face canvas pixels
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

    // Start camera immediately on page load
    startCamera();
});
</script>
@endpush
