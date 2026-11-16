<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Portal Siswa') - Smart Attendance School</title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons & Lucide Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Chart.js 4.4 -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

    <style>
        :root {
            --student-navy: #0f172a;
            --student-sidebar-bg: #0f172a;
            --student-sidebar-border: #1e293b;
            --student-emerald: #059669;
            --student-emerald-light: #ecfdf5;
            --student-active: #2563eb;
            --sidebar-width: 260px;
            --bg-color: #f8fafc;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--bg-color);
            color: #0f172a;
            min-height: 100vh;
        }

        /* Sidebar Styling */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--student-sidebar-bg);
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1040;
            transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            border-right: 1px solid var(--student-sidebar-border);
            display: flex;
            flex-direction: column;
        }

        .sidebar-brand {
            padding: 1.4rem 1.25rem;
            color: #ffffff;
            font-weight: 700;
            font-size: 1.15rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
        }

        .sidebar-brand-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #10b981, #06b6d4);
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
        }

        .sidebar-nav {
            padding: 1rem 0.85rem;
            list-style: none;
            margin: 0;
            flex-grow: 1;
            overflow-y: auto;
        }

        .nav-header {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
            padding: 0.85rem 0.75rem 0.35rem;
            font-weight: 700;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.72rem 0.95rem;
            color: #94a3b8;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 500;
            font-size: 0.92rem;
            transition: all 0.22s ease;
            margin-bottom: 0.25rem;
            position: relative;
        }

        .sidebar-link:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.06);
            transform: translateX(2px);
        }

        .sidebar-link.active {
            color: #ffffff;
            background: var(--student-active);
            font-weight: 600;
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.4);
        }

        .sidebar-link.active::before {
            content: '';
            position: absolute;
            left: -4px;
            top: 20%;
            bottom: 20%;
            width: 4px;
            background: #34d399;
            border-radius: 4px;
            box-shadow: 0 0 10px #34d399;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
        }

        /* Topbar */
        .topbar {
            height: 72px;
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.9);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.75rem;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        /* Mobile Backdrop */
        .sidebar-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(11, 18, 36, 0.65);
            backdrop-filter: blur(4px);
            z-index: 1035;
        }

        .sidebar-backdrop.show {
            display: block;
        }

        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
    @stack('styles')
    @include('components.brand-head')
</head>
<body class="sa-app-body">
    @include('components.brand-intro')

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <a href="{{ route('student.dashboard') }}" class="sidebar-brand">
            <div class="sidebar-brand-icon">
                <i class="bi bi-mortarboard-fill"></i>
            </div>
            <div>
                <div>Smart Attendance</div>
                <div style="font-size: 0.72rem; color: #34d399; font-weight: 600;">PORTAL SISWA</div>
            </div>
        </a>

        <ul class="sidebar-nav">
            <li class="nav-header">Menu Siswa</li>
            <li>
                <a href="{{ route('student.dashboard') }}" class="sidebar-link {{ request()->routeIs('student.dashboard') ? 'active' : '' }}">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard Saya</span>
                </a>
            </li>
            <li>
                <a href="{{ route('student.history') }}" class="sidebar-link {{ request()->routeIs('student.history') ? 'active' : '' }}">
                    <i class="bi bi-calendar-check-fill"></i>
                    <span>Riwayat Presensi</span>
                </a>
            </li>
            <li>
                <a href="{{ route('student.leave-requests.index') }}" class="sidebar-link {{ request()->routeIs('student.leave-requests.*') ? 'active' : '' }}">
                    <i class="bi bi-envelope-paper-fill"></i>
                    <span>Ajukan Izin / Sakit</span>
                </a>
            </li>

            <li class="nav-header">Layanan Kiosk</li>
            <li>
                <a href="{{ route('attendance.index') }}" class="sidebar-link" target="_blank">
                    <i class="bi bi-camera-video"></i>
                    <span>Scanner Presensi</span>
                    <i class="bi bi-box-arrow-up-right ms-auto small text-white-50"></i>
                </a>
            </li>
            <li>
                <a href="{{ route('home') }}" class="sidebar-link">
                    <i class="bi bi-house"></i>
                    <span>Beranda Utama</span>
                </a>
            </li>

            <li class="nav-header">Akun</li>
            <li>
                <form action="{{ route('logout') }}" method="POST"
                      data-confirm="Apakah Anda yakin ingin keluar dari sesi Siswa?"
                      data-confirm-title="Konfirmasi Logout"
                      data-confirm-ok="Ya, Keluar"
                      data-confirm-variant="logout">
                    @csrf
                    <button type="submit" class="sidebar-link w-100 text-start border-0 bg-transparent text-danger">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Keluar (Logout)</span>
                    </button>
                </form>
            </li>
        </ul>
    </aside>

    <!-- Mobile Backdrop -->
    <div class="sidebar-backdrop d-lg-none" id="studentBackdrop"></div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
        <header class="topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-light d-lg-none" type="button" id="sidebarToggle">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <div class="d-none d-sm-block">
                    <h6 class="mb-0 fw-bold">@yield('page-title', 'Portal Siswa')</h6>
                    <small class="text-muted">{{ \Carbon\Carbon::now()->translatedFormat('l, d F Y') }}</small>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 rounded-pill small fw-semibold">
                    <i class="bi bi-check-circle-fill me-1"></i> Akun Siswa Aktif
                </span>

                <div class="dropdown">
                    <button class="btn btn-light btn-sm d-flex align-items-center gap-2 border rounded-pill px-3 py-1.5" type="button" data-bs-toggle="dropdown">
                        <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 28px; height: 28px; font-size: 0.8rem;">
                            {{ strtoupper(substr(Auth::user()->name ?? 'S', 0, 1)) }}
                        </div>
                        <span class="fw-semibold small text-dark d-none d-md-inline">{{ Auth::user()->name ?? 'Siswa' }}</span>
                        <i class="bi bi-chevron-down small text-muted"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 mt-2">
                        <li class="px-3 py-2 border-bottom">
                            <div class="fw-bold text-dark small">{{ Auth::user()->name ?? 'Siswa' }}</div>
                            <div class="text-muted small" style="font-size: 0.75rem;">{{ Auth::user()->email ?? '' }}</div>
                        </li>
                        <li>
                            <form action="{{ route('logout') }}" method="POST"
                                  data-confirm="Apakah Anda yakin ingin keluar dari sesi Siswa?"
                                  data-confirm-title="Konfirmasi Logout"
                                  data-confirm-ok="Ya, Keluar"
                                  data-confirm-variant="logout">
                                @csrf
                                <button type="submit" class="dropdown-item py-2 text-danger small">
                                    <i class="bi bi-box-arrow-right me-2"></i> Keluar
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Page Body -->
        <main class="p-3 p-md-4 flex-grow-1">
            <div class="container-fluid">
                @include('components.alerts')
                @yield('content')
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-white border-top py-3 text-center text-muted small">
            &copy; {{ date('Y') }} <strong>Smart Attendance School</strong> — Sistem Absensi Berbasis Face Recognition
        </footer>
    </div>

    @include('components.ui-confirm')
    @include('components.smart-chatbot')

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/smart-ui.js') }}?v=1.0"></script>
    <script>
        (function () {
            var sidebar = document.getElementById('sidebar');
            var backdrop = document.getElementById('studentBackdrop');
            var toggle = document.getElementById('sidebarToggle');

            function toggleSidebar() {
                if (sidebar) sidebar.classList.toggle('show');
                if (backdrop) backdrop.classList.toggle('show');
            }

            function closeSidebar() {
                if (sidebar) sidebar.classList.remove('show');
                if (backdrop) backdrop.classList.remove('show');
            }

            if (toggle) toggle.addEventListener('click', toggleSidebar);
            if (backdrop) backdrop.addEventListener('click', closeSidebar);

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        })();
    </script>
    @stack('scripts')
</body>
</html>
