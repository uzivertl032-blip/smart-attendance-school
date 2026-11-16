<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - Admin Smart Attendance</title>

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
            --sidebar-bg: #0f172a;
            --sidebar-border: #1e293b;
            --sidebar-text: #94a3b8;
            --sidebar-hover-bg: #1e293b;
            --sidebar-active-bg: #2563eb;
            --sidebar-width: 260px;
            --primary-color: #2563eb;
            --bg-color: #f8fafc;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--bg-color);
            color: #0f172a;
            min-height: 100vh;
        }

        /* Sidebar Styling - Dark Navy Minimalist */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1040;
            transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            border-right: 1px solid var(--sidebar-border);
            display: flex;
            flex-direction: column;
        }

        .sidebar-brand {
            padding: 1.25rem 1.25rem;
            color: #ffffff;
            font-weight: 700;
            font-size: 1.1rem;
            border-bottom: 1px solid var(--sidebar-border);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
        }

        .sidebar-brand-icon {
            width: 38px;
            height: 38px;
            background: #2563eb;
            color: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
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
            color: var(--sidebar-text);
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
            background: var(--sidebar-active-bg);
            font-weight: 600;
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.45);
        }

        .sidebar-link.active::before {
            content: '';
            position: absolute;
            left: -4px;
            top: 20%;
            bottom: 20%;
            width: 4px;
            background: #22d3ee;
            border-radius: 4px;
            box-shadow: 0 0 10px #22d3ee;
        }

        /* Main Content Wrapper */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
        }

        .admin-topbar {
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.9);
            padding: 0.85rem 1.75rem;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .admin-content {
            padding: 1.25rem 1.5rem;
            flex-grow: 1;
        }

        .admin-card {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.9);
            border-radius: 20px;
            box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.04);
        }

        .user-avatar-pill {
            background: linear-gradient(135deg, #1d4ed8, #2563eb);
            color: #ffffff;
            width: 38px;
            height: 38px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.95rem;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.3);
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
            .main-wrapper {
                margin-left: 0;
            }
            .admin-content {
                padding: 1.25rem;
            }
        }
    </style>

    @stack('styles')
    @include('components.brand-head')
</head>
<body class="sa-app-body">
    @include('components.brand-intro')

    <!-- Sidebar -->
    <aside class="sidebar" id="adminSidebar">
        <a href="{{ route('admin.dashboard') }}" class="sidebar-brand">
            <div class="sidebar-brand-icon">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            <div>
                <div>Smart Attendance</div>
                <div class="small fw-normal text-muted" style="font-size: 0.75rem;">Admin Panel</div>
            </div>
        </a>

        <ul class="sidebar-nav">
            <li class="nav-header">Menu Utama</li>
            <li>
                <a href="{{ route('admin.dashboard') }}" class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>

            <li class="nav-header">Persetujuan & Izin</li>
            <li>
                @php
                    $pendingTeacherLeavesCount = \App\Models\Attendance::whereHas('user', fn ($q) => $q->where('role', 'teacher'))
                        ->whereIn('status', ['excused', 'sick'])
                        ->where('approval_status', 'pending')
                        ->count();
                @endphp
                <a href="{{ route('admin.teacher-leave-requests.index') }}" class="sidebar-link {{ request()->routeIs('admin.teacher-leave-requests.*') ? 'active' : '' }}">
                    <i class="bi bi-person-check-fill"></i>
                    <span>Persetujuan Izin Guru</span>
                    @if($pendingTeacherLeavesCount > 0)
                        <span class="badge bg-warning text-dark rounded-pill ms-auto fw-bold" style="font-size: 0.72rem;">{{ $pendingTeacherLeavesCount }}</span>
                    @endif
                </a>
            </li>

            <li class="nav-header">Manajemen Data</li>
            <li>
                <a href="{{ route('admin.students.index') }}" class="sidebar-link {{ request()->routeIs('admin.students.*') ? 'active' : '' }}">
                    <i class="bi bi-mortarboard-fill"></i> Manajemen Siswa
                </a>
            </li>
            <li>
                <a href="{{ route('admin.teachers.index') }}" class="sidebar-link {{ request()->routeIs('admin.teachers.*') ? 'active' : '' }}">
                    <i class="bi bi-person-video3"></i> Manajemen Guru
                </a>
            </li>
            <li>
                <a href="{{ route('admin.admins.index') }}" class="sidebar-link {{ request()->routeIs('admin.admins.index') ? 'active' : '' }}">
                    <i class="bi bi-person-gear"></i> Manajemen Admin
                </a>
            </li>
            <li>
                <a href="{{ route('admin.admins.create') }}" class="sidebar-link {{ request()->routeIs('admin.admins.create') ? 'active' : '' }}">
                    <i class="bi bi-person-plus-fill"></i> + Tambah Admin
                </a>
            </li>

            <li class="nav-header">Navigasi</li>
            <li>
                <a href="{{ route('home') }}" class="sidebar-link">
                    <i class="bi bi-house-door-fill"></i>
                    <span>Beranda Utama</span>
                </a>
            </li>

            <li class="nav-header">Pengaturan Sistem</li>
            <li>
                <a href="{{ route('admin.ai-configuration.index') }}" class="sidebar-link {{ request()->routeIs('admin.ai-configuration.*') ? 'active' : '' }}">
                    <i class="bi bi-robot"></i> Konfigurasi AI
                </a>
            </li>
        </ul>
    </aside>

    <!-- Mobile Sidebar Backdrop -->
    <div class="sidebar-backdrop d-lg-none" id="sidebarBackdrop"></div>

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        <!-- Topbar -->
        <header class="admin-topbar d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light d-lg-none border" type="button" id="sidebarToggler">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <div class="d-none d-md-block">
                    <span class="text-muted small">Status Sistem:</span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle ms-1">
                        <i class="bi bi-circle-fill" style="font-size: 0.5rem;"></i> Online
                    </span>
                </div>
                <div class="d-none d-md-flex align-items-center gap-2 ms-2">
                    <a href="{{ route('admin.admins.index') }}" class="btn btn-sm btn-outline-primary rounded-3 px-3 fw-semibold">
                        <i class="bi bi-person-gear me-1"></i> Manajemen Admin
                    </a>
                    <a href="{{ route('admin.admins.create') }}" class="btn btn-sm btn-primary text-white rounded-3 px-3 fw-semibold shadow-sm">
                        <i class="bi bi-person-plus-fill me-1"></i>+ Tambah Admin
                    </a>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="user-avatar-pill">
                        {{ strtoupper(substr(Auth::user()->name ?? 'A', 0, 1)) }}
                    </div>
                    <div class="d-none d-sm-block text-end">
                        <div class="fw-semibold text-dark small">{{ Auth::user()->name ?? 'Administrator' }}</div>
                        <div class="text-muted" style="font-size: 0.75rem;">{{ ucfirst(Auth::user()->role ?? 'Admin') }}</div>
                    </div>
                </div>

                <form action="{{ route('logout') }}" method="POST" class="m-0"
                      data-confirm="Apakah Anda yakin ingin keluar dari sesi Administrator?"
                      data-confirm-title="Konfirmasi Logout"
                      data-confirm-ok="Ya, Keluar"
                      data-confirm-variant="logout">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm rounded-3 px-3 fw-medium">
                        <i class="bi bi-box-arrow-right me-1"></i> Keluar
                    </button>
                </form>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="admin-content">
            @include('components.alerts')
            @yield('content')
        </main>
    </div>

    @include('components.ui-confirm')
    @include('components.smart-chatbot')

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/smart-ui.js') }}?v=1.0"></script>
    <script>
        (function () {
            var sidebar = document.getElementById('adminSidebar');
            var backdrop = document.getElementById('sidebarBackdrop');
            var toggler = document.getElementById('sidebarToggler');

            function toggleSidebar() {
                if (sidebar) sidebar.classList.toggle('show');
                if (backdrop) backdrop.classList.toggle('show');
            }

            function closeSidebar() {
                if (sidebar) sidebar.classList.remove('show');
                if (backdrop) backdrop.classList.remove('show');
            }

            if (toggler) toggler.addEventListener('click', toggleSidebar);
            if (backdrop) backdrop.addEventListener('click', closeSidebar);

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        })();
    </script>
    @stack('scripts')
</body>
</html>
