@extends('layouts.admin')

@section('title', 'Detail Presensi — ' . ($attendance->user->name ?? 'Pengguna'))

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">Detail Presensi</h4>
            <p class="text-muted small mb-0">Informasi lengkap pencatatan presensi biometrik.</p>
        </div>
        <div>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary rounded-3">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
            <div class="card admin-card p-4 border-0 shadow-sm">
                <div class="d-flex align-items-center gap-3 border-bottom pb-4 mb-4">
                    <div class="rounded-circle bg-primary-subtle text-primary fw-bold d-flex align-items-center justify-content-center"
                         style="width: 58px; height: 58px; font-size: 1.5rem;">
                        {{ strtoupper(substr($attendance->user->name ?? 'U', 0, 1)) }}
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark mb-1">{{ $attendance->user->name ?? '-' }}</h5>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge {{ $attendance->user?->role === 'teacher' ? 'bg-info-subtle text-info' : 'bg-primary-subtle text-primary' }}">
                                {{ $attendance->user?->role === 'teacher' ? 'Guru' : 'Siswa' }}
                            </span>
                            <span class="text-muted small">{{ $attendance->user->email ?? '-' }}</span>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-6 col-md-4">
                        <span class="text-muted small">NIS / NIP:</span>
                        <div class="fw-semibold text-dark mt-1">
                            {{ $attendance->user?->student?->nis ?? $attendance->user?->teacher?->nip ?? '-' }}
                        </div>
                    </div>

                    <div class="col-6 col-md-4">
                        <span class="text-muted small">Kelas:</span>
                        <div class="fw-semibold text-dark mt-1">
                            {{ $attendance->user?->student?->class ?? '-' }}
                        </div>
                    </div>

                    <div class="col-6 col-md-4">
                        <span class="text-muted small">Tanggal Presensi:</span>
                        <div class="fw-semibold text-dark mt-1">
                            {{ \Carbon\Carbon::parse($attendance->attendance_date)->translatedFormat('l, d F Y') }}
                        </div>
                    </div>

                    <div class="col-6 col-md-4">
                        <span class="text-muted small">Waktu Presensi:</span>
                        <div class="fw-semibold text-dark mt-1">
                            <i class="bi bi-clock me-1 text-primary"></i> {{ $attendance->attendance_time }} WIB
                        </div>
                    </div>

                    <div class="col-6 col-md-4">
                        <span class="text-muted small">Status Kehadiran:</span>
                        <div class="mt-1">
                            @if($attendance->status === 'present')
                                <span class="badge bg-success px-3 py-1">Hadir (Tepat Waktu)</span>
                            @elseif($attendance->status === 'late')
                                <span class="badge bg-warning text-dark px-3 py-1">Terlambat</span>
                            @else
                                <span class="badge bg-secondary px-3 py-1">{{ ucfirst($attendance->status) }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="col-6 col-md-4">
                        <span class="text-muted small">Verifikasi Biometrik:</span>
                        <div class="mt-1">
                            @if($attendance->verification_status === 'verified')
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                    <i class="bi bi-shield-check me-1"></i> Terverifikasi
                                </span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary px-2 py-1">
                                    {{ ucfirst($attendance->verification_status) }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="col-12 border-top pt-3 mt-3">
                        <span class="text-muted small">Waktu Pencatatan Sistem:</span>
                        <div class="text-dark small mt-1">
                            {{ $attendance->created_at?->translatedFormat('d F Y, H:i:s') ?? '-' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
