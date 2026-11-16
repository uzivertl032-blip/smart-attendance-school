@extends('layouts.admin')

@section('title', 'Manajemen Akun Administrator')

@section('content')
<div class="container-fluid p-0">
    {{-- Page Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="bi bi-shield-lock-fill text-primary me-2"></i>Manajemen Akun Administrator
            </h4>
            <p class="text-muted small mb-0">Kelola dan pantau seluruh akun administrator sistem SmartAttendance.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="{{ route('admin.admins.create') }}" class="btn btn-primary text-white rounded-3 px-4 fw-semibold shadow-sm">
                <i class="bi bi-person-plus-fill me-2"></i>+ Tambah Admin
            </a>
        </div>
    </div>

    {{-- Search Card --}}
    <div class="card admin-card p-3 mb-4">
        <form method="GET" action="{{ route('admin.admins.index') }}" class="row g-2 align-items-center">
            <div class="col-12 col-md-6 col-lg-4">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted">
                        <i class="bi bi-search"></i>
                    </span>
                    <input
                        type="text"
                        name="search"
                        class="form-control bg-light border-start-0"
                        placeholder="Cari nama atau email admin..."
                        value="{{ $search }}"
                    >
                </div>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary rounded-3 px-3">Cari</button>
                @if($search)
                    <a href="{{ route('admin.admins.index') }}" class="btn btn-outline-secondary rounded-3 px-3 ms-1">Reset</a>
                @endif
            </div>
            <div class="col-auto ms-md-auto">
                <span class="text-muted small">
                    Menampilkan <strong>{{ $admins->firstItem() ?? 0 }}–{{ $admins->lastItem() ?? 0 }}</strong>
                    dari <strong>{{ $admins->total() }}</strong> administrator
                </span>
            </div>
        </form>
    </div>

    {{-- Admins Table --}}
    <div class="card admin-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="small text-muted text-uppercase ps-4" style="width:40px">#</th>
                        <th class="small text-muted text-uppercase">Nama / Email</th>
                        <th class="small text-muted text-uppercase">Peran</th>
                        <th class="small text-muted text-uppercase text-center">Status Akun</th>
                        <th class="small text-muted text-uppercase pe-4">Terdaftar Pada</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($admins as $admin)
                        <tr>
                            <td class="ps-4 text-muted small">{{ $admins->firstItem() + $loop->index }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded-circle bg-primary-subtle text-primary fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                                         style="width:38px;height:38px;font-size:0.9rem;">
                                        {{ strtoupper(substr($admin->name ?? 'A', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-dark small d-flex align-items-center gap-2">
                                            <span>{{ $admin->name ?? '-' }}</span>
                                            @if($admin->id === Auth::id())
                                                <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill" style="font-size:0.7rem;">
                                                    <i class="bi bi-person-fill"></i> Anda
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-muted" style="font-size:0.78rem;">{{ $admin->email ?? '-' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                    <i class="bi bi-shield-check me-1"></i>Administrator
                                </span>
                            </td>
                            <td class="text-center">
                                @if($admin->id === Auth::id())
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill"
                                          style="font-size:0.78rem;"
                                          title="Akun Anda yang sedang aktif">
                                        <i class="bi bi-check-circle-fill me-1"></i>Aktif
                                    </span>
                                @else
                                    <form action="{{ route('admin.admins.toggle-status', $admin) }}" method="POST" class="d-inline"
                                          data-confirm="Apakah Anda yakin ingin {{ $admin->status === 'active' ? 'menonaktifkan' : 'mengaktifkan' }} akun admin &quot;{{ $admin->name }}&quot;?"
                                          data-confirm-title="Konfirmasi Status Admin"
                                          data-confirm-ok="Ya, Ubah Status"
                                          data-confirm-variant="{{ $admin->status === 'active' ? 'warning' : 'confirm' }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                            class="btn btn-sm rounded-pill px-3 fw-medium {{ $admin->status === 'active' ? 'btn-success-subtle border border-success-subtle text-success' : 'btn-danger-subtle border border-danger-subtle text-danger' }}"
                                            style="font-size:0.78rem;"
                                            title="Klik untuk {{ $admin->status === 'active' ? 'menonaktifkan' : 'mengaktifkan' }} akun">
                                            <i class="bi bi-{{ $admin->status === 'active' ? 'check-circle-fill' : 'x-circle-fill' }} me-1"></i>
                                            {{ $admin->status === 'active' ? 'Aktif' : 'Nonaktif' }}
                                        </button>
                                    </form>
                                @endif
                            </td>
                            <td class="pe-4 text-muted small">
                                <i class="bi bi-calendar3 me-1"></i>{{ $admin->created_at ? $admin->created_at->format('d/m/Y H:i') : '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="bi bi-shield-x fs-1 d-block mb-2 text-secondary"></i>
                                <p class="mb-1 fw-semibold">
                                    @if($search)
                                        Tidak ada administrator yang cocok dengan pencarian "<strong>{{ $search }}</strong>".
                                    @else
                                        Belum ada data administrator lain.
                                    @endif
                                </p>
                                @if($search)
                                    <a href="{{ route('admin.admins.index') }}" class="btn btn-sm btn-outline-primary mt-2">
                                        Lihat Semua Administrator
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($admins->hasPages())
            <div class="card-footer bg-transparent border-top p-3">
                <div class="d-flex justify-content-center">
                    {{ $admins->links() }}
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
