@extends('emails.layout')

@section('content')
    <h2 style="margin-top: 0; color: #0f172a; font-size: 18px;">Halo, {{ $student->name }}!</h2>
    <p style="color: #475569; font-size: 14px; margin-bottom: 20px;">
        Pengajuan <strong>{{ $attendance->status === 'sick' ? 'Sakit' : 'Izin' }}</strong> Anda telah selesai ditinjau oleh pihak Guru dengan keputusan sebagai berikut:
    </p>

    <div style="text-align: center; margin: 20px 0;">
        @if($attendance->approval_status === 'approved')
            <span class="badge badge-success" style="font-size: 14px; padding: 7px 16px;">
                ✓ Pengajuan {{ $attendance->status === 'sick' ? 'Sakit' : 'Izin' }} Disetujui
            </span>
        @else
            <span class="badge badge-danger" style="font-size: 14px; padding: 7px 16px;">
                ✕ Pengajuan {{ $attendance->status === 'sick' ? 'Sakit' : 'Izin' }} Ditolak
            </span>
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
            <td class="label">Jenis Pengajuan</td>
            <td class="value">{{ $attendance->status === 'sick' ? 'Sakit' : 'Izin' }}</td>
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
            <td class="label">Alasan Diajukan</td>
            <td class="value" style="font-style: italic;">"{{ $attendance->reason }}"</td>
        </tr>
        <tr>
            <td class="label">Guru Peninjau</td>
            <td class="value">{{ $approver->name ?? 'Dewan Guru' }}</td>
        </tr>
        @if($approvedAtFormatted)
            <tr>
                <td class="label">Waktu Keputusan</td>
                <td class="value">{{ $approvedAtFormatted }}</td>
            </tr>
        @endif
        <tr>
            <td class="label">Status Keputusan</td>
            <td class="value">
                @if($attendance->approval_status === 'approved')
                    <span class="badge badge-success" style="font-size: 12px; padding: 4px 10px;">
                        {{ $attendance->getApprovalStatusLabel() }}
                    </span>
                @else
                    <span class="badge badge-danger" style="font-size: 12px; padding: 4px 10px;">
                        {{ $attendance->getApprovalStatusLabel() }}
                    </span>
                @endif
            </td>
        </tr>
        @if($attendance->approval_status === 'rejected' && $attendance->rejection_note)
            <tr>
                <td class="label" style="color: #b91c1c;">Catatan Penolakan</td>
                <td class="value" style="color: #b91c1c; font-style: italic;">
                    "{{ $attendance->rejection_note }}"
                </td>
            </tr>
        @endif
    </table>

    @if($attendance->approval_status === 'approved')
        <div style="background: #f0fdf4; border-left: 4px solid #22c55e; padding: 12px 16px; border-radius: 6px; font-size: 13px; color: #166534; margin-top: 20px;">
            Catatan ketidakhadiran Anda telah resmi disetujui sebagai {{ $attendance->status === 'sick' ? 'Sakit' : 'Izin' }}. Semoga lekas pulih / urusan Anda berjalan dengan lancar.
        </div>
    @else
        <div style="background: #fef2f2; border-left: 4px solid #ef4444; padding: 12px 16px; border-radius: 6px; font-size: 13px; color: #991b1b; margin-top: 20px;">
            Pengajuan Anda tidak disetujui. Silakan hubungi wali kelas atau guru piket sekolah Anda untuk informasi dan klarifikasi lebih lanjut.
        </div>
    @endif
@endsection
