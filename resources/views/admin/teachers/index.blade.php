@extends('layouts.admin')

@section('title', 'Manajemen Guru')

@section('content')
<div class="container-fluid p-0">
    {{-- Page Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="bi bi-person-video3 text-info me-2"></i>Manajemen Guru
            </h4>
            <p class="text-muted small mb-0">Kelola data akun dan profil seluruh guru & staf.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="{{ route('admin.teachers.create') }}" class="btn btn-info text-white rounded-3 px-4 fw-semibold shadow-sm">
                <i class="bi bi-person-plus-fill me-2"></i>Tambah Guru
            </a>
        </div>
    </div>

    {{-- Search & Filter Card --}}
    <div class="card admin-card p-3 mb-4">
        <form method="GET" action="{{ route('admin.teachers.index') }}" class="row g-2 align-items-center">
            <div class="col-12 col-md-6 col-lg-4">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted">
                        <i class="bi bi-search"></i>
                    </span>
                    <input
                        type="text"
                        name="search"
                        class="form-control bg-light border-start-0"
                        placeholder="Cari nama, email, atau NIP..."
                        value="{{ $search }}"
                    >
                </div>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-info text-white rounded-3 px-3">Cari</button>
                @if($search)
                    <a href="{{ route('admin.teachers.index') }}" class="btn btn-outline-secondary rounded-3 px-3 ms-1">Reset</a>
                @endif
            </div>
            <div class="col-auto ms-md-auto">
                <span class="text-muted small">
                    Menampilkan <strong>{{ $teachers->firstItem() ?? 0 }}–{{ $teachers->lastItem() ?? 0 }}</strong>
                    dari <strong>{{ $teachers->total() }}</strong> guru
                </span>
            </div>
        </form>
    </div>

    {{-- Teachers Table --}}
    <div class="card admin-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="small text-muted text-uppercase ps-4" style="width:40px">#</th>
                        <th class="small text-muted text-uppercase">Nama / Email</th>
                        <th class="small text-muted text-uppercase">NIP</th>
                        <th class="small text-muted text-uppercase text-center">Wajah</th>
                        <th class="small text-muted text-uppercase text-center">Status</th>
                        <th class="small text-muted text-uppercase text-center pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($teachers as $teacher)
                        <tr>
                            <td class="ps-4 text-muted small">{{ $teachers->firstItem() + $loop->index }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded-circle bg-info-subtle text-info fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                                         style="width:38px;height:38px;font-size:0.9rem;">
                                        {{ strtoupper(substr($teacher->user->name ?? 'G', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-dark small">{{ $teacher->user->name ?? '-' }}</div>
                                        <div class="text-muted" style="font-size:0.78rem;">{{ $teacher->user->email ?? '-' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <code class="small bg-light px-2 py-1 rounded">{{ $teacher->nip }}</code>
                            </td>
                            <td class="text-center">
                                @if($teacher->face_data)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="bi bi-check-circle-fill me-1"></i>Terdaftar
                                    </span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border">
                                        <i class="bi bi-dash-circle me-1"></i>Belum
                                    </span>
                                @endif
                            </td>
                            <td class="text-center">
                                <form action="{{ route('admin.teachers.toggle-status', $teacher) }}" method="POST" class="d-inline">
                                    @csrf @method('PATCH')
                                    <button type="submit"
                                        class="btn btn-sm rounded-pill px-3 fw-medium {{ $teacher->user->status === 'active' ? 'btn-success-subtle border border-success-subtle text-success' : 'btn-danger-subtle border border-danger-subtle text-danger' }}"
                                        style="font-size:0.78rem;"
                                        title="Klik untuk {{ $teacher->user->status === 'active' ? 'nonaktifkan' : 'aktifkan' }}">
                                        <i class="bi bi-{{ $teacher->user->status === 'active' ? 'check-circle-fill' : 'x-circle-fill' }} me-1"></i>
                                        {{ $teacher->user->status === 'active' ? 'Aktif' : 'Nonaktif' }}
                                    </button>
                                </form>
                            </td>
                            <td class="text-center pe-4">
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="{{ route('admin.teachers.show', $teacher) }}"
                                       class="btn btn-sm btn-outline-secondary rounded-2"
                                       title="Detail">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('admin.teachers.edit', $teacher) }}"
                                       class="btn btn-sm btn-outline-info rounded-2"
                                       title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button"
                                        class="btn btn-sm btn-outline-danger rounded-2 btn-delete-teacher"
                                        title="Hapus"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteModal"
                                        data-teacher-id="{{ $teacher->id }}"
                                        data-delete-url="{{ route('admin.teachers.destroy', $teacher) }}"
                                        data-teacher-name="{{ $teacher->user->name ?? 'Guru ini' }}">
                                        <i class="bi bi-trash3" style="pointer-events: none;"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                <p class="mb-1 fw-semibold">
                                    @if($search)
                                        Tidak ada guru yang cocok dengan pencarian "<strong>{{ $search }}</strong>".
                                    @else
                                        Belum ada data guru.
                                    @endif
                                </p>
                                <small>
                                    @if($search)
                                        <a href="{{ route('admin.teachers.index') }}">Tampilkan semua guru</a>
                                    @else
                                        <a href="{{ route('admin.teachers.create') }}">Tambahkan guru pertama</a>
                                    @endif
                                </small>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($teachers->hasPages())
            <div class="p-3 border-top">
                {{ $teachers->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Delete Confirmation Modal --}}
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-danger-subtle text-danger d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:46px;height:46px;font-size:1.3rem;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="deleteModalLabel">Konfirmasi Hapus Guru</h5>
                        <p class="text-muted small mb-0">Tindakan ini tidak dapat dibatalkan.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3">
                <div class="alert alert-danger border-0 rounded-3 mb-0">
                    <p class="mb-1">Anda akan menghapus akun guru:</p>
                    <p class="fw-bold mb-1" id="deleteTeacherName">—</p>
                    <small>Seluruh data terkait (profil, riwayat absensi) akan ikut terhapus secara permanen.</small>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                <form id="deleteForm" method="POST" class="d-inline">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger rounded-3 px-4 fw-semibold">
                        <i class="bi bi-trash3 me-1"></i>Ya, Hapus
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget ? event.relatedTarget.closest('[data-teacher-id], [data-delete-url]') : null;
            if (!button) return;

            const teacherName = button.getAttribute('data-teacher-name') || 'Guru ini';
            const deleteUrl = button.getAttribute('data-delete-url')
                || ('{{ url("admin/teachers") }}/' + button.getAttribute('data-teacher-id'));

            document.getElementById('deleteTeacherName').textContent = teacherName;
            document.getElementById('deleteForm').action = deleteUrl;
        });
    }
</script>
@endpush
