@extends('emails.layout')

@section('content')
    <h2 style="margin-top: 0; color: #0f172a; font-size: 18px;">Halo, Bapak/Ibu {{ $teacher->name }}!</h2>
    <p style="color: #475569; font-size: 14px; margin-bottom: 20px;">
        Pengajuan <strong>{{ $attendance->status === 'sick' ? 'Sakit' : 'Izin' }}</strong> dari siswa telah selesai ditinjau. Berikut rincian keputusan terbaru:
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
            <td class="value"><strong>{{ $student->name }}</strong></td>
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
            <td class="label">Jenis Pengajuan</td>
            <td class="value">{{ $attendance->status === 'sick' ? 'Sakit' : 'Izin' }}</td>
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
            <td class="label">Guru Peninjau</td>
            <td class="value">{{ $approver->name ?? 'Dewan Guru' }}</td>
        </tr>
        @if($decidedAtFormatted)
            <tr>
                <td class="label">Waktu Keputusan</td>
                <td class="value">{{ $decidedAtFormatted }}</td>
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

    <div style="text-align: center; margin: 25px 0 15px 0;">
        <a href="{{ route('teacher.leave-requests.index') }}" class="btn-action">
            Buka Portal Guru & Lihat Riwayat Pengajuan
        </a>
    </div>

    <div style="background: #eff6ff; border-left: 4px solid #3b82f6; padding: 12px 16px; border-radius: 6px; font-size: 13px; color: #1e40af; margin-top: 20px;">
        <strong>Informasi:</strong> Email ini dikirim ke seluruh Guru sebagai arsip keputusan. Siswa yang bersangkutan juga menerima notifikasi hasil keputusan secara terpisah.
    </div>
@endsection
