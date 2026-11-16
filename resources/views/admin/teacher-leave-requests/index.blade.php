@extends('layouts.admin')

@section('title', 'Persetujuan Izin & Sakit Guru — Admin')

@section('content')
<div class="container-fluid p-0">
    <!-- Header Page -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="bi bi-person-check-fill text-primary me-2"></i>Persetujuan Izin & Sakit Guru
            </h4>
            <p class="text-muted small mb-0">Daftar permohonan izin dan sakit tenaga pendidik (Guru) yang dikelola oleh Administrator.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-white text-dark border p-2 px-3 rounded-3 shadow-sm d-flex align-items-center gap-2">
                <i class="bi bi-shield-lock-fill text-primary"></i>
                <span>Wewenang Khusus: Administrator</span>
            </span>
        </div>
    </div>

    <!-- Alert Flash Messages -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 p-3 mb-4 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-check-circle-fill fs-5 text-success"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm rounded-3 p-3 mb-4 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-5 text-warning-emphasis"></i>
            <div>{{ session('warning') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('info'))
        <div class="alert alert-info alert-dismissible fade show border-0 shadow-sm rounded-3 p-3 mb-4 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-info-circle-fill fs-5 text-info"></i>
            <div>{{ session('info') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 p-3 mb-4 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-x-circle-fill fs-5 text-danger"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- 1. Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <a href="{{ route('admin.teacher-leave-requests.index') }}" class="text-decoration-none">
                <div class="card admin-card p-3 text-center {{ !request('approval_status') ? 'border-primary border-2 shadow-sm' : '' }}">
                    <span class="text-muted small fw-semibold">Total Pengajuan Guru</span>
                    <h3 class="fw-bold my-1 text-dark">{{ number_format($stats['total_requests']) }}</h3>
                    <small class="text-muted">Semua riwayat izin guru</small>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ route('admin.teacher-leave-requests.index', ['approval_status' => 'pending']) }}" class="text-decoration-none">
                <div class="card admin-card p-3 text-center {{ request('approval_status') === 'pending' ? 'border-warning border-2 shadow-sm' : '' }}">
                    <span class="text-muted small fw-semibold">Menunggu Persetujuan</span>
                    <h3 class="fw-bold my-1 text-warning-emphasis">{{ number_format($stats['pending_count']) }}</h3>
                    <small class="text-muted">Perlu tindakan Admin</small>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ route('admin.teacher-leave-requests.index', ['approval_status' => 'approved']) }}" class="text-decoration-none">
                <div class="card admin-card p-3 text-center {{ request('approval_status') === 'approved' ? 'border-success border-2 shadow-sm' : '' }}">
                    <span class="text-muted small fw-semibold">Disetujui Admin</span>
                    <h3 class="fw-bold my-1 text-success">{{ number_format($stats['approved_count']) }}</h3>
                    <small class="text-muted">Tervalidasi sah</small>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ route('admin.teacher-leave-requests.index', ['approval_status' => 'rejected']) }}" class="text-decoration-none">
                <div class="card admin-card p-3 text-center {{ request('approval_status') === 'rejected' ? 'border-danger border-2 shadow-sm' : '' }}">
                    <span class="text-muted small fw-semibold">Ditolak Admin</span>
                    <h3 class="fw-bold my-1 text-danger">{{ number_format($stats['rejected_count']) }}</h3>
                    <small class="text-muted">Tidak disetujui</small>
                </div>
            </a>
        </div>
    </div>

    <!-- 2. Filter & Search Bar -->
    <div class="card admin-card p-3 mb-4">
        <form method="GET" action="{{ route('admin.teacher-leave-requests.index') }}" class="row g-2 align-items-center">
            <div class="col-12 col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" name="search" class="form-control bg-light border-start-0" placeholder="Cari nama guru, NIP, atau email..." value="{{ request('search') }}">
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
                    <option value="pending" {{ request('approval_status') === 'pending' ? 'selected' : '' }}>Menunggu Admin</option>
                    <option value="approved" {{ request('approval_status') === 'approved' ? 'selected' : '' }}>Disetujui</option>
                    <option value="rejected" {{ request('approval_status') === 'rejected' ? 'selected' : '' }}>Ditolak</option>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <input type="date" name="date" class="form-control bg-light" value="{{ request('date') }}" title="Filter Tanggal Presensi">
            </div>

            <div class="col-6 col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                @if(request()->anyFilled(['search', 'type', 'approval_status', 'date']))
                    <a href="{{ route('admin.teacher-leave-requests.index') }}" class="btn btn-light border text-muted" title="Reset Filter">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- 3. Table of Teacher Requests -->
    <div class="card admin-card border-0 shadow-sm">
        <div class="card-header bg-white py-3 px-3 d-flex align-items-center justify-content-between border-bottom">
            <h6 class="fw-bold mb-0 text-dark">
                <i class="bi bi-list-task me-1 text-primary"></i> Daftar Permohonan Izin Guru
            </h6>
            <span class="badge bg-light text-muted border">Total: {{ $requests->total() }} Data</span>
        </div>

        <div class="card-body p-0">
            @if($requests->isEmpty())
                <div class="text-center py-5">
                    <i class="bi bi-inbox text-muted display-4"></i>
                    <h6 class="fw-bold mt-2 text-dark">Tidak Ada Pengajuan Izin Guru</h6>
                    <p class="text-muted small mb-0">Belum ada permohonan izin/sakit guru yang sesuai dengan kriteria filter.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light border-bottom">
                            <tr class="text-muted small text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.05em;">
                                <th class="ps-3 py-3">Nama Guru</th>
                                <th>Tanggal Izin</th>
                                <th>Jenis</th>
                                <th>Alasan</th>
                                <th>Status Persetujuan</th>
                                <th>Admin Peninjau</th>
                                <th class="text-end pe-3">Aksi Admin</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($requests as $req)
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-bold text-dark">{{ $req->user->name ?? '-' }}</div>
                                        <small class="text-muted">
                                            NIP: {{ $req->user->teacher->nip ?? '-' }} &bull; {{ $req->user->email }}
                                        </small>
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
                                        @elseif($req->approval_status === 'rejected')
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                                <i class="bi bi-x-circle-fill me-1"></i> Ditolak
                                            </span>
                                            @if($req->rejection_note)
                                                <div class="text-danger small fst-italic" style="font-size: 0.72rem;" title="{{ $req->rejection_note }}">
                                                    "{{ Str::limit($req->rejection_note, 25) }}"
                                                </div>
                                            @endif
                                        @else
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                                <i class="bi bi-clock me-1"></i> Menunggu Persetujuan
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($req->approver)
                                            <div class="fw-semibold text-dark small">{{ $req->approver->name }}</div>
                                            <small class="text-muted" style="font-size: 0.72rem;">
                                                {{ $req->approved_at ? \Carbon\Carbon::parse($req->approved_at)->translatedFormat('d/m/Y H:i') : '' }}
                                            </small>
                                        @else
                                            <span class="text-muted small">-</span>
                                        @endif
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="d-inline-flex gap-1">
                                            @if($req->isPending())
                                                {{-- Tombol Setujui --}}
                                                <form action="{{ route('admin.teacher-leave-requests.approve', $req) }}" method="POST" class="d-inline"
                                                      data-confirm="Apakah Anda yakin ingin menyetujui pengajuan izin Guru {{ $req->user->name }}?"
                                                      data-confirm-title="Setujui Pengajuan"
                                                      data-confirm-ok="Ya, Setujui"
                                                      data-confirm-variant="success">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="btn btn-sm btn-success rounded-pill px-3 fw-semibold">
                                                        <i class="bi bi-check-lg me-1"></i> Setujui
                                                    </button>
                                                </form>

                                                {{-- Tombol Tolak (Modal) --}}
                                                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $req->id }}">
                                                    <i class="bi bi-x-lg me-1"></i> Tolak
                                                </button>

                                                <!-- Modal Tolak -->
                                                <div class="modal fade text-start" id="rejectModal{{ $req->id }}" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered">
                                                        <div class="modal-content rounded-4 border-0 shadow">
                                                            <form action="{{ route('admin.teacher-leave-requests.reject', $req) }}" method="POST">
                                                                @csrf
                                                                @method('PATCH')
                                                                <div class="modal-header border-0 pb-0">
                                                                    <h5 class="modal-title fw-bold text-danger">
                                                                        <i class="bi bi-x-circle me-1"></i> Tolak Pengajuan Izin Guru
                                                                    </h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <div class="p-3 bg-light rounded-3 mb-3 border">
                                                                        <div class="fw-bold text-dark">{{ $req->user->name }}</div>
                                                                        <div class="small text-muted">Tanggal: {{ \Carbon\Carbon::parse($req->attendance_date)->translatedFormat('d F Y') }}</div>
                                                                        <div class="small text-muted">Alasan: "{{ $req->reason }}"</div>
                                                                    </div>

                                                                    <label for="rejection_note{{ $req->id }}" class="form-label small fw-semibold text-secondary">
                                                                        Alasan / Catatan Penolakan (Akan dikirim ke email Guru) <span class="text-danger">*</span>
                                                                    </label>
                                                                    <textarea name="rejection_note" id="rejection_note{{ $req->id }}" rows="3" class="form-control" placeholder="Contoh: Jadwal pengawasan ujian sekolah mendesak, mohon konfirmasi ke kurikulum." required></textarea>
                                                                    <div class="form-text small text-muted">Catatan ini akan otomatis dikirimkan ke email guru sebagai penjelasan.</div>
                                                                </div>
                                                                <div class="modal-footer border-0 pt-0">
                                                                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                                                                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-semibold">
                                                                        <i class="bi bi-x-circle-fill me-1"></i> Konfirmasi Tolak
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            @else
                                                <span class="badge bg-light text-muted border py-2 px-3">
                                                    <i class="bi bi-check2-all me-1"></i> Selesai Diproses
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="p-3 border-top">
                    {{ $requests->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

