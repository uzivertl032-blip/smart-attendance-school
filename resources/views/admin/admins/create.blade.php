@extends('layouts.admin')

@section('title', 'Tambah Administrator')

@section('content')
<div class="container-fluid p-0">
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-muted small">Dashboard</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.admins.index') }}" class="text-decoration-none text-muted small">Manajemen Akun Administrator</a>
            </li>
            <li class="breadcrumb-item active small" aria-current="page">Tambah Administrator</li>
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
                            <h5 class="fw-bold text-dark mb-0">Tambah Akun Administrator</h5>
                            <p class="text-muted small mb-0">Daftarkan akun administrator baru untuk mengelola sistem SmartAttendance.</p>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4">
                    <form action="{{ route('admin.admins.store') }}" method="POST" autocomplete="off">
                        @csrf

                        {{-- Section: Profil Administrator --}}
                        <p class="fw-semibold text-primary small text-uppercase mb-3 mt-1">
                            <i class="bi bi-person-badge me-1"></i>Informasi Profil & Kredensial
                        </p>

                        <div class="row g-3 mb-4">
                            <div class="col-12 col-md-6">
                                <label for="name" class="form-label fw-semibold small text-secondary">
                                    Nama Lengkap <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="name" name="name"
                                    class="form-control @error('name') is-invalid @enderror"
                                    value="{{ old('name') }}"
                                    placeholder="Contoh: Muhammad Akbar, S.Kom"
                                    required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="email" class="form-label fw-semibold small text-secondary">
                                    Alamat Email <span class="text-danger">*</span>
                                </label>
                                <input type="email" id="email" name="email"
                                    class="form-control @error('email') is-invalid @enderror"
                                    value="{{ old('email') }}"
                                    placeholder="Contoh: akbar@smartattendance.sch.id"
                                    required>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="password" class="form-label fw-semibold small text-secondary">
                                    Kata Sandi <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="password" id="password" name="password"
                                        class="form-control @error('password') is-invalid @enderror"
                                        placeholder="Min. 8 karakter"
                                        required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword" title="Lihat/Sembunyikan Sandi">
                                        <i class="bi bi-eye" id="togglePasswordIcon"></i>
                                    </button>
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="form-text small text-muted">Gunakan minimal 8 karakter dengan kombinasi huruf dan angka.</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="password_confirmation" class="form-label fw-semibold small text-secondary">
                                    Konfirmasi Kata Sandi <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="password" id="password_confirmation" name="password_confirmation"
                                        class="form-control"
                                        placeholder="Ulangi kata sandi"
                                        required>
                                    <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword" title="Lihat/Sembunyikan Sandi">
                                        <i class="bi bi-eye" id="toggleConfirmPasswordIcon"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="status" class="form-label fw-semibold small text-secondary">
                                    Status Akun <span class="text-danger">*</span>
                                </label>
                                <select id="status" name="status"
                                    class="form-select @error('status') is-invalid @enderror"
                                    required>
                                    <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>Aktif (Dapat langsung login)</option>
                                    <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Nonaktif (Akses ditangguhkan)</option>
                                </select>
                                @error('status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                            <a href="{{ route('admin.admins.index') }}" class="btn btn-outline-secondary rounded-3 px-4">
                                <i class="bi bi-x-lg me-1"></i>Batal
                            </a>
                            <button type="submit" class="btn btn-primary text-white rounded-3 px-5 fw-semibold shadow-sm">
                                <i class="bi bi-check-lg me-1"></i>Simpan Administrator
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
    document.addEventListener('DOMContentLoaded', function () {
        const setupToggle = (btnId, inputId, iconId) => {
            const btn = document.getElementById(btnId);
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);

            if (btn && input && icon) {
                btn.addEventListener('click', function () {
                    const isPassword = input.type === 'password';
                    input.type = isPassword ? 'text' : 'password';
                    icon.classList.toggle('bi-eye', !isPassword);
                    icon.classList.toggle('bi-eye-slash', isPassword);
                });
            }
        };

        setupToggle('togglePassword', 'password', 'togglePasswordIcon');
        setupToggle('toggleConfirmPassword', 'password_confirmation', 'toggleConfirmPasswordIcon');
    });
</script>
@endpush
