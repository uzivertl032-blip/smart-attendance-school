@extends('emails.layout')

@section('content')
    <h2 style="margin-top: 0; color: #0f172a; font-size: 18px;">Halo, {{ $student->name }}!</h2>
    <p style="color: #475569; font-size: 14px; margin-bottom: 20px;">
        Pemberitahuan resmi: Berdasarkan catatan sistem SmartAttendance, Anda <strong>belum melakukan presensi kehadiran</strong> untuk hari ini:
    </p>

    <div style="text-align: center; margin: 20px 0;">
        <span class="badge badge-warning" style="font-size: 14px; padding: 7px 16px;">
            ⏰ Pengingat: Belum Melakukan Presensi
        </span>
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
            <td class="label">Batas Jam Masuk</td>
            <td class="value">{{ $lateAfter }} WIB</td>
        </tr>
    </table>

    <div style="background: #fffbeb; border-left: 4px solid #f59e0b; padding: 12px 16px; border-radius: 6px; font-size: 13px; color: #92400e; margin-top: 20px;">
        <strong>Himbauan Sekolah:</strong>
        <ul style="margin: 6px 0 0 0; padding-left: 20px;">
            <li>Jika Anda sudah berada di sekolah, silakan segera melakukan presensi mandiri pada scanner wajah di gerbang/kiosk sekolah.</li>
            <li>Jika Anda berhalangan hadir karena sakit atau ada keperluan penting, mohon ajukan permohonan izin/sakit melalui portal siswa atau hubungi pihak sekolah.</li>
        </ul>
    </div>
@endsection
