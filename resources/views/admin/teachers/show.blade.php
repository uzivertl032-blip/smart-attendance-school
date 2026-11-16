@extends('layouts.admin')

@section('title', 'Detail Guru — ' . $teacher->user->name)

@section('content')
<div class="container-fluid p-0">
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-muted small">Dashboard</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.teachers.index') }}" class="text-decoration-none text-muted small">Manajemen Guru</a>
            </li>
            <li class="breadcrumb-item active small" aria-current="page">Detail</li>
        </ol>
    </nav>

    <div class="row g-4">
        {{-- Profile Card --}}
        <div class="col-12 col-lg-4">
            <div class="card admin-card text-center p-4 h-100">
                <div class="rounded-circle bg-info-subtle text-info fw-bold d-flex align-items-center justify-content-center mx-auto mb-3"
                     style="width:80px;height:80px;font-size:2rem;">
                    {{ strtoupper(substr($teacher->user->name ?? 'G', 0, 1)) }}
                </div>
                <h5 class="fw-bold text-dark mb-1">{{ $teacher->user->name }}</h5>
                <p class="text-muted small mb-3">{{ $teacher->user->email }}</p>

                <div class="d-flex justify-content-center gap-2 mb-4">
                    @if($teacher->user->status === 'active')
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                            <i class="bi bi-check-circle-fill me-1"></i>Akun Aktif
                        </span>
                    @else
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2">
                            <i class="bi bi-x-circle-fill me-1"></i>Akun Nonaktif
                        </span>
                    @endif
                    <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-2">
                        <i class="bi bi-person-video3 me-1"></i>Guru
                    </span>
                </div>

                <div class="d-grid gap-2">
                    <a href="{{ route('admin.teachers.edit', $teacher) }}" class="btn btn-info text-white rounded-3 fw-semibold">
                        <i class="bi bi-pencil-fill me-1"></i>Edit Data
                    </a>
                    <form action="{{ route('admin.teachers.toggle-status', $teacher) }}" method="POST">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn w-100 rounded-3 fw-semibold {{ $teacher->user->status === 'active' ? 'btn-outline-warning' : 'btn-outline-success' }}">
                            <i class="bi bi-{{ $teacher->user->status === 'active' ? 'pause-circle' : 'play-circle' }} me-1"></i>
                            {{ $teacher->user->status === 'active' ? 'Nonaktifkan Akun' : 'Aktifkan Akun' }}
                        </button>
                    </form>
                    <form action="{{ route('admin.teachers.destroy', $teacher) }}" method="POST"
                          data-confirm="Anda akan menghapus akun guru &quot;{{ $teacher->user->name ?? 'ini' }}&quot;. Seluruh data terkait (profil, riwayat absensi) akan ikut terhapus secara permanen dari sistem."
                          data-confirm-title="Konfirmasi Hapus Guru"
                          data-confirm-ok="Ya, Hapus Guru"
                          data-confirm-variant="delete">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger rounded-3 fw-semibold w-100">
                            <i class="bi bi-trash3 me-1"></i>Hapus Akun Guru
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Detail Card --}}
        <div class="col-12 col-lg-8">
            <div class="card admin-card p-4 mb-4">
                <h6 class="fw-bold text-dark mb-4">
                    <i class="bi bi-person-lines-fill text-info me-2"></i>Informasi Lengkap
                </h6>
                <div class="row g-3">
                    <div class="col-12 col-sm-6">
                        <div class="p-3 bg-light rounded-3">
                            <div class="text-muted small mb-1">Nama Lengkap</div>
                            <div class="fw-semibold text-dark">{{ $teacher->user->name }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6">
                        <div class="p-3 bg-light rounded-3">
                            <div class="text-muted small mb-1">Alamat Email</div>
                            <div class="fw-semibold text-dark">{{ $teacher->user->email }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6">
                        <div class="p-3 bg-light rounded-3">
                            <div class="text-muted small mb-1">Nomor Induk Pegawai (NIP)</div>
                            <div class="fw-semibold text-dark">
                                <code>{{ $teacher->nip }}</code>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6">
                        <div class="p-3 bg-light rounded-3">
                            <div class="text-muted small mb-1">Status Akun</div>
                            <div class="fw-semibold">
                                @if($teacher->user->status === 'active')
                                    <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Aktif</span>
                                @else
                                    <span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>Nonaktif</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6">
                        <div class="p-3 bg-light rounded-3">
                            <div class="text-muted small mb-1">Terdaftar Sejak</div>
                            <div class="fw-semibold text-dark">{{ $teacher->created_at->translatedFormat('d F Y') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Face Data Status Card --}}
            <div class="card admin-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="bi bi-person-bounding-box text-info me-2"></i>Status Data Wajah
                    </h6>
                    @if($teacher->hasFaceRegistered())
                        <a href="{{ route('admin.teachers.face-register', $teacher) }}" class="btn btn-sm btn-outline-info rounded-3 fw-semibold">
                            <i class="bi bi-camera-fill me-1"></i>Update Wajah
                        </a>
                    @else
                        <a href="{{ route('admin.teachers.face-register', $teacher) }}" class="btn btn-sm btn-info text-white rounded-3 fw-semibold shadow-sm">
                            <i class="bi bi-camera-fill me-1"></i>Daftarkan Wajah
                        </a>
                    @endif
                </div>

                @if($teacher->hasFaceRegistered())
                    <div class="p-3 bg-success-subtle rounded-3 mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center flex-shrink-0"
                                 style="width:44px;height:44px;font-size:1.2rem;">
                                <i class="bi bi-shield-fill-check"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold text-success">Data wajah sudah terdaftar</div>
                                <small class="text-muted">Guru ini telah terdaftar dan siap untuk presensi face recognition.</small>
                            </div>
                        </div>
                    </div>
                    <form action="{{ route('admin.teachers.face-register.destroy', $teacher) }}" method="POST"
                          data-confirm="Apakah Anda yakin ingin menghapus data biometrik wajah guru ini?"
                          data-confirm-title="Hapus Data Biometrik"
                          data-confirm-ok="Ya, Hapus"
                          data-confirm-variant="danger">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-link text-danger text-decoration-none p-0 small">
                            <i class="bi bi-trash3 me-1"></i>Hapus Data Biometrik Wajah
                        </button>
                    </form>
                @else
                    <div class="d-flex align-items-center gap-3 p-3 bg-warning-subtle rounded-3">
                        <div class="rounded-circle bg-warning text-white d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width:44px;height:44px;font-size:1.2rem;">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                        </div>
                        <div>
                            <div class="fw-semibold text-warning-emphasis">Belum ada data wajah</div>
                            <small class="text-muted">Guru ini belum bisa absensi via face recognition. Klik tombol "Daftarkan Wajah" di atas.</small>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Back Link --}}
    <div class="mt-4">
        <a href="{{ route('admin.teachers.index') }}" class="text-decoration-none text-muted small">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke Daftar Guru
        </a>
    </div>
</div>

{{-- Delete Confirmation Modal --}}
<div class="modal fade" id="deleteTeacherModal" tabindex="-1" aria-labelledby="deleteTeacherModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-danger-subtle text-danger d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:46px;height:46px;font-size:1.3rem;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="deleteTeacherModalLabel">Konfirmasi Hapus Guru</h5>
                        <p class="text-muted small mb-0">Tindakan ini tidak dapat dibatalkan.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3">
                <div class="alert alert-danger border-0 rounded-3 mb-0">
                    <p class="mb-1">Anda akan menghapus akun guru:</p>
                    <p class="fw-bold mb-1">{{ $teacher->user->name ?? 'Guru ini' }}</p>
                    <small>Akun dan data terkait guru ini akan dihapus dari sistem.</small>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                <form action="{{ route('admin.teachers.destroy', $teacher) }}" method="POST" class="d-inline">
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
