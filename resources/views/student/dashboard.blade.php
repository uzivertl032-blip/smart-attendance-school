@extends('layouts.student')

@section('title', 'Dashboard Siswa — Smart Attendance 2026')
@section('page-title', 'Dashboard Siswa')

@section('content')
<div class="container-fluid p-0">
    {{-- =========================================================
         1. STUDENT PROFILE WELCOME BANNER (Clean SaaS Card)
         ========================================================= --}}
    <div class="clean-premium-card p-3 p-md-3.5 mb-3 sa-reveal">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="avatar-pill-2026 bg-success text-white" style="width: 46px; height: 46px; font-size: 1.25rem;">
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h4 class="fw-bold text-dark mb-0 fs-5">Halo, {{ $user->name }}</h4>
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-0.5 small">
                            Siswa Aktif
                        </span>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2 gap-md-3 text-muted small" style="font-size: 0.8rem;">
                        <span><i class="bi bi-card-text text-secondary me-1"></i> NIS: <strong class="text-dark">{{ $student->nis ?? '-' }}</strong></span>
                        <span>&bull;</span>
                        <span><i class="bi bi-door-closed text-secondary me-1"></i> Kelas: <strong class="text-dark">{{ $student->class ?? '-' }}</strong></span>
                        <span>&bull;</span>
                        <span>
                            <i class="bi bi-person-bounding-box text-secondary me-1"></i> Wajah:
                            @if($student && $student->hasFaceRegistered())
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2">Terdaftar</span>
                            @else
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2">Belum Terdaftar</span>
                            @endif
                        </span>
                    </div>
                </div>
            </div>

            {{-- Live Digital Clock & Action Buttons --}}
            <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 rounded-3 bg-light border">
                    <i class="bi bi-clock text-primary"></i>
                    <span class="small text-muted">Waktu:</span>
                    <span id="liveDigitalClock" class="font-monospace fw-bold text-dark small">--:--:-- WIB</span>
                </div>

                <a href="{{ route('attendance.index') }}" target="_blank" class="btn btn-primary btn-sm rounded-3 fw-semibold shadow-sm px-3 py-1.5" style="background-color: #2563eb; border-color: #2563eb;">
                    <i class="bi bi-camera-video me-1"></i> Scanner Presensi
                </a>
                <a href="{{ route('student.leave-requests.index') }}" class="btn btn-outline-secondary btn-sm rounded-3 px-3 py-1.5">
                    <i class="bi bi-envelope-plus me-1"></i> Ajukan Izin / Sakit
                </a>
            </div>
        </div>
    </div>

    {{-- =========================================================
         2. TODAY'S ATTENDANCE STATUS CARD (High-Tech Hero Status)
         ========================================================= --}}
    <div class="clean-premium-card p-3 p-md-3.5 mb-3 sa-reveal" style="border-left: 4px solid {{ $todayAttendance ? '#10b981' : '#f59e0b' }} !important;">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="avatar-pill-2026 {{ $todayAttendance ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis' }}" style="width: 46px; height: 46px; font-size: 1.35rem;">
                    <i class="bi {{ $todayAttendance ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill' }}"></i>
                </div>
                <div>
                    <span class="text-muted small fw-bold text-uppercase" style="letter-spacing: 0.04em; font-size: 0.72rem;">Status Presensi Hari Ini</span>
                    <h5 class="fw-bold mb-0 text-dark fs-6">
                        @if($todayAttendance)
                            @if($todayAttendance->status === 'present')
                                <span class="text-success"><i class="bi bi-check2 me-1"></i> Sudah Hadir (Tepat Waktu)</span>
                            @elseif($todayAttendance->status === 'late')
                                <span class="text-warning-emphasis"><i class="bi bi-clock-history me-1"></i> Sudah Hadir (Terlambat)</span>
                            @elseif($todayAttendance->status === 'sick')
                                <span class="text-danger"><i class="bi bi-bandaid me-1"></i> Tercatat Sakit ({{ $todayAttendance->getApprovalStatusLabel() }})</span>
                            @elseif($todayAttendance->status === 'excused')
                                <span class="text-info"><i class="bi bi-calendar-event me-1"></i> Tercatat Izin ({{ $todayAttendance->getApprovalStatusLabel() }})</span>
                            @endif
                        @else
                            <span class="text-secondary"><i class="bi bi-hourglass-split me-1"></i> Belum Melakukan Presensi Hari Ini</span>
                        @endif
                    </h5>
                    <small class="text-muted" style="font-size: 0.78rem;">
                        {{ \Carbon\Carbon::today()->translatedFormat('l, d F Y') }}
                        @if($todayAttendance && $todayAttendance->attendance_time)
                            &bull; Pukul: <strong class="text-dark">{{ $todayAttendance->attendance_time }} WIB</strong>
                        @endif
                    </small>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                @if($todayAttendance)
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1.5 rounded-pill small">
                        <i class="bi bi-shield-check me-1"></i> Verifikasi Biometrik Sukses
                    </span>
                @else
                    <a href="{{ route('attendance.index') }}" class="btn btn-cyber-primary btn-sm px-3 py-1.5" target="_blank" style="background: linear-gradient(135deg, #059669, #0d9488) !important;">
                        <i class="bi bi-camera-video-fill me-1"></i> Buka Kamera &amp; Absen Sekarang
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- =========================================================
         3. JADWAL PEMBELAJARAN HARI INI (Tepat Setelah Status Presensi)
         ========================================================= --}}
    <div class="clean-premium-card p-3 p-md-3.5 mb-3 sa-reveal">
        <div class="d-flex align-items-center justify-content-between mb-2.5 pb-2 border-bottom">
            <div class="d-flex align-items-center gap-2">
                <div class="avatar-pill-2026 bg-primary-subtle text-primary" style="width: 34px; height: 34px; font-size: 1rem;">
                    <i class="bi bi-calendar3-week"></i>
                </div>
                <div>
                    <h5 class="fw-bold text-dark mb-0 fs-6">Jadwal Pembelajaran Kelas {{ $student->class ?? 'Siswa' }}</h5>
                    <small class="text-muted" style="font-size: 0.75rem;">Agenda belajar mengajar hari ini &bull; {{ \Carbon\Carbon::now()->translatedFormat('l, d F Y') }}</small>
                </div>
            </div>
            <span class="badge bg-light text-primary border rounded-pill px-2.5 py-1 small fw-semibold" style="font-size: 0.72rem;">
                <i class="bi bi-clock me-1"></i> Jam Efektif
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-sm">
                <thead class="table-light small text-uppercase text-muted" style="font-size: 0.72rem;">
                    <tr>
                        <th class="ps-3 py-2">Sesi / Jam</th>
                        <th class="py-2">Mata Pelajaran</th>
                        <th class="py-2">Tenaga Pengajar</th>
                        <th class="py-2">Ruangan</th>
                        <th class="pe-3 py-2 text-end">Status</th>
                    </tr>
                </thead>
                <tbody class="small" style="font-size: 0.82rem;">
                    <tr>
                        <td class="ps-3 py-2 font-monospace fw-bold text-primary">07:15 - 08:45</td>
                        <td class="py-2">
                            <strong class="text-dark d-block">Matematika Wajib</strong>
                            <span class="text-muted extra-small" style="font-size: 0.72rem;">Kalkulus &amp; Trigonometri</span>
                        </td>
                        <td class="py-2">Dra. Hj. Nurhayati, M.Pd</td>
                        <td class="py-2"><span class="badge bg-light text-dark border">Lab Komputer A</span></td>
                        <td class="pe-3 py-2 text-end"><span class="badge bg-success-subtle text-success rounded-pill px-2 py-0.5">Selesai</span></td>
                    </tr>
                    <tr>
                        <td class="ps-3 py-2 font-monospace fw-bold text-primary">08:45 - 10:15</td>
                        <td class="py-2">
                            <strong class="text-dark d-block">Bahasa Inggris</strong>
                            <span class="text-muted extra-small" style="font-size: 0.72rem;">Advanced Academic Reading</span>
                        </td>
                        <td class="py-2">Ahmad Fauzi, S.Pd</td>
                        <td class="py-2"><span class="badge bg-light text-dark border">Ruang {{ $student->class ?? 'Kelas' }}</span></td>
                        <td class="pe-3 py-2 text-end"><span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-0.5">Berlangsung</span></td>
                    </tr>
                    <tr>
                        <td class="ps-3 py-2 font-monospace text-muted">10:15 - 10:45</td>
                        <td colspan="3" class="text-center text-muted fw-semibold bg-light py-1.5">
                            <i class="bi bi-cup-hot-fill text-warning me-1"></i> Istirahat &amp; Rehat Siang
                        </td>
                        <td class="pe-3 py-2 text-end"><span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-0.5">Rehat</span></td>
                    </tr>
                    <tr>
                        <td class="ps-3 py-2 font-monospace fw-bold text-primary">10:45 - 12:15</td>
                        <td class="py-2">
                            <strong class="text-dark d-block">Informatika &amp; Coding</strong>
                            <span class="text-muted extra-small" style="font-size: 0.72rem;">Algoritma &amp; Pemrograman Web</span>
                        </td>
                        <td class="py-2">Budi Santoso, S.Kom</td>
                        <td class="py-2"><span class="badge bg-light text-dark border">Lab Robotika</span></td>
                        <td class="pe-3 py-2 text-end"><span class="badge bg-light text-secondary border rounded-pill px-2 py-0.5">Mendatang</span></td>
                    </tr>
                    <tr>
                        <td class="ps-3 py-2 font-monospace fw-bold text-primary">13:00 - 14:30</td>
                        <td class="py-2">
                            <strong class="text-dark d-block">Fisika Terapan</strong>
                            <span class="text-muted extra-small" style="font-size: 0.72rem;">Mekanika Gelombang &amp; Optik</span>
                        </td>
                        <td class="py-2">Rina Wijaya, M.Si</td>
                        <td class="py-2"><span class="badge bg-light text-dark border">Lab Fisika</span></td>
                        <td class="pe-3 py-2 text-end"><span class="badge bg-light text-secondary border rounded-pill px-2 py-0.5">Mendatang</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- =========================================================
         4. STATS CARDS (4 Key Student Metrics)
         ========================================================= --}}
    <div class="row g-2 g-md-3 mb-3">
        {{-- Tingkat Kehadiran --}}
        <div class="col-6 col-lg-3 sa-reveal">
            <div class="stat-card-2026 stat-card-2026--primary">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="stat-lbl-2026">Tingkat Kehadiran</span>
                    <div class="stat-icon-wrap stat-icon-wrap--primary">
                        <i class="bi bi-pie-chart-fill"></i>
                    </div>
                </div>
                <div>
                    <div class="stat-val-2026 text-primary">{{ $stats['attendance_percentage'] }}%</div>
                    <div class="progress mt-2" style="height: 5px;">
                        <div class="progress-bar bg-primary" role="progressbar" style="width: {{ min(100, $stats['attendance_percentage']) }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Hadir Tepat Waktu --}}
        <div class="col-6 col-lg-3 sa-reveal">
            <div class="stat-card-2026 stat-card-2026--success">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="stat-lbl-2026">Hadir Tepat Waktu</span>
                    <div class="stat-icon-wrap stat-icon-wrap--success">
                        <i class="bi bi-check-lg"></i>
                    </div>
                </div>
                <div>
                    <div class="stat-val-2026 text-success" data-counter="{{ $stats['total_present'] }}">0</div>
                    <span class="stat-sub-2026">Total Hari Hadir</span>
                </div>
            </div>
        </div>

        {{-- Terlambat --}}
        <div class="col-6 col-lg-3 sa-reveal">
            <div class="stat-card-2026 stat-card-2026--warning">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="stat-lbl-2026">Terlambat</span>
                    <div class="stat-icon-wrap stat-icon-wrap--warning">
                        <i class="bi bi-clock-history"></i>
                    </div>
                </div>
                <div>
                    <div class="stat-val-2026 text-warning" data-counter="{{ $stats['total_late'] }}">0</div>
                    <span class="stat-sub-2026">Lewat 07:15 WIB</span>
                </div>
            </div>
        </div>

        {{-- Izin & Sakit --}}
        <div class="col-6 col-lg-3 sa-reveal">
            <div class="stat-card-2026 stat-card-2026--info">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="stat-lbl-2026">Izin &amp; Sakit</span>
                    <div class="stat-icon-wrap stat-icon-wrap--info">
                        <i class="bi bi-file-earmark-medical"></i>
                    </div>
                </div>
                <div>
                    <div class="stat-val-2026 text-info" data-counter="{{ $stats['total_excused'] + $stats['total_sick'] }}">0</div>
                    <span class="stat-sub-2026">Izin: {{ $stats['total_excused'] }} &bull; Sakit: {{ $stats['total_sick'] }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- =========================================================
         5. GRAFIK RIWAYAT KEHADIRAN & INFO JADWAL
         ========================================================= --}}
    <div class="row g-3 mb-3">
        {{-- Grafik Kehadiran Pribadi --}}
        <div class="col-12 col-lg-8 sa-reveal">
            <div class="clean-premium-card p-3 p-md-3.5 h-100">
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-2.5 gap-2">
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="pulse-dot pulse-dot--active"></span>
                            <h5 class="fw-bold text-dark mb-0 fs-6">Tren Presensi Pribadi</h5>
                        </div>
                        <small class="text-muted" style="font-size: 0.75rem;">Grafik konsistensi kehadiran Anda di sekolah</small>
                    </div>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill small fw-semibold" style="font-size: 0.72rem;">
                        <i class="bi bi-stars me-1"></i> Performa Aktif
                    </span>
                </div>
                <div style="height: 190px; position: relative;">
                    <canvas id="studentAttendanceChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Info Jadwal Sekolah --}}
        <div class="col-12 col-lg-4 sa-reveal">
            <div class="clean-premium-card p-3 p-md-3.5 h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2.5">
                        <h5 class="fw-bold text-dark mb-0 fs-6">Jadwal Masuk Sekolah</h5>
                        <span class="badge bg-light text-secondary border small" style="font-size: 0.72rem;">Ketentuan</span>
                    </div>

                    <div class="p-2.5 rounded-3 mb-2 bg-light border">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="small text-muted" style="font-size: 0.78rem;">Gerbang Presensi:</span>
                            <span class="fw-bold text-success font-monospace small">06:30 WIB</span>
                        </div>
                    </div>

                    <div class="p-2.5 rounded-3 mb-2 bg-light border">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="small text-muted" style="font-size: 0.78rem;">Batas Tepat Waktu:</span>
                            <span class="fw-bold text-primary font-monospace small">07:15 WIB</span>
                        </div>
                    </div>

                    <div class="p-2.5 rounded-3 mb-2.5 bg-light border">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="small text-muted" style="font-size: 0.78rem;">Status Terlambat:</span>
                            <span class="fw-bold text-warning font-monospace small">&gt; 07:15 WIB</span>
                        </div>
                    </div>
                </div>

                <div class="pt-2 border-top">
                    <a href="{{ route('student.leave-requests.index') }}" class="btn btn-outline-success btn-sm w-100 rounded-3 fw-semibold py-1.5">
                        <i class="bi bi-send-plus me-1"></i> Ajukan Surat Izin / Sakit
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- =========================================================
         6. TABEL CATATAN PRESENSI TERKINI (7 Catatan Terakhir)
         ========================================================= --}}
    <div class="clean-premium-card p-0 mb-3 sa-reveal">
        <div class="p-3 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <div class="avatar-pill-2026 bg-success-subtle text-success" style="width: 34px; height: 34px; font-size: 1rem;">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div>
                    <h5 class="fw-bold text-dark mb-0 fs-6">Catatan Presensi Terkini</h5>
                    <small class="text-muted" style="font-size: 0.75rem;">7 riwayat kehadiran sekolah terakhir Anda</small>
                </div>
            </div>
            <a href="{{ route('student.history') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 small">
                Lihat Semua Riwayat
            </a>
        </div>

        <div class="p-0">
            @if($recentAttendances->isEmpty())
                <div class="text-center py-4">
                    <i class="bi bi-calendar-x text-muted display-6"></i>
                    <h6 class="fw-bold text-dark mt-2 mb-1">Belum Ada Catatan Kehadiran</h6>
                    <p class="text-muted small mb-0">Lakukan absensi di scanner kiosk sekolah untuk merekam kehadiran harian.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table-modern-2026 mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3 py-2">Hari &amp; Tanggal</th>
                                <th class="py-2">Waktu Masuk</th>
                                <th class="py-2">Status Kehadiran</th>
                                <th class="py-2">Keterangan / Status Guru</th>
                                <th class="pe-3 py-2 text-end">Verifikasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentAttendances as $att)
                                <tr>
                                    <td class="ps-3 py-2">
                                        <div class="fw-bold text-dark small">
                                            {{ \Carbon\Carbon::parse($att->attendance_date)->translatedFormat('l, d F Y') }}
                                        </div>
                                    </td>
                                    <td class="py-2">
                                        <span class="small text-dark fw-medium">
                                            <i class="bi bi-clock me-1 text-primary"></i>{{ $att->attendance_time ?: '-' }} WIB
                                        </span>
                                    </td>
                                    <td class="py-2">
                                        @if($att->status === 'present')
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-0.5">
                                                <i class="bi bi-check-circle-fill me-1"></i> Tepat Waktu
                                            </span>
                                        @elseif($att->status === 'late')
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2.5 py-0.5">
                                                <i class="bi bi-clock-history me-1"></i> Terlambat
                                            </span>
                                        @elseif($att->status === 'sick')
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-0.5">
                                                <i class="bi bi-bandaid me-1"></i> Sakit
                                            </span>
                                        @elseif($att->status === 'excused')
                                            <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2.5 py-0.5">
                                                <i class="bi bi-calendar-event me-1"></i> Izin
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-2">
                                        @if(in_array($att->status, ['sick', 'excused']))
                                            <div class="small text-dark">{{ $att->reason ?: '-' }}</div>
                                            <small class="text-muted" style="font-size: 0.72rem;">Persetujuan: <strong>{{ $att->getApprovalStatusLabel() }}</strong></small>
                                        @else
                                            <span class="small text-muted" style="font-size: 0.78rem;">Hadir melalui pemindai kamera</span>
                                        @endif
                                    </td>
                                    <td class="pe-3 py-2 text-end">
                                        <span class="badge bg-light text-secondary border rounded-pill px-2.5 py-0.5 small">
                                            <i class="bi bi-shield-check text-success me-1"></i> Biometrik
                                        </span>
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
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Live Digital Clock Ticker
    function updateLiveClock() {
        const el = document.getElementById('liveDigitalClock');
        if (!el) return;
        const now = new Date();
        const h = String(now.getHours()).padStart(2, '0');
        const m = String(now.getMinutes()).padStart(2, '0');
        const s = String(now.getSeconds()).padStart(2, '0');
        el.textContent = `${h}:${m}:${s} WIB`;
    }
    updateLiveClock();
    setInterval(updateLiveClock, 1000);

    const canvas = document.getElementById('studentAttendanceChart');
    if (canvas && typeof Chart !== 'undefined') {
        const ctx = canvas.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 190);
        gradient.addColorStop(0, 'rgba(16, 185, 129, 0.35)');
        gradient.addColorStop(1, 'rgba(52, 211, 153, 0.02)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['H-6', 'H-5', 'H-4', 'H-3', 'H-2', 'Kemarin', 'Hari Ini'],
                datasets: [{
                    label: 'Status Kehadiran',
                    data: [1, 1, 1, 1, 1, 1, {{ $todayAttendance ? 1 : 0 }}],
                    borderColor: '#10b981',
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#059669',
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
                        cornerRadius: 8,
                        callbacks: {
                            label: function (context) {
                                return context.raw === 1 ? 'Hadir / Izin Tercatat' : 'Belum Absen';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        min: 0,
                        max: 1.2,
                        ticks: {
                            stepSize: 1,
                            callback: function (val) {
                                if (val === 1) return 'Hadir';
                                if (val === 0) return 'Belum';
                                return '';
                            },
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
