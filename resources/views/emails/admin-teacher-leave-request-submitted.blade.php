@extends('emails.layout')

@section('content')
    <h2 style="margin-top: 0; color: #0f172a; font-size: 18px;">Halo, Administrator {{ $admin->name }}!</h2>
    <p style="color: #475569; font-size: 14px; margin-bottom: 20px;">
        Terdapat permohonan <strong>{{ $attendance->status === 'sick' ? 'Sakit' : 'Izin' }}</strong> baru dari tenaga pendidik (Guru) yang memerlukan peninjauan dan persetujuan Administrator. Berikut adalah rincian data permohonan:
    </p>

    <div style="text-align: center; margin: 20px 0;">
        @if($attendance->status === 'sick')
            <span class="badge badge-warning">⚠ Permohonan Sakit Guru (Menunggu Persetujuan Admin)</span>
        @else
            <span class="badge badge-info">ℹ Permohonan Izin Guru (Menunggu Persetujuan Admin)</span>
        @endif
    </div>

    <table class="data-table">
        <tr>
            <td class="label">Nama Guru</td>
            <td class="value"><strong>{{ $teacher->name }}</strong></td>
        </tr>
        <tr>
            <td class="label">NIP</td>
            <td class="value">{{ $teacherDetails->nip ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Email Guru</td>
            <td class="value">{{ $teacher->email ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Waktu Pengajuan</td>
            <td class="value">{{ $submittedAtFormatted }}</td>
        </tr>
        <tr>
            <td class="label">Tanggal Izin / Sakit</td>
            <td class="value">{{ $dateFormatted }}</td>
        </tr>
        <tr>
            <td class="label">Alasan / Keterangan</td>
            <td class="value" style="font-style: italic;">"{{ $attendance->reason }}"</td>
        </tr>
        <tr>
            <td class="label">Status Saat Ini</td>
            <td class="value">
                <span class="badge badge-warning" style="font-size: 12px; padding: 4px 10px;">
                    {{ $attendance->getApprovalStatusLabel() }}
                </span>
            </td>
        </tr>
    </table>

    <div style="text-align: center; margin: 25px 0 15px 0;">
        <a href="{{ route('admin.teacher-leave-requests.index') }}" class="btn-action">
            Buka Panel Admin & Tinjau Permohonan Guru
        </a>
    </div>

    <div style="background: #eff6ff; border-left: 4px solid #3b82f6; padding: 12px 16px; border-radius: 6px; font-size: 13px; color: #1e40af; margin-top: 20px;">
        <strong>Informasi Admin:</strong> Setiap akun dengan hak akses Administrator dapat menyetujui atau menolak pengajuan ini. Ketika salah satu Admin memberikan keputusan, status akan langsung diperbarui ke seluruh Administrator dan notifikasi hasil keputusan akan otomatis dikirimkan ke email guru yang bersangkutan.
    </div>
@endsection

