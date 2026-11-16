@extends('layouts.app')

@section('title', 'Login Portal Guru')

@section('content')
<div class="row justify-content-center py-4 sa-auth-page">
    <div class="col-12 col-md-8 col-lg-5">
        <div class="card card-custom border-0 shadow-sm p-4 p-md-5 sa-auth-card">
            <!-- Header Icon & Title -->
            <div class="text-center mb-4">
                <div class="brand-icon mx-auto mb-3 shadow" style="width: 56px; height: 56px; font-size: 1.7rem; border-radius: 16px; background: linear-gradient(135deg, #0284c7 0%, #06b6d4 100%); color: #ffffff; display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-person-video3"></i>
                </div>
                <h4 class="fw-bold text-dark mb-1">Portal Guru</h4>
                <p class="text-muted small">Masuk untuk meninjau dan menyetujui pengajuan izin &amp; sakit siswa.</p>
            </div>

            <!-- Portal Switcher Nav Tabs -->
            <div class="d-flex p-1 bg-light rounded-3 mb-4 border text-center">
                <a href="{{ route('student.login') }}" class="flex-fill py-1.5 px-2 rounded-2 text-decoration-none fw-semibold small text-muted">
                    <i class="bi bi-mortarboard-fill me-1"></i> Siswa
                </a>
                <a href="{{ route('teacher.login') }}" class="flex-fill py-1.5 px-2 rounded-2 text-decoration-none fw-bold small bg-white text-info-emphasis shadow-sm">
                    <i class="bi bi-person-video3 me-1 text-info"></i> Guru
                </a>
                <a href="{{ route('login') }}" class="flex-fill py-1.5 px-2 rounded-2 text-decoration-none fw-semibold small text-muted">
                    <i class="bi bi-shield-lock-fill me-1"></i> Admin
                </a>
            </div>

            <!-- Demo Credentials Box -->
            @env('local')
            <div class="alert alert-info border-0 bg-info-subtle p-3 rounded-3 mb-4">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="fw-semibold text-info-emphasis small">
                        <i class="bi bi-info-circle-fill me-1"></i> Akun Demo Guru
                    </span>
                    <button type="button" class="btn btn-outline-info btn-sm py-0 px-2 rounded-pill" id="btnFillDemo" style="font-size: 0.75rem;">
                        <i class="bi bi-magic me-1"></i> Isi Otomatis
                    </button>
                </div>
                <div class="small text-secondary" style="font-size: 0.82rem;">
                    <div><strong>Email:</strong> <span class="badge bg-light text-dark border">budi.santoso@smartattendance.sch.id</span></div>
                    <div class="mt-1"><strong>Password:</strong> <span class="badge bg-light text-dark border">teacher123</span></div>
                </div>
            </div>
            @endenv

            <form action="{{ route('teacher.login.post') }}" method="POST" autocomplete="off">
                @csrf

                <!-- Email Input -->
                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold small text-secondary">Alamat Email Guru</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted">
                            <i class="bi bi-envelope"></i>
                        </span>
                        <input 
                            type="email" 
                            class="form-control bg-light border-start-0 @error('email') is-invalid @enderror" 
                            id="email" 
                            name="email" 
                            value="{{ old('email') }}" 
                            placeholder="guru@smartattendance.sch.id" 
                            required 
                            autofocus
                        >
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <!-- Password Input -->
                <div class="mb-3">
                    <label for="password" class="form-label fw-semibold small text-secondary">Kata Sandi</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted">
                            <i class="bi bi-lock"></i>
                        </span>
                        <input 
                            type="password" 
                            class="form-control bg-light border-start-0 @error('password') is-invalid @enderror" 
                            id="password" 
                            name="password" 
                            placeholder="••••••••" 
                            required
                        >
                        <button class="btn btn-light border border-start-0 text-muted" type="button" id="togglePassword">
                            <i class="bi bi-eye" id="togglePasswordIcon"></i>
                        </button>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <!-- Remember Me & Info -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                        <label class="form-check-label text-muted small" for="remember">
                            Ingat saya
                        </label>
                    </div>
                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">Role: Guru</span>
                </div>

                <!-- Submit Button -->
                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary-custom py-2 shadow-sm" style="background: linear-gradient(135deg, #0284c7, #0369a1);">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Masuk ke Portal Guru
                    </button>
                </div>

                <div class="text-center d-flex justify-content-between pt-2 border-top">
                    <a href="{{ route('attendance.index') }}" class="text-decoration-none small text-muted">
                        <i class="bi bi-camera-video me-1"></i> Scanner Presensi
                    </a>
                    <a href="{{ route('home') }}" class="text-decoration-none small text-muted">
                        Beranda <i class="bi bi-arrow-right me-1"></i>
                    </a>
                </div>
            </form>
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

    @env('local')
    document.getElementById('btnFillDemo')?.addEventListener('click', function () {
        const emailInput = document.getElementById('email');
        if (emailInput && passwordInput) {
            emailInput.value = 'budi.santoso@smartattendance.sch.id';
            passwordInput.value = 'teacher123';
            emailInput.focus();
        }
    });
    @endenv
</script>
@endpush
