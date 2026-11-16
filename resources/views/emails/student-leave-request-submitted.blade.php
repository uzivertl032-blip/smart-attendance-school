@extends('emails.layout')

@section('content')
    <h2 style="margin-top: 0; color: #0f172a; font-size: 18px;">Halo, {{ $student->name }}!</h2>
    <p style="color: #475569; font-size: 14px; margin-bottom: 20px;">
        Permohonan <strong>{{ $attendance->status === 'sick' ? 'Sakit' : 'Izin' }}</strong> Anda telah berhasil dikirimkan ke sistem SmartAttendance dan sedang menunggu peninjauan dari Guru. Berikut adalah rincian data permohonan Anda:
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

    <div style="background: #fffbeb; border-left: 4px solid #f59e0b; padding: 12px 16px; border-radius: 6px; font-size: 13px; color: #92400e; margin-top: 20px;">
        <strong>Informasi Status:</strong> Permohonan Anda saat ini berstatus <em>Menunggu Persetujuan</em> dari Guru. Sistem akan secara otomatis mengirimkan notifikasi email ke alamat ini begitu Guru memberikan keputusan resmi.
    </div>
@endsection
