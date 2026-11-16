@extends('layouts.teacher')

@section('title', 'Detail Pengajuan Izin/Sakit')
@section('page-title', 'Detail Pengajuan Izin / Sakit')

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="card card-custom p-4 mb-4">
            <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-4">
                <div>
                    <span class="text-muted small">ID Pengajuan #{{ $attendance->id }}</span>
                    <h4 class="fw-bold mb-0 text-dark">
                        Permohonan {{ $attendance->status === 'sick' ? 'Sakit' : 'Izin' }}
                    </h4>
                </div>
                <div>
                    @if($attendance->approval_status === 'approved')
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill fs-6">
                            <i class="bi bi-check-circle-fill me-1"></i> Disetujui
                        </span>
                    @elseif($attendance->approval_status === 'rejected')
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 rounded-pill fs-6">
                            <i class="bi bi-x-circle-fill me-1"></i> Ditolak
                        </span>
                    @else
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-2 rounded-pill fs-6">
                            <i class="bi bi-clock me-1"></i> Menunggu Persetujuan
                        </span>
                    @endif
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-sm-6">
                    <label class="text-muted small">Nama Pemohon</label>
                    <div class="fw-bold text-dark fs-6">{{ $attendance->user->name ?? '-' }}</div>
                    <small class="text-muted">{{ $attendance->user->email ?? '-' }}</small>
                </div>
                <div class="col-sm-6">
                    <label class="text-muted small">NIS &amp; Kelas</label>
                    <div class="fw-bold text-dark fs-6">NIS: {{ $attendance->user->student->nis ?? '-' }}</div>
                    <span class="badge bg-light text-dark border">{{ $attendance->user->student->class ?? 'Kelas -' }}</span>
                </div>
                <div class="col-sm-6">
                    <label class="text-muted small">Tanggal Izin / Sakit</label>
                    <div class="fw-semibold text-dark fs-6">
                        <i class="bi bi-calendar3 me-1 text-primary"></i>
                        {{ \Carbon\Carbon::parse($attendance->attendance_date)->translatedFormat('l, d F Y') }}
                    </div>
                </div>
                <div class="col-sm-6">
                    <label class="text-muted small">Waktu Pengajuan Masuk</label>
                    <div class="fw-semibold text-dark">
                        <i class="bi bi-clock me-1 text-primary"></i>
                        {{ $attendance->attendance_time ?: '-' }} WIB
                    </div>
                </div>
            </div>

            <div class="p-3 bg-light rounded-3 mb-4 border">
                <label class="fw-bold text-secondary small mb-1">
                    <i class="bi bi-chat-left-text me-1 text-primary"></i> Keterangan / Alasan Lengkap
                </label>
                <p class="mb-0 text-dark" style="white-space: pre-line;">{{ $attendance->reason ?: 'Tidak ada alasan rinci yang dituliskan.' }}</p>
            </div>

            @if($attendance->approver || $attendance->rejection_note)
                <div class="p-3 bg-body-tertiary rounded-3 mb-4 border">
                    <h6 class="fw-bold text-dark small mb-2">Riwayat Tindakan Guru</h6>
                    @if($attendance->approver)
                        <div class="small text-muted mb-1">
                            <strong>Diproses oleh:</strong> {{ $attendance->approver->name }} ({{ $attendance->approver->email }})
                        </div>
                    @endif
                    @if($attendance->approved_at)
                        <div class="small text-muted mb-1">
                            <strong>Tanggal Tindakan:</strong> {{ $attendance->approved_at->translatedFormat('d F Y H:i:s') }} WIB
                        </div>
                    @endif
                    @if($attendance->rejection_note)
                        <div class="small text-danger mt-2">
                            <strong>Catatan Penolakan:</strong> {{ $attendance->rejection_note }}
                        </div>
                    @endif
                </div>
            @endif

            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pt-2 border-top">
                <a href="{{ route('teacher.leave-requests.index') }}" class="btn btn-outline-secondary rounded-pill px-3">
                    <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
                </a>

                @if($attendance->isPending())
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-danger rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#rejectModal">
                            <i class="bi bi-x-circle me-1"></i> Tolak
                        </button>

                        <form action="{{ route('teacher.leave-requests.approve', $attendance) }}" method="POST" class="d-inline"
                              data-confirm="Apakah Anda yakin ingin menyetujui pengajuan ini?"
                              data-confirm-title="Setujui Pengajuan"
                              data-confirm-ok="Ya, Setujui"
                              data-confirm-variant="success">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-success rounded-pill px-4">
                                <i class="bi bi-check-circle me-1"></i> Setujui Pengajuan
                            </button>
                        </form>
                    </div>

                    <!-- Modal Tolak -->
                    <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content rounded-4 border-0 shadow">
                                <form action="{{ route('teacher.leave-requests.reject', $attendance) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <div class="modal-header border-0 pb-0">
                                        <h5 class="modal-title fw-bold text-danger">Tolak Pengajuan Izin/Sakit</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body py-3">
                                        <label class="form-label small fw-semibold text-secondary">Catatan Alasan Penolakan (Opsional)</label>
                                        <textarea name="rejection_note" class="form-control" rows="3" placeholder="Tuliskan catatan alasan kepada siswa..."></textarea>
                                    </div>
                                    <div class="modal-footer border-0 pt-0">
                                        <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                                        <button type="submit" class="btn btn-danger rounded-pill px-3">Konfirmasi Penolakan</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
