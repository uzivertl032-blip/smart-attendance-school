@extends('layouts.admin')

@section('title', 'Tambah Siswa')

@section('content')
<div class="container-fluid p-0">
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-muted small">Dashboard</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.students.index') }}" class="text-decoration-none text-muted small">Manajemen Siswa</a>
            </li>
            <li class="breadcrumb-item active small" aria-current="page">Tambah Siswa</li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-12 col-xl-8">
            <div class="card admin-card">
                <div class="card-header bg-transparent border-bottom p-4 pb-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-3 bg-primary-subtle text-primary d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width:44px;height:44px;font-size:1.2rem;">
                            <i class="bi bi-person-plus-fill"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold text-dark mb-0">Tambah Siswa Baru</h5>
                            <p class="text-muted small mb-0">Isi form di bawah untuk mendaftarkan siswa baru ke sistem.</p>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4">
                    <form action="{{ route('admin.students.store') }}" method="POST" autocomplete="off">
                        @csrf

                        {{-- Section: Akun --}}
                        <p class="fw-semibold text-primary small text-uppercase mb-3 mt-1">
                            <i class="bi bi-person-circle me-1"></i>Informasi Akun
                        </p>

                        <div class="row g-3 mb-4">
                            <div class="col-12 col-md-6">
                                <label for="name" class="form-label fw-semibold small text-secondary">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" id="name" name="name"
                                    class="form-control @error('name') is-invalid @enderror"
                                    value="{{ old('name') }}"
                                    placeholder="Contoh: Ahmad Rizky Pratama">
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="email" class="form-label fw-semibold small text-secondary">Alamat Email <span class="text-danger">*</span></label>
                                <input type="email" id="email" name="email"
                                    class="form-control @error('email') is-invalid @enderror"
                                    value="{{ old('email') }}"
                                    placeholder="Contoh: ahmad@sekolah.sch.id">
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="password" class="form-label fw-semibold small text-secondary">Kata Sandi <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" id="password" name="password"
                                        class="form-control @error('password') is-invalid @enderror"
                                        placeholder="Min. 8 karakter">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="bi bi-eye" id="togglePasswordIcon"></i>
                                    </button>
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="password_confirmation" class="form-label fw-semibold small text-secondary">Konfirmasi Kata Sandi <span class="text-danger">*</span></label>
                                <input type="password" id="password_confirmation" name="password_confirmation"
                                    class="form-control"
                                    placeholder="Ulangi kata sandi">
                            </div>
                        </div>

                        <hr class="my-4">

                        {{-- Section: Data Siswa --}}
                        <p class="fw-semibold text-primary small text-uppercase mb-3">
                            <i class="bi bi-mortarboard-fill me-1"></i>Data Siswa
                        </p>

                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label for="nis" class="form-label fw-semibold small text-secondary">NIS <span class="text-danger">*</span></label>
                                <input type="text" id="nis" name="nis"
                                    class="form-control @error('nis') is-invalid @enderror"
                                    value="{{ old('nis') }}"
                                    placeholder="Contoh: 202610101">
                                @error('nis')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12 col-md-4">
                                <label for="class" class="form-label fw-semibold small text-secondary">Kelas <span class="text-danger">*</span></label>
                                <input type="text" id="class" name="class"
                                    class="form-control @error('class') is-invalid @enderror"
                                    value="{{ old('class') }}"
                                    placeholder="Contoh: XII RPL 1">
                                @error('class')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12 col-md-4">
                                <label for="status" class="form-label fw-semibold small text-secondary">Status Akun <span class="text-danger">*</span></label>
                                <select id="status" name="status"
                                    class="form-select @error('status') is-invalid @enderror">
                                    <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>Aktif</option>
                                    <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                                </select>
                                @error('status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="d-flex justify-content-end gap-2 mt-5 pt-3 border-top">
                            <a href="{{ route('admin.students.index') }}" class="btn btn-outline-secondary rounded-3 px-4">
                                <i class="bi bi-x-lg me-1"></i>Batal
                            </a>
                            <button type="submit" class="btn btn-primary rounded-3 px-5 fw-semibold shadow-sm">
                                <i class="bi bi-check-lg me-1"></i>Simpan Siswa
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('togglePasswordIcon');
    togglePassword?.addEventListener('click', function () {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        toggleIcon.classList.toggle('bi-eye');
        toggleIcon.classList.toggle('bi-eye-slash');
    });
</script>
@endpush
