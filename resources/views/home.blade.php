@extends('layouts.app')

@section('title', 'SmartAttendance School Edition — Sistem Presensi Cerdas')

@push('styles')
<style>
    /* =========================================================
       SmartAttendance - Clean Minimalist Professional
       Strict Rule: NO Images, NO Photos, NO Illustrations, NO 3D.
       Pure Typography, Clean Cards, Lucide Icons, Soft White #F8FAFC.
       ========================================================= */

    .hero-minimal-section {
        padding: 3rem 0 2.5rem;
        background: #ffffff;
        border-bottom: 1px solid #e2e8f0;
    }

    @media (max-width: 991.98px) {
        .hero-minimal-section {
            padding: 2.25rem 0 1.75rem;
        }
    }

    .badge-pill-clean {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.35rem 0.85rem;
        border-radius: 9999px;
        font-size: 0.8rem;
        font-weight: 600;
        background: #eff6ff;
        color: #2563eb;
        border: 1px solid #bfdbfe;
    }

    .hero-title-clean {
        font-size: clamp(2rem, 3.4vw, 3rem);
        font-weight: 800;
        letter-spacing: -0.03em;
        line-height: 1.2;
        color: #0f172a;
        margin-top: 0.85rem;
        margin-bottom: 1rem;
    }

    .hero-title-clean .text-royal-blue {
        color: #2563eb;
    }

    .hero-desc-clean {
        font-size: 1.05rem;
        line-height: 1.65;
        color: #475569;
        max-width: 600px;
        margin-bottom: 1.5rem;
    }

    /* Live Parameters Panel (Minimalist Clean Card) */
    .hero-kiosk-preview-card {
        background: #0f172a;
        color: #f8fafc;
        border-radius: 16px;
        padding: 1.5rem;
        border: 1px solid #1e293b;
        box-shadow: 0 10px 25px -3px rgba(15, 23, 42, 0.12);
    }

    .scanner-live-box {
        background: #1e293b;
        border-radius: 12px;
        border: 1px dashed #334155;
        padding: 1.5rem 1.25rem;
        text-align: center;
    }

    .icon-box-clean {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }

    .stat-metric-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 1.25rem;
        box-shadow: 0 1px 3px 0 rgba(15, 23, 42, 0.05);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .stat-metric-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px 0 rgba(15, 23, 42, 0.08);
        border-color: #cbd5e1;
    }

    .feature-clean-box {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 1.75rem 1.5rem;
        height: 100%;
        display: flex;
        flex-direction: column;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        box-shadow: 0 1px 3px 0 rgba(15, 23, 42, 0.04);
    }

    .feature-clean-box:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 16px -2px rgba(15, 23, 42, 0.08);
        border-color: #93c5fd;
    }

    .portal-link-clean {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 1.75rem 1.5rem;
        text-decoration: none;
        display: flex;
        flex-direction: column;
        height: 100%;
        transition: all 0.2s ease;
        box-shadow: 0 1px 3px 0 rgba(15, 23, 42, 0.04);
    }

    .portal-link-clean:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 20px -2px rgba(37, 99, 235, 0.1);
        border-color: #2563eb;
    }
</style>
@endpush

@section('content')
<div class="landing-page-clean">

    {{-- =========================================================
         1. HERO SECTION (Minimalist Typography & Clean Layout)
         ========================================================= --}}
    <section class="hero-minimal-section">
        <div class="container">
            <div class="row align-items-center g-4 g-xl-5">
                {{-- Left Column: Headline, Description & Actions --}}
                <div class="col-12 col-lg-7">
                    <div class="badge-pill-clean mb-2">
                        <i class="bi bi-shield-check"></i>
                        <span>SmartAttendance &bull; School Edition</span>
                    </div>

                    <h1 class="hero-title-clean">
                        Presensi Sekolah <br>
                        <span class="text-royal-blue">Cerdas, Cepat, dan Akurat.</span>
                    </h1>

                    <p class="hero-desc-clean">
                        Sistem manajemen absensi modern terintegrasi untuk Guru, Siswa, dan Administrator Sekolah. Dilengkapi deteksi wajah biometrik instan, perlindungan anti-spoofing, dan rekapitulasi data kehadiran otomatis tanpa antrean manual.
                    </p>

                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <a href="{{ route('attendance.index') }}" class="btn btn-primary px-4 py-2.5 rounded-3 fw-semibold shadow-sm" style="background-color: #2563eb; border-color: #2563eb;">
                            <i class="bi bi-camera-video me-1.5"></i> Buka Scanner Kiosk
                        </a>

                        @auth
                            @if(Auth::user()->isAdmin())
                                <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary px-4 py-2.5 rounded-3 fw-semibold">
                                    <i class="bi bi-speedometer2 me-1.5"></i> Panel Administrator
                                </a>
                            @elseif(Auth::user()->isTeacher())
                                <a href="{{ route('teacher.dashboard') }}" class="btn btn-outline-secondary px-4 py-2.5 rounded-3 fw-semibold">
                                    <i class="bi bi-person-badge me-1.5"></i> Portal Guru
                                </a>
                            @elseif(Auth::user()->isStudent())
                                <a href="{{ route('student.dashboard') }}" class="btn btn-outline-secondary px-4 py-2.5 rounded-3 fw-semibold">
                                    <i class="bi bi-mortarboard me-1.5"></i> Portal Siswa
                                </a>
                            @endif
                        @else
                            <a href="#portal-section" class="btn btn-outline-secondary px-4 py-2.5 rounded-3 fw-semibold">
                                <i class="bi bi-box-arrow-in-right me-1.5"></i> Masuk Sistem
                            </a>
                            <a href="#fitur-section" class="btn btn-link text-secondary text-decoration-none px-2 py-2.5 fw-semibold">
                                Pelajari Sistem <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        @endauth
                    </div>

                    {{-- School operational parameters --}}
                    <div class="d-flex flex-wrap align-items-center gap-4 mt-4 pt-3 border-top text-muted small">
                        <div>
                            <span class="text-secondary">Jam Masuk:</span>
                            <strong class="text-dark ms-1">{{ $openingTime }} WIB</strong>
                        </div>
                        <div>
                            <span class="text-secondary">Batas Tepat Waktu:</span>
                            <strong class="text-dark ms-1">{{ $lateAfter }} WIB</strong>
                        </div>
                        <div>
                            <span class="text-secondary">Anti-Spoofing:</span>
                            <strong class="text-success ms-1"><i class="bi bi-check-circle-fill me-0.5"></i> Aktif</strong>
                        </div>
                    </div>
                </div>

                {{-- Right Column: Minimalist Dark Terminal / Scanner Preview Card (NO Images) --}}
                <div class="col-12 col-lg-5">
                    <div class="hero-kiosk-preview-card">
                        <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom border-secondary border-opacity-25">
                            <div class="d-flex align-items-center gap-2">
                                <span class="d-inline-block rounded-circle bg-success" style="width: 8px; height: 8px;"></span>
                                <span class="fw-semibold small text-light text-uppercase">Kiosk Pemindai Biometrik</span>
                            </div>
                            <span class="badge bg-primary bg-opacity-25 text-info border border-primary border-opacity-25 rounded-pill px-2.5 py-1 small">
                                99.8% Match
                            </span>
                        </div>

                        <div class="scanner-live-box my-2">
                            <div class="d-inline-flex p-2.5 rounded-circle bg-secondary bg-opacity-25 text-primary mb-2.5">
                                <i class="bi bi-person-bounding-box" style="font-size: 2.25rem; color: #38bdf8;"></i>
                            </div>
                            <h6 class="text-white fw-bold mb-1">Face Recognition Instan</h6>
                            <p class="text-secondary small mb-0 font-monospace" style="font-size: 0.78rem;">
                                Latency: &lt; 0.8 detik / wajah &bull; 128-D Biometrics
                            </p>
                        </div>

                        <div class="row g-2 mt-3 pt-1">
                            <div class="col-6">
                                <div class="p-2.5 rounded-3 bg-secondary bg-opacity-25">
                                    <div class="text-secondary small" style="font-size: 0.72rem;">Status Liveness</div>
                                    <div class="fw-bold text-success small"><i class="bi bi-shield-check me-1"></i>Terverifikasi</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2.5 rounded-3 bg-secondary bg-opacity-25">
                                    <div class="text-secondary small" style="font-size: 0.72rem;">Model AI</div>
                                    <div class="fw-bold text-light small">SSD MobileNet V1</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- =========================================================
         2. STATISTIK SINGKAT (Clean Metric Cards)
         ========================================================= --}}
    <section class="py-3 py-md-4">
        <div class="container">
            <div class="row g-3">
                <div class="col-6 col-lg-3">
                    <div class="stat-metric-card">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.04em;">Hadir Hari Ini</span>
                            <div class="icon-box-clean bg-primary-subtle text-primary">
                                <i class="bi bi-check2-circle"></i>
                            </div>
                        </div>
                        <div class="fs-3 fw-bold text-dark mb-0">{{ $totalPresentToday }}</div>
                        <span class="text-muted small" style="font-size: 0.75rem;">Presensi terverifikasi</span>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="stat-metric-card">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.04em;">Terlambat</span>
                            <div class="icon-box-clean bg-warning-subtle text-warning">
                                <i class="bi bi-clock-history"></i>
                            </div>
                        </div>
                        <div class="fs-3 fw-bold text-dark mb-0">{{ $totalLateToday }}</div>
                        <span class="text-muted small" style="font-size: 0.75rem;">Lewat {{ $lateAfter }} WIB</span>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="stat-metric-card">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.04em;">Akurasi Model</span>
                            <div class="icon-box-clean bg-info-subtle text-info">
                                <i class="bi bi-cpu"></i>
                            </div>
                        </div>
                        <div class="fs-3 fw-bold text-dark mb-0">99.8%</div>
                        <span class="text-muted small" style="font-size: 0.75rem;">ResNet Biometrik AI</span>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="stat-metric-card">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.04em;">Waktu Verifikasi</span>
                            <div class="icon-box-clean bg-success-subtle text-success">
                                <i class="bi bi-lightning-charge"></i>
                            </div>
                        </div>
                        <div class="fs-3 fw-bold text-dark mb-0">&lt; 0.8s</div>
                        <span class="text-muted small" style="font-size: 0.75rem;">Pemrosesan real-time</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- =========================================================
         3. FITUR UNGGULAN (Clean Minimalist Cards with Icons)
         ========================================================= --}}
    <section class="py-5" id="fitur-section">
        <div class="container">
            <div class="text-center mx-auto mb-5" style="max-width: 640px;">
                <span class="badge-pill-clean mb-2">FITUR UTAMA</span>
                <h2 class="fw-bold text-dark fs-3 mb-2">Presensi Modern Berstandar Sekolah Unggulan</h2>
                <p class="text-muted small mb-0">
                    Dirancang dengan antarmuka yang bersih, fungsional, dan memberikan kemudahan maksimal bagi seluruh pengguna.
                </p>
            </div>

            <div class="row g-4">
                {{-- Fitur 1 --}}
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="feature-clean-box">
                        <div class="icon-box-clean bg-primary-subtle text-primary mb-3">
                            <i class="bi bi-camera-video"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">Presensi Biometrik Instan</h5>
                        <p class="text-muted small mb-3 flex-grow-1">
                            Mendeteksi dan mengenali wajah secara otomatis tanpa sentuhan fisik. Menghilangkan antrean siswa di depan gerbang saat pagi hari.
                        </p>
                        <a href="{{ route('attendance.index') }}" class="small fw-semibold text-primary text-decoration-none">
                            Buka Scanner <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>

                {{-- Fitur 2 --}}
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="feature-clean-box">
                        <div class="icon-box-clean bg-success-subtle text-success mb-3">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">Anti-Spoofing &amp; Validasi Asli</h5>
                        <p class="text-muted small mb-3 flex-grow-1">
                            Mencegah kecurangan presensi menggunakan foto cetak atau video layar ponsel dengan sensor analisis biometrik aktif.
                        </p>
                        <span class="small text-muted"><i class="bi bi-check2 text-success me-1"></i> Proteksi Real-Time</span>
                    </div>
                </div>

                {{-- Fitur 3 --}}
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="feature-clean-box">
                        <div class="icon-box-clean bg-info-subtle text-info mb-3">
                            <i class="bi bi-envelope-paper"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">Alur Perizinan Guru &amp; Siswa</h5>
                        <p class="text-muted small mb-3 flex-grow-1">
                            Sistem pengajuan izin dan sakit yang terstruktur dengan notifikasi otomatis via Gmail ke seluruh pihak yang berwenang.
                        </p>
                        <span class="small text-muted"><i class="bi bi-check2 text-info me-1"></i> Integrasi Email Otomatis</span>
                    </div>
                </div>

                {{-- Fitur 4 --}}
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="feature-clean-box">
                        <div class="icon-box-clean bg-dark-subtle text-dark mb-3">
                            <i class="bi bi-chat-left-dots"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">Asisten Chatbot+ Cerdas</h5>
                        <p class="text-muted small mb-3 flex-grow-1">
                            Asisten AI yang siap menjawab seputar aturan jam masuk, status presensi, hingga informasi tata tertib sekolah secara mandiri.
                        </p>
                        <span class="small text-muted"><i class="bi bi-check2 text-dark me-1"></i> Standby 24/7 di Sudut Layar</span>
                    </div>
                </div>

                {{-- Fitur 5 --}}
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="feature-clean-box">
                        <div class="icon-box-clean bg-warning-subtle text-warning mb-3">
                            <i class="bi bi-bar-chart"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">Rekapitulasi Laporan Otomatis</h5>
                        <p class="text-muted small mb-3 flex-grow-1">
                            Laporan kehadiran harian, mingguan, dan bulanan siap diunduh dan dicetak untuk keperluan evaluasi bimbingan konseling dan pimpinan.
                        </p>
                        <span class="small text-muted"><i class="bi bi-check2 text-warning me-1"></i> Export &amp; Cetak Cepat</span>
                    </div>
                </div>

                {{-- Fitur 6 --}}
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="feature-clean-box">
                        <div class="icon-box-clean bg-primary-subtle text-primary mb-3">
                            <i class="bi bi-phone"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">Responsif Multi-Perangkat</h5>
                        <p class="text-muted small mb-3 flex-grow-1">
                            Didesain bersih dan adaptif untuk komputer kantor, laptop guru, tablet presensi, hingga layar smartphone siswa.
                        </p>
                        <span class="small text-muted"><i class="bi bi-check2 text-primary me-1"></i> Optimal di Semua Layar</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- =========================================================
         4. AKSES PORTAL (Clean Minimalist Cards)
         ========================================================= --}}
    <section class="py-5 bg-white border-top border-bottom" id="portal-section">
        <div class="container">
            <div class="text-center mx-auto mb-5" style="max-width: 640px;">
                <span class="badge-pill-clean mb-2">PORTAL MASUK</span>
                <h2 class="fw-bold text-dark fs-3 mb-2">Masuk ke Portal Terkait</h2>
                <p class="text-muted small mb-0">
                    Pilih portal sesuai peran dan kewenangan Anda untuk mengakses layanan presensi.
                </p>
            </div>

            <div class="row g-4">
                {{-- Siswa --}}
                <div class="col-12 col-md-6 col-lg-3">
                    <a href="{{ route('student.login') }}" class="portal-link-clean">
                        <div class="icon-box-clean bg-success-subtle text-success mb-3">
                            <i class="bi bi-mortarboard"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-1">Portal Siswa</h5>
                        <p class="text-muted small mb-3 flex-grow-1">
                            Cek status presensi harian, riwayat kehadiran, serta ajukan surat permohonan izin/sakit.
                        </p>
                        <div class="d-flex align-items-center text-success fw-semibold small mt-auto">
                            <span>Masuk Siswa</span> <i class="bi bi-arrow-right ms-2"></i>
                        </div>
                    </a>
                </div>

                {{-- Guru --}}
                <div class="col-12 col-md-6 col-lg-3">
                    <a href="{{ route('teacher.login') }}" class="portal-link-clean">
                        <div class="icon-box-clean bg-info-subtle text-info mb-3">
                            <i class="bi bi-person-badge"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-1">Portal Guru</h5>
                        <p class="text-muted small mb-3 flex-grow-1">
                            Verifikasi permohonan izin siswa, pantau absensi kelas, dan kelola perizinan guru ke admin.
                        </p>
                        <div class="d-flex align-items-center text-info-emphasis fw-semibold small mt-auto">
                            <span>Masuk Guru</span> <i class="bi bi-arrow-right ms-2"></i>
                        </div>
                    </a>
                </div>

                {{-- Admin --}}
                <div class="col-12 col-md-6 col-lg-3">
                    <a href="{{ route('login') }}" class="portal-link-clean">
                        <div class="icon-box-clean bg-primary-subtle text-primary mb-3">
                            <i class="bi bi-shield-lock"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-1">Panel Admin</h5>
                        <p class="text-muted small mb-3 flex-grow-1">
                            Kelola data master siswa &amp; guru, konfigurasi AI, persetujuan izin guru, dan laporan lengkap.
                        </p>
                        <div class="d-flex align-items-center text-primary fw-semibold small mt-auto">
                            <span>Masuk Admin</span> <i class="bi bi-arrow-right ms-2"></i>
                        </div>
                    </a>
                </div>

                {{-- Scanner --}}
                <div class="col-12 col-md-6 col-lg-3">
                    <a href="{{ route('attendance.index') }}" class="portal-link-clean bg-light">
                        <div class="icon-box-clean bg-dark text-white mb-3">
                            <i class="bi bi-camera-video"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-1">Scanner Kiosk</h5>
                        <p class="text-muted small mb-3 flex-grow-1">
                            Buka mode kamera pemindai di gerbang masuk sekolah untuk absensi mandiri siswa &amp; guru.
                        </p>
                        <div class="d-flex align-items-center text-dark fw-semibold small mt-auto">
                            <span>Buka Scanner</span> <i class="bi bi-arrow-right ms-2"></i>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </section>

</div>
@endsection
