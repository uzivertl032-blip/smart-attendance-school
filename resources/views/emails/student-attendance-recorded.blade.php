@extends('emails.layout')

@section('content')
    <h2 style="margin-top: 0; color: #0f172a; font-size: 18px;">Halo, {{ $student->name }}!</h2>
    <p style="color: #475569; font-size: 14px; margin-bottom: 20px;">
        Presensi kehadiran Anda telah berhasil dicatat oleh sistem SmartAttendance. Berikut adalah rincian data kehadiran Anda:
    </p>

    <div style="text-align: center; margin: 20px 0;">
        @if($attendance->status === 'present')
            <span class="badge badge-success">✓ Hadir Tepat Waktu</span>
        @elseif($attendance->status === 'late')
            <span class="badge badge-warning">⚠ Hadir Terlambat</span>
        @else
            <span class="badge badge-info">{{ ucfirst($attendance->status) }}</span>
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
            <td class="label">Hari / Tanggal</td>
            <td class="value">{{ $dateFormatted }}</td>
        </tr>
        <tr>
            <td class="label">Waktu Presensi</td>
            <td class="value">{{ $attendance->attendance_time }} WIB</td>
        </tr>
        <tr>
            <td class="label">Metode Verifikasi</td>
            <td class="value">Biometrik Wajah (Terverifikasi)</td>
        </tr>
    </table>

    @if($attendance->status === 'late')
        <div style="background: #fffbeb; border-left: 4px solid #f59e0b; padding: 12px 16px; border-radius: 6px; font-size: 13px; color: #92400e; margin-top: 20px;">
            <strong>Catatan Keterlambatan:</strong> Presensi Anda tercatat melebihi batas waktu yang telah ditentukan (pukul {{ config('attendance.late_after', '07:15') }} WIB). Harap hadir lebih awal pada pertemuan berikutnya.
        </div>
    @else
        <div style="background: #f0fdf4; border-left: 4px solid #22c55e; padding: 12px 16px; border-radius: 6px; font-size: 13px; color: #166534; margin-top: 20px;">
            Terima kasih telah hadir tepat waktu! Tetap pertahankan kedisiplinan dan semangat belajar Anda hari ini.
        </div>
    @endif
@endsection
