@extends('layouts.student')

@section('title', 'Riwayat Presensi Siswa')
@section('page-title', 'Riwayat Presensi Siswa')

@section('content')
<!-- Filter Card -->
<div class="card card-custom p-3 mb-4">
    <form method="GET" action="{{ route('student.history') }}" class="row g-2 align-items-center">
        <div class="col-12 col-md-5">
            <label class="form-label small text-muted mb-1">Filter Bulan Presensi</label>
            <input type="month" name="month" class="form-control bg-light" value="{{ request('month') }}">
        </div>

        <div class="col-12 col-md-4">
            <label class="form-label small text-muted mb-1">Status Kehadiran</label>
            <select name="status" class="form-select bg-light">
                <option value="">Semua Status</option>
                <option value="present" {{ request('status') === 'present' ? 'selected' : '' }}>Hadir Tepat Waktu</option>
                <option value="late" {{ request('status') === 'late' ? 'selected' : '' }}>Terlambat</option>
                <option value="excused" {{ request('status') === 'excused' ? 'selected' : '' }}>Izin</option>
                <option value="sick" {{ request('status') === 'sick' ? 'selected' : '' }}>Sakit</option>
            </select>
        </div>

        <div class="col-12 col-md-3 d-flex align-items-end gap-1 mt-auto">
            <button type="submit" class="btn btn-student-primary flex-grow-1">
                <i class="bi bi-funnel me-1"></i> Terapkan
            </button>
            @if(request()->anyFilled(['month', 'status']))
                <a href="{{ route('student.history') }}" class="btn btn-light border text-muted" title="Reset Filter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            @endif
        </div>
    </form>
</div>

<!-- History Table Card -->
<div class="card card-custom">
    <div class="card-body p-0">
        @if($attendances->isEmpty())
            <div class="text-center py-5">
                <i class="bi bi-calendar-x text-muted display-4"></i>
                <h6 class="fw-bold text-dark mt-2">Tidak Ada Data Presensi</h6>
                <p class="text-muted small mb-0">Tidak ditemukan catatan kehadiran pada filter yang dipilih.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light border-bottom">
                        <tr class="text-muted small">
                            <th class="ps-3 py-3">Tanggal</th>
                            <th>Waktu Masuk</th>
                            <th>Status Kehadiran</th>
                            <th>Verifikasi Biometrik</th>
                            <th>Keterangan / Status Persetujuan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($attendances as $att)
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold text-dark">
                                        {{ \Carbon\Carbon::parse($att->attendance_date)->translatedFormat('l, d F Y') }}
                                    </div>
                                </td>
                                <td>
                                    <span class="small text-muted">
                                        <i class="bi bi-clock me-1"></i>{{ $att->attendance_time ?: '-' }} WIB
                                    </span>
                                </td>
                                <td>
                                    @if($att->status === 'present')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            <i class="bi bi-check-circle-fill me-1"></i> Hadir Tepat Waktu
                                        </span>
                                    @elseif($att->status === 'late')
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                            <i class="bi bi-alarm-fill me-1"></i> Hadir Terlambat
                                        </span>
                                    @elseif($att->status === 'sick')
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                            <i class="bi bi-bandaid me-1"></i> Sakit
                                        </span>
                                    @elseif($att->status === 'excused')
                                        <span class="badge bg-info-subtle text-info border border-info-subtle">
                                            <i class="bi bi-calendar-event me-1"></i> Izin
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if(in_array($att->status, ['present', 'late']))
                                        <span class="badge bg-light text-secondary border">
                                            <i class="bi bi-shield-check text-success me-1"></i> Face Recognized
                                        </span>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if(in_array($att->status, ['sick', 'excused']))
                                        <div class="small text-dark">{{ $att->reason ?: '-' }}</div>
                                        <div class="mt-1">
                                            @if($att->approval_status === 'approved')
                                                <span class="badge bg-success-subtle text-success border border-success-subtle small">
                                                    Disetujui Guru
                                                </span>
                                            @elseif($att->approval_status === 'rejected')
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle small">
                                                    Ditolak
                                                </span>
                                                @if($att->rejection_note)
                                                    <small class="text-danger d-block mt-0.5">Catatan: {{ $att->rejection_note }}</small>
                                                @endif
                                            @else
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle small">
                                                    Menunggu Persetujuan
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="small text-muted">Kehadiran fisik tercatat</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($attendances->hasPages())
                <div class="p-3 border-top">
                    {{ $attendances->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
