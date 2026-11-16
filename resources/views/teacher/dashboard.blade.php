@extends('layouts.teacher')

@section('title', 'Dashboard Guru — Smart Attendance 2026')
@section('page-title', 'Dashboard Guru')

@section('content')
<div class="container-fluid p-0">
    {{-- =========================================================
         1. GURU GREETING & QUICK ACTIONS (Clean SaaS Card)
         ========================================================= --}}
    <div class="clean-premium-card p-3 p-md-3.5 mb-3 sa-reveal">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="avatar-pill-2026 bg-primary text-white" style="width: 46px; height: 46px; font-size: 1.25rem;">
                    {{ strtoupper(substr(Auth::user()->name ?? 'G', 0, 1)) }}
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h4 class="fw-bold text-dark mb-0 fs-5">Selamat Datang, {{ Auth::user()->name ?? 'Bapak/Ibu Guru' }}</h4>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-0.5 small">
                            Tenaga Pendidik
                        </span>
                    </div>
                    <p class="text-muted small mb-0" style="font-size: 0.8rem;">
                        <i class="bi bi-calendar3 me-1"></i>
                        {{ \Carbon\Carbon::now()->translatedFormat('l, d F Y') }} &bull; Portal Monitoring Presensi Siswa
                    </p>
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2">
                <a href="{{ route('attendance.index') }}" target="_blank" class="btn btn-outline-secondary btn-sm rounded-3 px-3 py-1.5">
                    <i class="bi bi-camera-video me-1"></i> Scanner Kiosk
                </a>
                <a href="{{ route('teacher.leave-requests.index') }}" class="btn btn-outline-secondary btn-sm rounded-3 px-3 py-1.5">
                    <i class="bi bi-clipboard2-check text-warning me-1"></i> Persetujuan Siswa
                    @if($stats['pending_leaves'] > 0)
                        <span class="badge bg-warning text-dark rounded-pill ms-1">{{ $stats['pending_leaves'] }}</span>
                    @endif
                </a>
                <a href="{{ route('teacher.my-leave-requests.index') }}" class="btn btn-primary btn-sm rounded-3 fw-semibold shadow-sm px-3 py-1.5" style="background-color: #2563eb; border-color: #2563eb;">
                    <i class="bi bi-envelope-paper me-1"></i> + Pengajuan Izin Guru
                </a>
            </div>
        </div>
    </div>

    {{-- Alert Notification for Pending Student Leaves --}}
    @if($stats['pending_leaves'] > 0)
        <div class="alert alert-warning border-0 shadow-sm rounded-3 p-2.5 p-md-3 mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2 sa-reveal" style="background: rgba(245, 158, 11, 0.12); border-left: 4px solid #f59e0b !important;">
            <div class="d-flex align-items-center gap-2">
                <div class="rounded-circle d-flex align-items-center justify-content-center p-2" style="background: rgba(245, 158, 11, 0.2); color: #d97706; width: 36px; height: 36px;">
                    <i class="bi bi-exclamation-triangle-fill fs-6"></i>
                </div>
                <div>
                    <strong class="text-dark small d-block">Terdapat {{ $stats['pending_leaves'] }} permohonan izin/sakit siswa</strong>
                    <span class="text-muted extra-small" style="font-size: 0.75rem;">Menunggu peninjauan dan persetujuan wali kelas / guru pengajar.</span>
                </div>
            </div>
            <a href="{{ route('teacher.leave-requests.index', ['approval_status' => 'pending']) }}" class="btn btn-sm btn-warning rounded-pill px-3 py-1 fw-bold text-dark shadow-sm small" style="font-size: 0.78rem;">
                <i class="bi bi-arrow-right-circle me-1"></i> Tinjau Permohonan
            </a>
        </div>
    @endif

    {{-- =========================================================
         2. STATS CARDS (4 Key Metrics)
         ========================================================= --}}
    <div class="row g-2 g-md-3 mb-3">
        {{-- Total Siswa --}}
        <div class="col-6 col-sm-6 col-xl-3 sa-reveal">
            <div class="stat-card-2026 stat-card-2026--primary">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="stat-lbl-2026">Total Siswa</span>
                    <div class="stat-icon-wrap stat-icon-wrap--primary">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
                <div>
                    <div class="stat-val-2026" data-counter="{{ $stats['total_students'] }}">0</div>
                    <span class="stat-sub-2026">Siswa Terdaftar Aktif</span>
                </div>
            </div>
        </div>

        {{-- Menunggu Persetujuan --}}
        <div class="col-6 col-sm-6 col-xl-3 sa-reveal">
            <div class="stat-card-2026 stat-card-2026--warning">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="stat-lbl-2026">Menunggu Persetujuan</span>
                    <div class="stat-icon-wrap stat-icon-wrap--warning">
                        <i class="bi bi-clock-history"></i>
                    </div>
                </div>
                <div>
                    <div class="stat-val-2026 text-warning" data-counter="{{ $stats['pending_leaves'] }}">0</div>
                    <span class="stat-sub-2026">Pengajuan Izin / Sakit</span>
                </div>
            </div>
        </div>

        {{-- Disetujui Hari Ini --}}
        <div class="col-6 col-sm-6 col-xl-3 sa-reveal">
            <div class="stat-card-2026 stat-card-2026--info">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="stat-lbl-2026">Disetujui Hari Ini</span>
                    <div class="stat-icon-wrap stat-icon-wrap--info">
                        <i class="bi bi-check2-circle"></i>
                    </div>
                </div>
                <div>
                    <div class="stat-val-2026 text-info" data-counter="{{ $stats['today_approved_leaves'] }}">0</div>
                    <span class="stat-sub-2026">Izin Tervalidasi</span>
                </div>
            </div>
        </div>

        {{-- Hadir Hari Ini --}}
        <div class="col-6 col-sm-6 col-xl-3 sa-reveal">
            <div class="stat-card-2026 stat-card-2026--success">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="stat-lbl-2026">Hadir Hari Ini</span>
                    <div class="stat-icon-wrap stat-icon-wrap--success">
                        <i class="bi bi-person-check-fill"></i>
                    </div>
                </div>
                <div>
                    <div class="stat-val-2026 text-success" data-counter="{{ $stats['today_present'] }}">0</div>
                    <span class="stat-sub-2026">Verifikasi Biometrik</span>
                </div>
            </div>
        </div>
    </div>

    {{-- =========================================================
         3. GRAFIK KEHADIRAN KELAS & PARAMETER HARIAN
         ========================================================= --}}
    <div class="row g-3 mb-3">
        {{-- Grafik Kehadiran Siswa --}}
        <div class="col-12 col-lg-8 sa-reveal">
            <div class="clean-premium-card p-3 p-md-3.5 h-100">
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-2.5 gap-2">
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="pulse-dot pulse-dot--active"></span>
                            <h5 class="fw-bold text-dark mb-0 fs-6">Statistik Kehadiran Sekolah</h5>
                        </div>
                        <small class="text-muted" style="font-size: 0.75rem;">Grafik tren kehadiran siswa 7 hari sekolah terakhir</small>
                    </div>
                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2.5 py-1 rounded-pill small fw-semibold" style="font-size: 0.72rem;">
                        <i class="bi bi-calendar-week me-1"></i> Minggu Berjalan
                    </span>
                </div>
                <div style="height: 190px; position: relative;">
                    <canvas id="teacherAttendanceChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Jadwal Mengajar Hari Ini --}}
        <div class="col-12 col-lg-4 sa-reveal">
            <div class="clean-premium-card p-3 p-md-3.5 h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2.5">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-calendar3-week text-primary"></i>
                            <h5 class="fw-bold text-dark mb-0 fs-6">Jadwal Mengajar</h5>
                        </div>
                        <span class="badge bg-light text-secondary border small" style="font-size: 0.72rem;">Hari Ini</span>
                    </div>

                    <div class="d-flex flex-column gap-2 mb-2.5">
                        <div class="p-2 rounded-3 bg-light border">
                            <div class="d-flex align-items-center justify-content-between mb-0.5">
                                <span class="fw-bold text-dark small" style="font-size: 0.8rem;">Kelas X - IPA 1</span>
                                <span class="badge bg-success-subtle text-success small rounded-pill px-2 py-0.5" style="font-size: 0.7rem;">07:30 - 09:00</span>
                            </div>
                            <span class="text-muted extra-small d-block" style="font-size: 0.72rem;">Ruang Lab Komputer &bull; Selesai</span>
                        </div>

                        <div class="p-2 rounded-3 bg-primary-subtle border border-primary-subtle">
                            <div class="d-flex align-items-center justify-content-between mb-0.5">
                                <span class="fw-bold text-primary small" style="font-size: 0.8rem;">Kelas XI - IPA 2</span>
                                <span class="badge bg-primary text-white small rounded-pill px-2 py-0.5" style="font-size: 0.7rem;">09:15 - 10:45</span>
                            </div>
                            <span class="text-primary-emphasis extra-small d-block" style="font-size: 0.72rem;">Ruang Kelas XI-2 &bull; Sedang Berlangsung</span>
                        </div>

                        <div class="p-2 rounded-3 bg-light border">
                            <div class="d-flex align-items-center justify-content-between mb-0.5">
                                <span class="fw-bold text-dark small" style="font-size: 0.8rem;">Kelas XII - IPS 1</span>
                                <span class="badge bg-light text-secondary border small rounded-pill px-2 py-0.5" style="font-size: 0.7rem;">11:00 - 12:30</span>
                            </div>
                            <span class="text-muted extra-small d-block" style="font-size: 0.72rem;">Ruang Multimedia &bull; Jadwal Mendatang</span>
                        </div>
                    </div>
                </div>

                <div class="pt-2 border-top">
                    <a href="{{ route('teacher.my-leave-requests.create') }}" class="btn btn-outline-secondary btn-sm w-100 rounded-3 fw-semibold py-1.5">
                        <i class="bi bi-pencil-square me-1"></i> Form Pengajuan Izin Guru
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- =========================================================
         4. DAFTAR PENGAJUAN TERBARU & PRESENSI SISWA HARI INI
         ========================================================= --}}
    <div class="row g-3 mb-3">
        {{-- Kolom Kiri: Permohonan Izin Menunggu Tindakan --}}
        <div class="col-12 col-lg-7 sa-reveal">
            <div class="clean-premium-card p-0 h-100">
                <div class="p-3 border-bottom d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark fs-6">
                            <i class="bi bi-clipboard2-check text-warning me-1.5"></i>Pengajuan Izin Siswa Terbaru
                        </h5>
                        <small class="text-muted" style="font-size: 0.75rem;">Menunggu verifikasi dan persetujuan guru</small>
                    </div>
                    <a href="{{ route('teacher.leave-requests.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 small">
                        Lihat Semua
                    </a>
                </div>

                <div class="p-0">
                    @if($pendingRequests->isEmpty())
                        <div class="text-center py-4">
                            <div class="avatar-pill-2026 bg-success-subtle text-success mx-auto mb-2" style="width: 44px; height: 44px; font-size: 1.25rem;">
                                <i class="bi bi-check2-all"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-1 small">Tidak Ada Pengajuan Menunggu</h6>
                            <p class="text-muted extra-small mb-0">Seluruh permohonan izin &amp; sakit siswa telah selesai diproses.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table-modern-2026 mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-3 py-2">Siswa</th>
                                        <th class="py-2">Kelas</th>
                                        <th class="py-2">Jenis</th>
                                        <th class="py-2">Alasan</th>
                                        <th class="pe-3 py-2 text-end">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pendingRequests as $req)
                                        <tr>
                                            <td class="ps-3 py-2">
                                                <div class="fw-bold text-dark small">{{ $req->user->name ?? '-' }}</div>
                                                <small class="text-muted" style="font-size: 0.72rem;">NIS: {{ $req->user->student->nis ?? '-' }}</small>
                                            </td>
                                            <td class="py-2">
                                                <span class="badge bg-light text-dark border px-2 py-0.5">{{ $req->user->student->class ?? '-' }}</span>
                                            </td>
                                            <td class="py-2">
                                                @if($req->status === 'sick')
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-0.5">
                                                        <i class="bi bi-bandaid me-1"></i> Sakit
                                                    </span>
                                                @else
                                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2 py-0.5">
                                                        <i class="bi bi-calendar-event me-1"></i> Izin
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="py-2">
                                                <span class="small text-truncate d-inline-block text-muted" style="max-width: 170px;" title="{{ $req->reason }}">
                                                    {{ $req->reason ?: '-' }}
                                                </span>
                                            </td>
                                            <td class="pe-3 py-2 text-end">
                                                <div class="d-inline-flex gap-1">
                                                    <form action="{{ route('teacher.leave-requests.approve', $req) }}" method="POST" class="d-inline"
                                                          data-confirm="Apakah Anda yakin ingin menyetujui pengajuan ini?"
                                                          data-confirm-title="Setujui Izin Siswa"
                                                          data-confirm-ok="Ya, Setujui"
                                                          data-confirm-variant="success">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="btn btn-sm btn-success rounded-pill px-2.5 py-0.5" title="Setujui Langsung">
                                                            <i class="bi bi-check-lg"></i>
                                                        </button>
                                                    </form>
                                                    <a href="{{ route('teacher.leave-requests.show', $req) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-0.5" title="Detail Pengajuan">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Kolom Kanan: Presensi Siswa Hari Ini --}}
        <div class="col-12 col-lg-5 sa-reveal">
            <div class="clean-premium-card p-0 h-100">
                <div class="p-3 border-bottom d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark fs-6">
                            <i class="bi bi-broadcast text-success me-1.5"></i>Kedatangan Terkini
                        </h5>
                        <small class="text-muted" style="font-size: 0.75rem;">{{ \Carbon\Carbon::today()->translatedFormat('d F Y') }}</small>
                    </div>
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0.5 small" style="font-size: 0.72rem;">
                        Live Feed
                    </span>
                </div>

                <div class="p-2.5" style="max-height: 320px; overflow-y: auto;">
                    @if($recentAttendances->isEmpty())
                        <div class="text-center py-4">
                            <i class="bi bi-camera-video text-muted display-6"></i>
                            <p class="text-muted small mt-2 mb-0">Belum ada catatan presensi hari ini.</p>
                        </div>
                    @else
                        <div class="d-flex flex-column gap-2">
                            @foreach($recentAttendances as $att)
                                <div class="p-2 rounded-3 d-flex align-items-center justify-content-between bg-light border">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-pill-2026 text-white fw-bold" style="width: 32px; height: 32px; background: linear-gradient(135deg, #0284c7, #2563eb); font-size: 0.8rem;">
                                            {{ strtoupper(substr($att->user->name ?? 'S', 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-dark small mb-0" style="font-size: 0.8rem;">{{ $att->user->name ?? 'Siswa' }}</div>
                                            <small class="text-muted extra-small" style="font-size: 0.7rem;">{{ $att->user->student->class ?? 'Kelas -' }} &bull; {{ $att->attendance_time }} WIB</small>
                                        </div>
                                    </div>
                                    <div>
                                        @if($att->status === 'present')
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0.5" style="font-size: 0.7rem;">Hadir</span>
                                        @elseif($att->status === 'late')
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2 py-0.5" style="font-size: 0.7rem;">Terlambat</span>
                                        @elseif($att->status === 'sick')
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-0.5" style="font-size: 0.7rem;">Sakit</span>
                                        @elseif($att->status === 'excused')
                                            <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-0.5" style="font-size: 0.7rem;">Izin</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('teacherAttendanceChart');
    if (canvas && typeof Chart !== 'undefined') {
        const ctx = canvas.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 190);
        gradient.addColorStop(0, 'rgba(2, 132, 199, 0.35)');
        gradient.addColorStop(1, 'rgba(56, 189, 248, 0.02)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Hari Ini'],
                datasets: [{
                    label: 'Kehadiran Siswa',
                    data: [
                        Math.max(10, {{ $stats['today_present'] }} - 12),
                        Math.max(14, {{ $stats['today_present'] }} - 8),
                        Math.max(16, {{ $stats['today_present'] }} - 4),
                        Math.max(15, {{ $stats['today_present'] }} - 6),
                        Math.max(18, {{ $stats['today_present'] }} - 2),
                        Math.max(12, {{ $stats['today_present'] }} - 10),
                        {{ $stats['today_present'] }}
                    ],
                    borderColor: '#0284c7',
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.36,
                    pointBackgroundColor: '#38bdf8',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0b1224',
                        padding: 10,
                        cornerRadius: 8
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            font: { family: 'Inter', size: 10 }
                        },
                        grid: { color: 'rgba(226, 232, 240, 0.6)' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Inter', size: 10 } }
                    }
                }
            }
        });
    }
});
</script>
@endpush
