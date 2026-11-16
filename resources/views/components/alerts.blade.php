{{-- ===========================================================================
     SmartAttendance School Edition - Glassmorphism & Dark Premium Alerts
     Provides dual-layer notification:
     1. Automatic 3D Animated Popup trigger via #saFlashNotice (smart-ui.js)
     2. Refined Glassmorphism inline notice fallback
     =========================================================================== --}}

@if(session('success'))
    <div class="sa-flash-notice d-none"
         data-flash-type="success"
         data-flash-title="Berhasil!"
         data-flash-message="{{ session('success') }}"
         aria-hidden="true"></div>

    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4 border-0 shadow-sm rounded-4" role="alert">
        <div class="me-3 d-flex align-items-center justify-content-center rounded-circle" style="width: 38px; height: 38px; background: rgba(16, 185, 129, 0.2); color: #34d399; font-size: 1.15rem; flex-shrink: 0;">
            <i class="bi bi-check-circle-fill"></i>
        </div>
        <div class="fw-medium text-light flex-grow-1">{{ session('success') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
@endif

@if(session('error'))
    <div class="sa-flash-notice d-none"
         data-flash-type="error"
         data-flash-title="Terjadi Kendala"
         data-flash-message="{{ session('error') }}"
         aria-hidden="true"></div>

    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4 border-0 shadow-sm rounded-4" role="alert">
        <div class="me-3 d-flex align-items-center justify-content-center rounded-circle" style="width: 38px; height: 38px; background: rgba(239, 68, 68, 0.2); color: #f87171; font-size: 1.15rem; flex-shrink: 0;">
            <i class="bi bi-exclamation-triangle-fill"></i>
        </div>
        <div class="fw-medium text-light flex-grow-1">{{ session('error') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
@endif

@if(session('warning'))
    <div class="sa-flash-notice d-none"
         data-flash-type="warning"
         data-flash-title="Peringatan Sistem"
         data-flash-message="{{ session('warning') }}"
         aria-hidden="true"></div>

    <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center mb-4 border-0 shadow-sm rounded-4" role="alert">
        <div class="me-3 d-flex align-items-center justify-content-center rounded-circle" style="width: 38px; height: 38px; background: rgba(245, 158, 11, 0.2); color: #fbbf24; font-size: 1.15rem; flex-shrink: 0;">
            <i class="bi bi-exclamation-circle-fill"></i>
        </div>
        <div class="fw-medium text-light flex-grow-1">{{ session('warning') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
@endif

@if(session('info'))
    <div class="sa-flash-notice d-none"
         data-flash-type="info"
         data-flash-title="Informasi Sistem"
         data-flash-message="{{ session('info') }}"
         aria-hidden="true"></div>

    <div class="alert alert-info alert-dismissible fade show d-flex align-items-center mb-4 border-0 shadow-sm rounded-4" role="alert">
        <div class="me-3 d-flex align-items-center justify-content-center rounded-circle" style="width: 38px; height: 38px; background: rgba(59, 130, 246, 0.2); color: #60a5fa; font-size: 1.15rem; flex-shrink: 0;">
            <i class="bi bi-info-circle-fill"></i>
        </div>
        <div class="fw-medium text-light flex-grow-1">{{ session('info') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
@endif

@if($errors->any())
    <div class="sa-flash-notice d-none"
         data-flash-type="error"
         data-flash-title="Validasi Formulir Gagal"
         data-flash-message="{{ implode(' • ', $errors->all()) }}"
         aria-hidden="true"></div>

    <div class="alert alert-danger alert-dismissible fade show mb-4 border-0 shadow-sm rounded-4" role="alert">
        <div class="d-flex align-items-center mb-2">
            <div class="me-2 d-flex align-items-center justify-content-center rounded-circle" style="width: 32px; height: 32px; background: rgba(239, 68, 68, 0.2); color: #f87171; font-size: 1rem; flex-shrink: 0;">
                <i class="bi bi-x-circle-fill"></i>
            </div>
            <strong class="fw-bold text-white">Terjadi kesalahan pengisian formulir:</strong>
        </div>
        <ul class="mb-0 ps-4 text-light small">
            @foreach($errors->all() as $error)
                <li class="mb-1">{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
@endif
