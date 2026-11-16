<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Smart Attendance School') - Sistem Absensi Face Recognition</title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons & Lucide Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        :root {
            --brand-navy: #0f172a;
            --brand-navy-light: #1e293b;
            --brand-blue: #2563eb;
            --brand-blue-hover: #1d4ed8;
            --brand-blue-dark: #1e3a8a;
            --brand-cyan: #0284c7;
            --brand-cyan-light: #e0f2fe;
            --brand-bg: #f8fafc;
            --brand-surface: #ffffff;
            --brand-border: #e2e8f0;
            --brand-border-focus: rgba(37, 99, 235, 0.4);
            --brand-shadow-sm: 0 1px 3px 0 rgba(15, 23, 42, 0.05), 0 1px 2px -1px rgba(15, 23, 42, 0.03);
            --brand-shadow-md: 0 4px 12px -2px rgba(15, 23, 42, 0.06), 0 2px 6px -1px rgba(15, 23, 42, 0.03);
            --brand-shadow-lg: 0 10px 25px -3px rgba(15, 23, 42, 0.07), 0 4px 10px -2px rgba(15, 23, 42, 0.03);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--brand-bg);
            color: #0f172a;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Modern 2026 SaaS Navbar */
        .navbar-custom {
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--brand-border);
            height: 72px;
            transition: all 0.25s ease;
            z-index: 1030;
        }

        .brand-logo-wrap {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
        }

        .brand-icon-modern {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 55%, #06b6d4 100%);
            color: #ffffff;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .brand-logo-wrap:hover .brand-icon-modern {
            transform: scale(1.04);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35);
        }

        .brand-title {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--brand-navy);
            letter-spacing: -0.02em;
            line-height: 1.1;
        }

        .brand-title span {
            color: var(--brand-blue);
        }

        .brand-badge-tag {
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 0.15rem 0.5rem;
            border-radius: 9999px;
            background: #eff6ff;
            color: #2563eb;
            border: 1px solid #dbeafe;
            display: inline-block;
        }

        .nav-link-modern {
            font-size: 0.925rem;
            font-weight: 500;
            color: #475569;
            padding: 0.5rem 1rem !important;
            border-radius: 8px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        .nav-link-modern:hover {
            color: var(--brand-blue);
            background: rgba(37, 99, 235, 0.05);
        }

        .nav-link-modern.active {
            color: var(--brand-blue) !important;
            font-weight: 700;
            background: rgba(37, 99, 235, 0.08);
        }

        /* Action Buttons */
        .btn-admin-login {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #ffffff !important;
            font-weight: 600;
            font-size: 0.875rem;
            padding: 0.55rem 1.15rem;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-admin-login:hover {
            background: linear-gradient(135deg, #1e293b 0%, #2563eb 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(37, 99, 235, 0.25);
            color: #ffffff !important;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            border: none;
            color: #ffffff;
            font-weight: 600;
            padding: 0.65rem 1.4rem;
            border-radius: 10px;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.25);
            transition: all 0.2s ease;
        }

        .btn-primary-custom:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e3a8a 100%);
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(37, 99, 235, 0.35);
        }

        .card-custom {
            background: #ffffff;
            border: 1px solid var(--brand-border);
            border-radius: 16px;
            box-shadow: var(--brand-shadow-md);
            transition: all 0.25s ease;
        }

        .card-custom:hover {
            box-shadow: var(--brand-shadow-lg);
        }

        .footer-custom {
            margin-top: auto;
            background: #ffffff;
            border-top: 1px solid var(--brand-border);
            padding: 1.5rem 0;
            color: #64748b;
            font-size: 0.875rem;
        }
    </style>

    @stack('styles')
    @include('components.brand-head')
</head>
<body class="sa-app-body">
    @include('components.brand-intro')

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom sticky-top">
        <div class="container d-flex align-items-center justify-content-between">
            <a class="brand-logo-wrap" href="{{ route('home') }}">
                <div class="brand-icon-modern">
                    <i class="bi bi-person-bounding-box"></i>
                </div>
                <div>
                    <div class="brand-title">Smart<span>Attendance</span></div>
                    <span class="brand-badge-tag">School Edition</span>
                </div>
            </a>

            <button class="navbar-toggler border-0 shadow-none p-1" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Navigasi Menu">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4 gap-lg-1">
                    <li class="nav-item">
                        <a class="nav-link nav-link-modern {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">
                            <i class="bi bi-house-door-fill"></i> Beranda
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-modern {{ request()->routeIs('attendance.index') ? 'active' : '' }}" href="{{ route('attendance.index') }}">
                            <i class="bi bi-camera-video-fill"></i> Scanner Presensi
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-modern" href="{{ request()->routeIs('home') ? '#panduan-section' : route('home') . '#panduan-section' }}">
                            <i class="bi bi-journal-text"></i> Panduan
                        </a>
                    </li>
                </ul>

                <div class="d-flex align-items-center gap-2 pt-2 pt-lg-0 border-top border-lg-0 mt-2 mt-lg-0">
                    @auth
                        @if(Auth::user()->isAdmin())
                            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-primary btn-sm rounded-3 px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1.5">
                                <i class="bi bi-speedometer2"></i> Panel Admin
                            </a>
                        @elseif(Auth::user()->isTeacher())
                            <a href="{{ route('teacher.dashboard') }}" class="btn btn-outline-info btn-sm rounded-3 px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1.5 text-info-emphasis">
                                <i class="bi bi-person-video3"></i> Portal Guru
                            </a>
                        @elseif(Auth::user()->isStudent())
                            <a href="{{ route('student.dashboard') }}" class="btn btn-outline-success btn-sm rounded-3 px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1.5 text-success">
                                <i class="bi bi-mortarboard-fill"></i> Portal Siswa
                            </a>
                        @endif

                        <div class="d-none d-sm-inline-flex align-items-center px-2 py-1 bg-light rounded-pill border small text-muted">
                            <i class="bi bi-person-circle me-1 text-primary"></i>
                            <span class="fw-semibold text-dark">{{ Str::limit(Auth::user()->name, 15) }}</span>
                        </div>

                        <form action="{{ route('logout') }}" method="POST" class="d-inline"
                              data-confirm="Apakah Anda yakin ingin keluar dari akun ini?"
                              data-confirm-title="Konfirmasi Logout"
                              data-confirm-ok="Ya, Keluar"
                              data-confirm-variant="logout">
                            @csrf
                            <button type="submit" class="btn btn-light btn-sm rounded-3 px-3 py-2 text-danger fw-semibold border d-inline-flex align-items-center gap-1.5">
                                <i class="bi bi-box-arrow-right"></i> Keluar
                            </button>
                        </form>
                    @else
                        <div class="dropdown">
                            <button class="btn btn-primary-custom btn-sm rounded-3 px-3 py-2 fw-semibold d-inline-flex align-items-center gap-2 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-box-arrow-in-right"></i> Masuk / Login
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 p-2 mt-2" style="min-width: 220px;">
                                <li>
                                    <span class="dropdown-header text-uppercase small fw-bold text-muted py-1">Pilih Portal Masuk</span>
                                </li>
                                <li>
                                    <a class="dropdown-item rounded-2 py-2 d-flex align-items-center gap-2 {{ request()->routeIs('student.login') ? 'active' : '' }}" href="{{ route('student.login') }}">
                                        <div class="rounded-2 p-1 bg-success-subtle text-success">
                                            <i class="bi bi-mortarboard-fill"></i>
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-dark small">Portal Siswa</div>
                                            <div class="text-muted" style="font-size: 0.72rem;">Absensi &amp; pengajuan izin</div>
                                        </div>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item rounded-2 py-2 d-flex align-items-center gap-2 {{ request()->routeIs('teacher.login') ? 'active' : '' }}" href="{{ route('teacher.login') }}">
                                        <div class="rounded-2 p-1 bg-info-subtle text-info">
                                            <i class="bi bi-person-video3"></i>
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-dark small">Portal Guru</div>
                                            <div class="text-muted" style="font-size: 0.72rem;">Verifikasi izin &amp; data presensi</div>
                                        </div>
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider my-1"></li>
                                <li>
                                    <a class="dropdown-item rounded-2 py-2 d-flex align-items-center gap-2 {{ request()->routeIs('login') ? 'active' : '' }}" href="{{ route('login') }}">
                                        <div class="rounded-2 p-1 bg-primary-subtle text-primary">
                                            <i class="bi bi-shield-lock-fill"></i>
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-dark small">Panel Admin</div>
                                            <div class="text-muted" style="font-size: 0.72rem;">Pengelolaan master data</div>
                                        </div>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <!-- Content -->
    <main class="flex-grow-1">
        <div class="container pt-3">
            @include('components.alerts')
        </div>
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="footer-custom mt-auto pt-5 pb-4" style="background: #0b1224; border-top: 1px solid rgba(255, 255, 255, 0.08); color: #94a3b8;">
        <div class="container">
            <div class="row g-4 pb-4 border-bottom border-secondary border-opacity-25">
                <div class="col-12 col-lg-5">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="brand-icon-modern" style="width: 36px; height: 36px; font-size: 1.1rem;">
                            <i class="bi bi-person-bounding-box"></i>
                        </div>
                        <span class="text-white fw-bold fs-5">Smart<span class="text-primary">Attendance</span></span>
                        <span class="badge bg-primary bg-opacity-25 text-info border border-info border-opacity-25 rounded-pill small">School Edition 2026</span>
                    </div>
                    <p class="small text-muted mb-3" style="max-width: 380px; line-height: 1.6;">
                        Platform presensi otomatis berbasis biometrik Artificial Intelligence dan Face Recognition. Memberikan akurasi pencatatan kehadiran instan, transparan, dan terintegrasi untuk seluruh ekosistem sekolah modern.
                    </p>
                    <div class="d-flex align-items-center gap-2">
                        <span class="pulse-dot pulse-dot--active"></span>
                        <span class="small text-light fw-medium">Sistem Operasional 99.9% Online</span>
                    </div>
                </div>

                <div class="col-6 col-lg-2 offset-lg-1">
                    <h6 class="text-white fw-bold small text-uppercase mb-3" style="letter-spacing: 0.05em;">Layanan</h6>
                    <ul class="list-unstyled small mb-0 d-flex flex-column gap-2">
                        <li><a href="{{ route('home') }}" class="text-secondary text-decoration-none hover-white">Beranda Utama</a></li>
                        <li><a href="{{ route('attendance.index') }}" class="text-secondary text-decoration-none hover-white">Scanner Presensi</a></li>
                        <li><a href="{{ route('home') }}#fitur-section" class="text-secondary text-decoration-none hover-white">Fitur Unggulan</a></li>
                        <li><a href="{{ route('home') }}#keunggulan-section" class="text-secondary text-decoration-none hover-white">Keunggulan AI</a></li>
                    </ul>
                </div>

                <div class="col-6 col-lg-2">
                    <h6 class="text-white fw-bold small text-uppercase mb-3" style="letter-spacing: 0.05em;">Portal Akses</h6>
                    <ul class="list-unstyled small mb-0 d-flex flex-column gap-2">
                        <li><a href="{{ route('student.login') }}" class="text-secondary text-decoration-none hover-white">Portal Siswa</a></li>
                        <li><a href="{{ route('teacher.login') }}" class="text-secondary text-decoration-none hover-white">Portal Guru</a></li>
                        <li><a href="{{ route('login') }}" class="text-secondary text-decoration-none hover-white">Panel Administrator</a></li>
                        <li><a href="{{ route('attendance.index') }}" class="text-secondary text-decoration-none hover-white">Kiosk Kamera</a></li>
                    </ul>
                </div>

                <div class="col-12 col-lg-2">
                    <h6 class="text-white fw-bold small text-uppercase mb-3" style="letter-spacing: 0.05em;">Keamanan</h6>
                    <div class="p-3 rounded-3" style="background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.08);">
                        <div class="d-flex align-items-center gap-2 mb-1 text-light small fw-semibold">
                            <i class="bi bi-shield-check text-info"></i> Anti-Spoofing AI
                        </div>
                        <p class="mb-0 text-muted" style="font-size: 0.72rem;">
                            Enkripsi SHA-256 data biometrik untuk melindungi integritas dan privasi seluruh warga sekolah.
                        </p>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center justify-content-between pt-3 text-muted small">
                <div>
                    &copy; {{ date('Y') }} <strong class="text-light">SmartAttendance School Edition</strong>. Seluruh hak cipta dilindungi.
                </div>
                <div class="d-flex gap-3 mt-2 mt-sm-0">
                    <span class="text-secondary">Next-Gen SaaS Architecture</span>
                    <span class="text-secondary">&bull;</span>
                    <span class="text-secondary">Face-API &amp; Claude Sonnet AI</span>
                </div>
            </div>
        </div>
    </footer>

    @include('components.ui-confirm')
    @include('components.smart-chatbot')

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/smart-ui.js') }}?v=1.0"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    </script>
    @stack('scripts')
</body>
</html>
