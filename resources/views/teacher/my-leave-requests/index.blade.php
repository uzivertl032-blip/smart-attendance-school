@extends('layouts.teacher')

@section('title', 'Pengajuan Izin Guru')
@section('page-title', 'Pengajuan Izin & Sakit Guru')

@section('content')
<div class="row g-4">
    <!-- Form Card: Buat Pengajuan Baru -->
    <div class="col-12 col-lg-5">
        <div class="card card-custom p-4">
            <div class="d-flex align-items-center gap-2 mb-3">
                <div class="rounded-3 p-2 bg-primary-subtle text-primary">
                    <i class="bi bi-file-earmark-plus-fill fs-5"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0 text-dark">Form Pengajuan Izin Guru</h5>
                    <small class="text-muted">Kirim permohonan izin/sakit ke Administrator</small>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show border-0 rounded-3 small mb-3" role="alert">
                    <i class="bi bi-check-circle-fill me-1"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show border-0 rounded-3 small mb-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show border-0 rounded-3 small mb-3" role="alert">
                    <i class="bi bi-exclamation-circle-fill me-1"></i> {{ session('warning') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <form action="{{ route('teacher.my-leave-requests.store') }}" method="POST">
                @csrf

                <!-- Identitas Guru -->
                <div class="p-3 bg-light rounded-3 mb-3 border">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="small text-muted">Pemohon:</span>
                        <span class="badge bg-primary text-white">Guru / Tenaga Pendidik</span>
                    </div>
                    <div class="fw-bold text-dark">{{ $user->name }}</div>
                    <div class="small text-muted">NIP: {{ $teacher->nip ?? '-' }} | Email: {{ $user->email }}</div>
                </div>

                <!-- Jenis Permohonan -->
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary">Jenis Permohonan <span class="text-danger">*</span></label>
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="status" id="type_excused" value="excused" {{ old('status', 'excused') === 'excused' ? 'checked' : '' }}>
                            <label class="btn btn-outline-primary w-100 py-2.5 rounded-3 d-flex flex-column align-items-center" for="type_excused">
                                <i class="bi bi-calendar-event fs-5 mb-1"></i>
                                <span class="fw-semibold small">Izin</span>
                            </label>
                        </div>
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="status" id="type_sick" value="sick" {{ old('status') === 'sick' ? 'checked' : '' }}>
                            <label class="btn btn-outline-danger w-100 py-2.5 rounded-3 d-flex flex-column align-items-center" for="type_sick">
                                <i class="bi bi-bandaid fs-5 mb-1"></i>
                                <span class="fw-semibold small">Sakit</span>
                            </label>
                        </div>
                    </div>
                    @error('status')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Tanggal Izin -->
                <div class="mb-3">
                    <label for="attendance_date" class="form-label small fw-semibold text-secondary">
                        Tanggal Berhalangan Hadir <span class="text-danger">*</span>
                    </label>
                    <input 
                        type="date" 
                        class="form-control bg-light @error('attendance_date') is-invalid @enderror" 
                        id="attendance_date" 
                        name="attendance_date" 
                        value="{{ old('attendance_date', \Carbon\Carbon::today()->toDateString()) }}" 
                        required
                    >
                    @error('attendance_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Keterangan / Alasan -->
                <div class="mb-4">
                    <label for="reason" class="form-label small fw-semibold text-secondary">
                        Keterangan Alasan Lengkap <span class="text-danger">*</span>
                    </label>
                    <textarea 
                        name="reason" 
                        id="reason" 
                        rows="4" 
                        class="form-control bg-light @error('reason') is-invalid @enderror" 
                        placeholder="Contoh: Mengikuti dinas luar / pelatihan kurikulum sekolah di dinas pendidikan, atau sakit demam istirahat dokter." 
                        required
                    >{{ old('reason') }}</textarea>
                    <div class="form-text small text-muted">Minimal 5 karakter penjelasan alasan Anda.</div>
                    @error('reason')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Submit Button -->
                <div class="d-grid">
                    <button type="submit" class="btn btn-teacher-primary py-2.5 shadow-sm">
                        <i class="bi bi-send-fill me-1"></i> Kirim Pengajuan ke Administrator
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Table Card: Riwayat Pengajuan -->
    <div class="col-12 col-lg-7">
        <div class="card card-custom h-100">
            <div class="card-header bg-transparent border-0 p-3 pb-0">
                <h5 class="fw-bold mb-0 text-dark">Riwayat Pengajuan Izin Anda</h5>
                <small class="text-muted">Permohonan izin guru ditinjau langsung oleh Administrator</small>
            </div>

            <div class="card-body p-3">
                @if($requests->isEmpty())
                    <div class="text-center py-5">
                        <i class="bi bi-inbox text-muted display-4"></i>
                        <h6 class="fw-bold text-dark mt-2">Belum Ada Pengajuan</h6>
                        <p class="text-muted small mb-0">Isi formulir di sebelah kiri jika Anda berhalangan hadir mengajar.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr class="text-muted small">
                                    <th>Tanggal</th>
                                    <th>Jenis</th>
                                    <th>Alasan</th>
                                    <th>Status Admin</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($requests as $req)
                                    <tr>
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
                                                    <div class="text-danger small fst-italic" style="font-size: 0.72rem;" title="{{ $req->rejection_note }}">
                                                        "{{ Str::limit($req->rejection_note, 25) }}"
                                                    </div>
                                                @endif
                                            @else
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                                    <i class="bi bi-clock me-1"></i> Menunggu Admin
                                                </span>
                                            @endif
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
</div>
@endsection

