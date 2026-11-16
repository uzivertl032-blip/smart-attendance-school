@extends('emails.layout')

@section('content')
    <h2 style="margin-top: 0; color: #0f172a; font-size: 18px;">Halo, Bapak/Ibu {{ $teacher->name }}!</h2>
    <p style="color: #475569; font-size: 14px; margin-bottom: 20px;">
        Terdapat permohonan <strong>{{ $attendance->status === 'sick' ? 'Sakit' : 'Izin' }}</strong> baru dari siswa yang memerlukan peninjauan dan persetujuan Anda. Berikut adalah rincian data permohonan:
    </p>

    <div style="text-align: center; margin: 20px 0;">
        @if($attendance->status === 'sick')
            <span class="badge badge-warning">⚠ Permohonan Sakit (Menunggu Persetujuan)</span>
        @else
            <span class="badge badge-info">ℹ Permohonan Izin (Menunggu Persetujuan)</span>
        @endif
    </div>

    <table class="data-table">
        <tr>
            <td class="label">Nama Siswa</td>
            <td class="value">{{ $student->name }}</td>
        </tr>
        <tr>
            <td class="label">NIS</td>
            <td class="value">{{ $studentDetails->nis ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Kelas</td>
            <td class="value">{{ $studentDetails->class ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Email Siswa</td>
            <td class="value">{{ $student->email ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Tanggal Pengajuan</td>
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
            <td class="label">Status Pengajuan</td>
            <td class="value">
                <span class="badge badge-warning" style="font-size: 12px; padding: 4px 10px;">
                    {{ $attendance->getApprovalStatusLabel() }}
                </span>
            </td>
        </tr>
    </table>

    <div style="text-align: center; margin: 25px 0 15px 0;">
        <a href="{{ route('teacher.leave-requests.index') }}" class="btn-action">
            Buka Portal Guru & Tinjau Permohonan
        </a>
    </div>

    <div style="background: #fffbeb; border-left: 4px solid #f59e0b; padding: 12px 16px; border-radius: 6px; font-size: 13px; color: #92400e; margin-top: 20px;">
        <strong>Informasi Tindakan Guru:</strong> Mohon segera meninjau permohonan ini melalui portal Guru. Keputusan persetujuan atau penolakan yang Anda tentukan akan dikirimkan secara otomatis sebagai notifikasi email ke siswa terkait.
    </div>
@endsection
