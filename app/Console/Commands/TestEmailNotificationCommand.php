<?php

namespace App\Console\Commands;

use App\Mail\AdminTeacherLeaveRequestSubmittedMail;
use App\Mail\StudentAttendanceRecordedMail;
use App\Mail\StudentAttendanceReminderMail;
use App\Mail\StudentLeaveRequestStatusMail;
use App\Mail\TeacherLeaveRequestStatusMail;
use App\Mail\TeacherLeaveRequestSubmittedMail;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TestEmailNotificationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:test-email
                            {--to= : Alamat email penerima tunggal untuk uji coba}
                            {--type=attendance : Jenis template (attendance, leave-submitted, leave-status, reminder, admin-teacher-leave, teacher-leave-status)}
                            {--dry-run : Hanya uji render template tanpa mengirim email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Uji coba aman untuk konfigurasi dan template email notifikasi SmartAttendance';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('====================================================');
        $this->info(' DIAGNOSTIK & PENGUJIAN AMAN EMAIL SMARTATTENDANCE');
        $this->info('====================================================');

        // 1. Tampilkan status konfigurasi saat ini (tanpa membocorkan password)
        $mailer = config('mail.default');
        $host = config('mail.mailers.smtp.host');
        $port = config('mail.mailers.smtp.port');
        $encryption = config('mail.mailers.smtp.encryption') ?: config('mail.mailers.smtp.scheme');
        $username = config('mail.mailers.smtp.username');
        $password = config('mail.mailers.smtp.password');
        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name');

        $hasPassword = ! empty($password);
        $maskedUser = $username ? substr($username, 0, 3).'***@'.(explode('@', $username)[1] ?? '') : 'belum diisi';

        $this->table(
            ['Parameter Konfigurasi', 'Nilai Saat Ini', 'Status'],
            [
                ['MAIL_MAILER', $mailer, $mailer === 'smtp' ? '✓ Mode SMTP Aktif' : 'ℹ Mode Log (Aman untuk dev)'],
                ['MAIL_HOST', $host ?? '-', $host === 'smtp.gmail.com' ? '✓ Gmail SMTP' : 'Lainnya / Default'],
                ['MAIL_PORT', $port ?? '-', ($port == 587 || $port == 465) ? '✓ Standar TLS/SSL' : 'Port Non-SSL'],
                ['MAIL_ENCRYPTION', $encryption ?? 'tls', '✓ Enkripsi TLS'],
                ['MAIL_USERNAME', $maskedUser, ! empty($username) ? '✓ Terisi' : 'Belum diisi'],
                ['MAIL_PASSWORD', $hasPassword ? '******** (Tersedia)' : 'Kosong', $hasPassword ? '✓ Kredensial Ada' : 'Belum diisi'],
                ['MAIL_FROM_ADDRESS', $fromAddress ?? '-', ! empty($fromAddress) ? '✓ Valid' : 'Belum diisi'],
                ['MAIL_FROM_NAME', $fromName ?? '-', '✓'],
            ]
        );

        // 2. Siapkan model dummy in-memory (TIDAK menyentuh atau mengubah database)
        $dummyStudentUser = new User([
            'id' => 9999,
            'name' => 'Siswa Percobaan (Uji Coba)',
            'email' => 'siswa.test@example.com',
            'role' => 'student',
            'status' => 'active',
        ]);
        $dummyStudentProfile = new Student([
            'nis' => '99999',
            'class' => 'XII-RPL 1',
        ]);
        $dummyStudentUser->setRelation('student', $dummyStudentProfile);

        $dummyTeacherUser = new User([
            'id' => 8888,
            'name' => 'Bapak Guru Uji Coba, S.Pd',
            'email' => 'guru.test@example.com',
            'role' => 'teacher',
            'status' => 'active',
        ]);
        $dummyTeacherProfile = new Teacher([
            'nip' => '198501019999',
        ]);
        $dummyTeacherUser->setRelation('teacher', $dummyTeacherProfile);

        // 3. Tentukan jenis mailable yang diuji
        $type = strtolower((string) $this->option('type'));

        $dummyAttendance = new Attendance([
            'id' => 7777,
            'user_id' => $dummyStudentUser->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'attendance_time' => Carbon::now()->format('H:i:s'),
            'status' => in_array($type, ['leave-submitted', 'leave-status'], true) ? 'sick' : 'present',
            'reason' => 'Demam dan flu perlu istirahat di rumah sesuai anjuran dokter.',
            'approval_status' => ($type === 'leave-submitted') ? 'pending' : 'approved',
            'approved_at' => Carbon::now(),
            'rejection_note' => null,
        ]);
        $dummyAttendance->created_at = Carbon::now()->subMinutes(30);
        $dummyAttendance->setRelation('user', $dummyStudentUser);
        $dummyAttendance->setRelation('approver', $dummyTeacherUser);

        $dummyAdminUser = new User([
            'id' => 1111,
            'name' => 'Administrator Utama',
            'email' => 'admin.test@example.com',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $dummyTeacherAttendance = new Attendance([
            'id' => 6666,
            'user_id' => $dummyTeacherUser->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'attendance_time' => Carbon::now()->format('H:i:s'),
            'status' => 'excused',
            'reason' => 'Mengikuti pelatihan kurikulum di dinas pendidikan provinsi.',
            'approval_status' => ($type === 'admin-teacher-leave') ? 'pending' : 'approved',
            'approved_at' => Carbon::now(),
            'rejection_note' => null,
        ]);
        $dummyTeacherAttendance->created_at = Carbon::now()->subMinutes(20);
        $dummyTeacherAttendance->setRelation('user', $dummyTeacherUser);
        $dummyTeacherAttendance->setRelation('approver', $dummyAdminUser);

        $mailable = match ($type) {
            'leave-submitted' => new TeacherLeaveRequestSubmittedMail($dummyAttendance, $dummyTeacherUser),
            'leave-status' => new StudentLeaveRequestStatusMail($dummyAttendance),
            'admin-teacher-leave' => new AdminTeacherLeaveRequestSubmittedMail($dummyTeacherAttendance, $dummyAdminUser),
            'teacher-leave-status' => new TeacherLeaveRequestStatusMail($dummyTeacherAttendance),
            'reminder' => new StudentAttendanceReminderMail($dummyStudentUser, Carbon::today()->toDateString()),
            default => new StudentAttendanceRecordedMail($dummyAttendance),
        };

        $this->line('');
        $this->info("Menyiapkan template email jenis: [{$type}]");

        // 4. Uji Render Template Blade ke HTML (memastikan tidak ada sintaks error di template)
        try {
            $renderedHtml = $mailable->render();
            $this->info('✓ Template Blade berhasil di-render tanpa kendala ('.strlen($renderedHtml).' bytes).');
        } catch (Throwable $e) {
            $this->error('✕ Gagal me-render template email: '.$e->getMessage());

            return self::FAILURE;
        }

        // 5. Cek apakah pengguna meminta kirim ke email spesifik (--to)
        $targetEmail = $this->option('to');
        $isDryRun = (bool) $this->option('dry-run');

        if ($isDryRun || empty($targetEmail)) {
            $this->line('');
            $this->comment('Status Pengujian: DRY-RUN / MODE AMAN SELESAI');
            $this->line('  - Tidak ada email sungguhan yang dikirim.');
            $this->line('  - Tidak ada data absensi atau siswa di database yang diubah.');
            $this->line('  - Template email terbukti valid dan siap digunakan.');
            $this->line('');
            $this->info('Petunjuk untuk menguji kirim 1 email nyata ke alamat Anda:');
            $this->line('  php artisan attendance:test-email --to=email-anda@gmail.com');

            return self::SUCCESS;
        }

        // Validasi format email tujuan
        if (! filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
            $this->error("✕ Format alamat email tujuan tidak valid: {$targetEmail}");

            return self::FAILURE;
        }

        $this->line('');
        $this->warn("PERINGATAN: Anda meminta pengiriman 1 email uji coba ke: {$targetEmail}");
        $this->line("Mailer yang digunakan: [{$mailer}]");

        try {
            Mail::to($targetEmail)->send($mailable);

            $this->info("✓ Email uji coba berhasil diproses untuk: {$targetEmail}");

            if ($mailer === 'log') {
                $this->comment('ℹ Karena MAIL_MAILER=log di file .env, isi email disimpan di storage/logs/laravel.log dan tidak dikirim ke internet.');
            } else {
                $this->comment("ℹ Email dikirim via driver [{$mailer}]. Silakan periksa Kotak Masuk atau folder Spam pada {$targetEmail}.");
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('✕ Gagal mengirim email: '.$e->getMessage());
            $this->line('');
            $this->warn('Kemungkinan penyebab kegagalan:');
            $this->line('1. Kredensial Gmail di .env belum tepat atau masih berupa nilai default dummy.');
            $this->line('2. Belum menggunakan Google App Password (16 digit) dari akun Google.');
            $this->line('3. Port 587 diblokir oleh firewall atau antivirus lokal.');

            return self::FAILURE;
        }
    }
}
