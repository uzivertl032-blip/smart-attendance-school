@extends('layouts.teacher')

@section('title', 'Pengajuan Izin & Sakit')
@section('page-title', 'Pengajuan Izin & Sakit Siswa')

@section('content')
<!-- Header Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="{{ route('teacher.leave-requests.index') }}" class="text-decoration-none">
            <div class="card card-custom p-3 text-center {{ !request('approval_status') ? 'border-primary shadow-sm' : '' }}">
                <span class="text-muted small fw-semibold">Total Pengajuan</span>
                <h4 class="fw-bold my-1 text-dark">{{ number_format($stats['total_requests']) }}</h4>
                <small class="text-muted">Semua riwayat</small>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="{{ route('teacher.leave-requests.index', ['approval_status' => 'pending']) }}" class="text-decoration-none">
            <div class="card card-custom p-3 text-center {{ request('approval_status') === 'pending' ? 'border-warning shadow-sm' : '' }}">
                <span class="text-muted small fw-semibold">Menunggu</span>
                <h4 class="fw-bold my-1 text-warning-emphasis">{{ number_format($stats['pending_count']) }}</h4>
                <small class="text-muted">Perlu tindakan</small>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="{{ route('teacher.leave-requests.index', ['approval_status' => 'approved']) }}" class="text-decoration-none">
            <div class="card card-custom p-3 text-center {{ request('approval_status') === 'approved' ? 'border-success shadow-sm' : '' }}">
                <span class="text-muted small fw-semibold">Disetujui</span>
                <h4 class="fw-bold my-1 text-success">{{ number_format($stats['approved_count']) }}</h4>
                <small class="text-muted">Tervalidasi</small>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="{{ route('teacher.leave-requests.index', ['approval_status' => 'rejected']) }}" class="text-decoration-none">
            <div class="card card-custom p-3 text-center {{ request('approval_status') === 'rejected' ? 'border-danger shadow-sm' : '' }}">
                <span class="text-muted small fw-semibold">Ditolak</span>
                <h4 class="fw-bold my-1 text-danger">{{ number_format($stats['rejected_count']) }}</h4>
                <small class="text-muted">Tidak diizinkan</small>
            </div>
        </a>
    </div>
</div>

<!-- Filter & Search Card -->
<div class="card card-custom p-3 mb-4">
    <form method="GET" action="{{ route('teacher.leave-requests.index') }}" class="row g-2 align-items-center">
        <div class="col-12 col-md-4">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 text-muted">
                    <i class="bi bi-search"></i>
                </span>
                <input type="text" name="search" class="form-control bg-light border-start-0" placeholder="Cari nama siswa atau NIS..." value="{{ request('search') }}">
            </div>
        </div>

        <div class="col-6 col-md-2">
            <select name="type" class="form-select bg-light">
                <option value="">Semua Jenis</option>
                <option value="excused" {{ request('type') === 'excused' ? 'selected' : '' }}>Izin</option>
                <option value="sick" {{ request('type') === 'sick' ? 'selected' : '' }}>Sakit</option>
            </select>
        </div>

        <div class="col-6 col-md-2">
            <select name="approval_status" class="form-select bg-light">
                <option value="">Semua Status</option>
                <option value="pending" {{ request('approval_status') === 'pending' ? 'selected' : '' }}>Menunggu</option>
                <option value="approved" {{ request('approval_status') === 'approved' ? 'selected' : '' }}>Disetujui</option>
                <option value="rejected" {{ request('approval_status') === 'rejected' ? 'selected' : '' }}>Ditolak</option>
            </select>
        </div>

        <div class="col-6 col-md-2">
            <input type="date" name="date" class="form-control bg-light" value="{{ request('date') }}" title="Filter Tanggal Presensi">
        </div>

        <div class="col-6 col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-teacher-primary flex-grow-1">
                <i class="bi bi-funnel me-1"></i> Filter
            </button>
            @if(request()->anyFilled(['search', 'type', 'approval_status', 'date']))
                <a href="{{ route('teacher.leave-requests.index') }}" class="btn btn-light border text-muted" title="Reset Filter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            @endif
        </div>
    </form>
</div>

<!-- Requests Table Card -->
<div class="card card-custom">
    <div class="card-body p-0">
        @if($requests->isEmpty())
            <div class="text-center py-5">
                <i class="bi bi-inbox text-muted display-4"></i>
                <h6 class="fw-bold mt-2 text-dark">Tidak Ada Data Pengajuan</h6>
                <p class="text-muted small mb-0">Belum ada permohonan izin/sakit yang sesuai dengan kriteria filter.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light border-bottom">
                        <tr class="text-muted small">
                            <th class="ps-3 py-3">Siswa</th>
                            <th>Kelas</th>
                            <th>Tanggal Izin</th>
                            <th>Jenis</th>
                            <th>Alasan</th>
                            <th>Status Persetujuan</th>
                            <th class="text-end pe-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($requests as $req)
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold text-dark">{{ $req->user->name ?? '-' }}</div>
                                    <small class="text-muted">NIS: {{ $req->user->student->nis ?? '-' }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $req->user->student->class ?? '-' }}</span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark small">
                                        {{ \Carbon\Carbon::parse($req->attendance_date)->translatedFormat('d M Y') }}
                                    </div>
                                    <small class="text-muted">{{ $req->attendance_time ?: '-' }}</small>
                                </td>
                                <td>
                                    @if($req->status === 'sick')
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                            <i class="bi bi-bandaid me-1"></i> Sakit
                                        </span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                            <i class="bi bi-calendar-event me-1"></i> Izin
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <span class="small text-truncate d-inline-block" style="max-width: 220px;" title="{{ $req->reason }}">
                                        {{ $req->reason ?: '-' }}
                                    </span>
                                </td>
                                <td>
                                    @if($req->approval_status === 'approved')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            <i class="bi bi-check-circle-fill me-1"></i> Disetujui
                                        </span>
                                        @if($req->approver)
                                            <div class="text-muted" style="font-size: 0.72rem;">Oleh: {{ $req->approver->name }}</div>
                                        @endif
                                    @elseif($req->approval_status === 'rejected')
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                            <i class="bi bi-x-circle-fill me-1"></i> Ditolak
                                        </span>
                                        @if($req->rejection_note)
                                            <div class="text-muted fst-italic" style="font-size: 0.72rem;" title="{{ $req->rejection_note }}">
                                                "{{ Str::limit($req->rejection_note, 25) }}"
                                            </div>
                                        @endif
                                    @else
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                            <i class="bi bi-clock me-1"></i> Menunggu
                                        </span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('teacher.leave-requests.show', $req) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5" title="Detail Pengajuan">
                                            <i class="bi bi-eye"></i>
                                        </a>

                                        @if($req->isPending())
                                            <form action="{{ route('teacher.leave-requests.approve', $req) }}" method="POST" class="d-inline"
                                                  data-confirm="Apakah Anda yakin ingin menyetujui pengajuan ini?"
                                                  data-confirm-title="Setujui Pengajuan"
                                                  data-confirm-ok="Ya, Setujui"
                                                  data-confirm-variant="success">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-sm btn-success rounded-pill px-2.5" title="Setujui">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                            </form>

                                            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-2.5" title="Tolak" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $req->id }}">
                                                <i class="bi bi-x-lg"></i>
                                            </button>

                                            <!-- Modal Tolak -->
                                            <div class="modal fade text-start" id="rejectModal{{ $req->id }}" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content rounded-4 border-0 shadow">
                                                        <form action="{{ route('teacher.leave-requests.reject', $req) }}" method="POST">
                                                            @csrf
                                                            @method('PATCH')
                                                            <div class="modal-header border-0 pb-0">
                                                                <h5 class="modal-title fw-bold text-danger">
                                                                    <i class="bi bi-x-circle me-1"></i> Tolak Pengajuan
                                                                </h5>
                                                                <button type="button" class="btn-close" data-bs-toggle="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body py-3">
                                                                <p class="small text-muted mb-3">
                                                                    Anda akan menolak pengajuan <strong>{{ $req->status === 'sick' ? 'Sakit' : 'Izin' }}</strong> atas nama <strong>{{ $req->user->name ?? '-' }}</strong>.
                                                                </p>
                                                                <label class="form-label small fw-semibold text-secondary">Catatan Alasan Penolakan (Opsional)</label>
                                                                <textarea name="rejection_note" class="form-control" rows="3" placeholder="Contoh: Surat keterangan dokter belum dilampirkan atau format tidak sesuai."></textarea>
                                                            </div>
                                                            <div class="modal-footer border-0 pt-0">
                                                                <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                                                                <button type="submit" class="btn btn-danger rounded-pill px-3">Tolak Pengajuan</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($requests->hasPages())
                <div class="p-3 border-top">
                    {{ $requests->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
