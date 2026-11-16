<?php

namespace Tests\Feature;

use App\Mail\StudentAttendanceRecordedMail;
use App\Mail\StudentAttendanceReminderMail;
use App\Mail\StudentLeaveRequestStatusMail;
use App\Mail\TeacherLeaveRequestSubmittedMail;
use App\Mail\TeacherStudentLeaveDecisionMail;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AttendanceNotificationService;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AttendanceNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * 1. Siswa berhasil absen dan notifikasi email dipicu.
     * Admin TIDAK menerima email.
     */
    public function test_student_attendance_triggers_confirmation_email_to_student_only(): void
    {
        Mail::fake();

        $admin = User::factory()->create([
            'email' => 'admin@smartattendance.sch.id',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $studentUser = User::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi.santoso@siswa.sch.id',
            'role' => 'student',
            'status' => 'active',
        ]);
        Student::create([
            'user_id' => $studentUser->id,
            'nis' => '10001',
            'class' => 'X-RPL',
        ]);

        $attendanceService = app(AttendanceService::class);
        $token = $attendanceService->issueVerificationToken($studentUser);

        $customTime = Carbon::parse('2026-10-25 07:05:00');

        $result = $attendanceService->recordAttendance(
            customTime: $customTime,
            status: 'present',
            verificationToken: $token
        );

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('attendances', [
            'user_id' => $studentUser->id,
            'status' => 'present',
        ]);

        // Email dipicu untuk siswa
        Mail::assertSent(StudentAttendanceRecordedMail::class, function ($mail) use ($studentUser) {
            return $mail->hasTo($studentUser->email)
                && $mail->attendance->user_id === $studentUser->id
                && $mail->attendance->status === 'present';
        });

        // Kebijakan: Admin TIDAK menerima email konfirmasi absensi
        Mail::assertNotSent(StudentAttendanceRecordedMail::class, function ($mail) use ($admin) {
            return $mail->hasTo($admin->email);
        });
    }

    /**
     * 2. Pengajuan izin/sakit siswa memicu email kepada guru yang berwenang.
     * Admin TIDAK menerima email.
     */
    public function test_student_leave_submission_triggers_email_to_active_teachers(): void
    {
        Mail::fake();

        $admin = User::factory()->create([
            'email' => 'admin@smartattendance.sch.id',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $teacher1 = User::factory()->create([
            'name' => 'Pak Joko',
            'email' => 'joko@guru.sch.id',
            'role' => 'teacher',
            'status' => 'active',
        ]);
        Teacher::create(['user_id' => $teacher1->id, 'nip' => '19800101']);

        $teacher2 = User::factory()->create([
            'name' => 'Ibu Siti',
            'email' => 'siti@guru.sch.id',
            'role' => 'teacher',
            'status' => 'active',
        ]);
        Teacher::create(['user_id' => $teacher2->id, 'nip' => '19820202']);

        $student = User::factory()->create([
            'name' => 'Dimas Pratama',
            'email' => 'dimas@siswa.sch.id',
            'role' => 'student',
            'status' => 'active',
        ]);
        Student::create(['user_id' => $student->id, 'nis' => '10002', 'class' => 'XI-TKJ']);

        $response = $this->actingAs($student)->post(route('student.leave-requests.store'), [
            'status' => 'sick',
            'attendance_date' => Carbon::today()->toDateString(),
            'reason' => 'Demam tinggi dan istirahat dokter',
        ]);

        $response->assertRedirect(route('student.leave-requests.index'));
        $this->assertDatabaseHas('attendances', [
            'user_id' => $student->id,
            'status' => 'sick',
            'approval_status' => 'pending',
        ]);

        // Email terkirim ke kedua guru aktif
        Mail::assertSent(TeacherLeaveRequestSubmittedMail::class, function ($mail) use ($teacher1) {
            return $mail->hasTo($teacher1->email);
        });

        Mail::assertSent(TeacherLeaveRequestSubmittedMail::class, function ($mail) use ($teacher2) {
            return $mail->hasTo($teacher2->email);
        });

        // Kebijakan: Admin TIDAK menerima email pengajuan izin/sakit
        Mail::assertNotSent(TeacherLeaveRequestSubmittedMail::class, function ($mail) use ($admin) {
            return $mail->hasTo($admin->email);
        });
    }

    /**
     * 3. Keputusan izin/sakit oleh guru memicu email ke siswa terkait.
     * Uji approval (disetujui) dan rejection (ditolak).
     */
    public function test_teacher_approval_and_rejection_triggers_email_to_student(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['email' => 'admin@smartattendance.sch.id', 'role' => 'admin']);

        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        Teacher::create(['user_id' => $teacher->id, 'nip' => '19800101']);

        $student = User::factory()->create([
            'email' => 'andi@siswa.sch.id',
            'role' => 'student',
            'status' => 'active',
        ]);
        Student::create(['user_id' => $student->id, 'nis' => '10003', 'class' => 'XII-RPL']);

        $attendance = Attendance::create([
            'user_id' => $student->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'attendance_time' => '07:00:00',
            'status' => 'sick',
            'reason' => 'Sakit demam',
            'approval_status' => 'pending',
        ]);

        // 3a. Guru menyetujui (Approve)
        $resApprove = $this->actingAs($teacher)->patch(route('teacher.leave-requests.approve', $attendance));
        $resApprove->assertSessionHas('success');

        $attendance->refresh();
        $this->assertEquals('approved', $attendance->approval_status);

        Mail::assertSent(StudentLeaveRequestStatusMail::class, function ($mail) use ($student) {
            return $mail->hasTo($student->email)
                && $mail->attendance->approval_status === 'approved';
        });

        // Keputusan juga diarsipkan ke email guru yang meninjau
        Mail::assertSent(TeacherStudentLeaveDecisionMail::class, function ($mail) use ($teacher) {
            return $mail->hasTo($teacher->email)
                && $mail->attendance->approval_status === 'approved';
        });

        // 3b. Guru menolak pengajuan lain (Reject)
        Cache::flush();
        $attendanceReject = Attendance::create([
            'user_id' => $student->id,
            'attendance_date' => Carbon::tomorrow()->toDateString(),
            'attendance_time' => '07:00:00',
            'status' => 'excused',
            'reason' => 'Acara keluarga mendadak',
            'approval_status' => 'pending',
        ]);

        $resReject = $this->actingAs($teacher)->patch(route('teacher.leave-requests.reject', $attendanceReject), [
            'rejection_note' => 'Surat keterangan dari orang tua belum dilampirkan.',
        ]);
        $resReject->assertSessionHas('info');

        $attendanceReject->refresh();
        $this->assertEquals('rejected', $attendanceReject->approval_status);

        Mail::assertSent(StudentLeaveRequestStatusMail::class, function ($mail) use ($student) {
            return $mail->hasTo($student->email)
                && $mail->attendance->approval_status === 'rejected'
                && $mail->attendance->rejection_note === 'Surat keterangan dari orang tua belum dilampirkan.';
        });

        Mail::assertSent(TeacherStudentLeaveDecisionMail::class, function ($mail) use ($teacher) {
            return $mail->hasTo($teacher->email)
                && $mail->attendance->approval_status === 'rejected';
        });

        // Kebijakan: Admin TIDAK menerima email status keputusan ini
        Mail::assertNotSent(StudentLeaveRequestStatusMail::class, function ($mail) use ($admin) {
            return $mail->hasTo($admin->email);
        });
    }

    /**
     * 4. Kegagalan email tidak membatalkan absensi atau pengajuan yang berhasil disimpan.
     */
    public function test_email_failure_does_not_abort_attendance_or_leave_storage(): void
    {
        // Mock Mail facade agar melempar exception koneksi SMTP
        Mail::shouldReceive('to')
            ->andThrow(new Exception('SMTP Connection Timeout: smtp.gmail.com could not be reached.'));

        $student = User::factory()->create([
            'name' => 'Faisal',
            'email' => 'faisal@siswa.sch.id',
            'role' => 'student',
            'status' => 'active',
        ]);
        Student::create(['user_id' => $student->id, 'nis' => '10004', 'class' => 'X-TKJ']);

        $attendanceService = app(AttendanceService::class);
        $token = $attendanceService->issueVerificationToken($student);

        // Rekam absensi meskipun SMTP down
        $result = $attendanceService->recordAttendance(
            customTime: Carbon::parse('2026-10-25 07:00:00'),
            status: 'present',
            verificationToken: $token
        );

        // Transaksi absensi tetap sukses dan tersimpan permanen di DB
        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('attendances', [
            'user_id' => $student->id,
            'status' => 'present',
        ]);

        // Rekam pengajuan izin meskipun SMTP down
        $response = $this->actingAs($student)->post(route('student.leave-requests.store'), [
            'status' => 'excused',
            'attendance_date' => Carbon::now()->addDays(2)->toDateString(),
            'reason' => 'Mengikuti olimpiade sains nasional',
        ]);

        $response->assertRedirect(route('student.leave-requests.index'));
        $this->assertDatabaseHas('attendances', [
            'user_id' => $student->id,
            'status' => 'excused',
            'approval_status' => 'pending',
        ]);
    }

    /**
     * 5. Email tidak terkirim ganda (Deduplication check).
     */
    public function test_email_deduplication_prevents_duplicate_dispatches(): void
    {
        Mail::fake();

        $student = User::factory()->create([
            'name' => 'Dewi',
            'email' => 'dewi@siswa.sch.id',
            'role' => 'student',
            'status' => 'active',
        ]);
        Student::create(['user_id' => $student->id, 'nis' => '10005', 'class' => 'X-A']);

        $attendance = Attendance::create([
            'user_id' => $student->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'attendance_time' => '07:00:00',
            'status' => 'present',
        ]);

        $notificationService = app(AttendanceNotificationService::class);

        // Panggilan pertama: terkirim
        $sent1 = $notificationService->sendAttendanceRecordedNotification($attendance);
        $this->assertTrue($sent1);

        // Panggilan kedua untuk data yang sama (misal refresh browser): dicegah
        $sent2 = $notificationService->sendAttendanceRecordedNotification($attendance);
        $this->assertTrue($sent2);

        // Hanya terkirim tepat 1 kali
        Mail::assertSent(StudentAttendanceRecordedMail::class, 1);
    }

    /**
     * 6. Pengingat hanya dikirim kepada siswa yang benar-benar belum absen setelah batas waktu.
     */
    public function test_reminders_command_only_targets_unattended_students(): void
    {
        Mail::fake();

        // Siswa 1: SUDAH hadir
        $studentAttended = User::factory()->create([
            'name' => 'Siswa Hadir',
            'email' => 'hadir@siswa.sch.id',
            'role' => 'student',
            'status' => 'active',
        ]);
        Student::create(['user_id' => $studentAttended->id, 'nis' => '20001', 'class' => 'X-A']);

        Attendance::create([
            'user_id' => $studentAttended->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'attendance_time' => '07:00:00',
            'status' => 'present',
        ]);

        // Siswa 2: BELUM hadir
        $studentUnattended = User::factory()->create([
            'name' => 'Siswa Belum Hadir',
            'email' => 'belum.hadir@siswa.sch.id',
            'role' => 'student',
            'status' => 'active',
        ]);
        Student::create(['user_id' => $studentUnattended->id, 'nis' => '20002', 'class' => 'X-B']);

        // Jalankan artisan command dengan --force agar dapat diuji kapan pun
        $this->artisan('attendance:send-reminders --force')
            ->expectsOutputToContain('Ditemukan 1 siswa yang belum melakukan absensi')
            ->expectsOutputToContain('Berhasil dikirim: 1')
            ->assertExitCode(0);

        // Email pengingat terkirim ke siswa yang belum absen
        Mail::assertSent(StudentAttendanceReminderMail::class, function ($mail) use ($studentUnattended) {
            return $mail->hasTo($studentUnattended->email);
        });

        // Email pengingat TIDAK terkirim ke siswa yang sudah absen
        Mail::assertNotSent(StudentAttendanceReminderMail::class, function ($mail) use ($studentAttended) {
            return $mail->hasTo($studentAttended->email);
        });
    }

    /**
     * 7. Pengingat tidak dikirim sebelum batas waktu absensi (tanpa flag --force).
     */
    public function test_reminders_are_not_sent_before_cutoff_time(): void
    {
        Mail::fake();

        // Atur waktu saat ini menjadi jam 06:45 (sebelum 07:15)
        Carbon::setTestNow(Carbon::parse('2026-10-25 06:45:00'));

        $student = User::factory()->create([
            'name' => 'Siswa Pagi',
            'email' => 'pagi@siswa.sch.id',
            'role' => 'student',
            'status' => 'active',
        ]);
        Student::create(['user_id' => $student->id, 'nis' => '20003', 'class' => 'X-C']);

        // Command dijalankan tanpa --force
        $this->artisan('attendance:send-reminders')
            ->expectsOutputToContain('belum mencapai batas waktu keterlambatan')
            ->assertExitCode(0);

        // Tidak ada email yang terkirim
        Mail::assertNothingSent();

        Carbon::setTestNow(); // Reset time
    }

    /**
     * 8. Template email pengajuan izin/sakit guru memuat semua field wajib & konsisten.
     */
    public function test_teacher_leave_request_submitted_template_contains_all_required_fields(): void
    {
        $teacher = User::factory()->create(['name' => 'Bapak Guru Budi', 'role' => 'teacher']);
        $studentUser = User::factory()->create(['name' => 'Siswa Cerdas', 'role' => 'student']);
        $studentProfile = Student::create(['user_id' => $studentUser->id, 'nis' => '54321', 'class' => 'XII-RPL 2']);
        $studentUser->setRelation('student', $studentProfile);

        $attendance = Attendance::create([
            'user_id' => $studentUser->id,
            'attendance_date' => Carbon::parse('2026-10-30')->toDateString(),
            'attendance_time' => '08:15:00',
            'status' => 'sick',
            'reason' => 'Sakit tifus rawat inap di rumah sakit',
            'approval_status' => 'pending',
        ]);
        $attendance->setRelation('user', $studentUser);

        $mailable = new TeacherLeaveRequestSubmittedMail($attendance, $teacher);
        $html = $mailable->render();

        $this->assertStringContainsString('SmartAttendance School', $html);
        $this->assertStringContainsString('Siswa Cerdas', $html);
        $this->assertStringContainsString('54321', $html);
        $this->assertStringContainsString('XII-RPL 2', $html);
        $this->assertStringContainsString('Tanggal Pengajuan', $html);
        $this->assertStringContainsString('Tanggal Izin / Sakit', $html);
        $this->assertStringContainsString('Sakit tifus rawat inap di rumah sakit', $html);
        $this->assertStringContainsString('Menunggu Persetujuan', $html);
        $this->assertStringContainsString('badge badge-warning', $html);
    }

    /**
     * 9. Template email hasil keputusan guru memuat status disetujui / ditolak & catatan guru.
     */
    public function test_student_leave_request_status_template_contains_decision_and_notes(): void
    {
        $teacher = User::factory()->create(['name' => 'Ibu Guru Ani', 'role' => 'teacher']);
        $studentUser = User::factory()->create(['name' => 'Andi Wijaya', 'role' => 'student']);
        $studentProfile = Student::create(['user_id' => $studentUser->id, 'nis' => '67890', 'class' => 'XI-TKJ 1']);
        $studentUser->setRelation('student', $studentProfile);

        // Kasus 1: Disetujui
        $attendanceApproved = Attendance::create([
            'user_id' => $studentUser->id,
            'attendance_date' => Carbon::parse('2026-10-28')->toDateString(),
            'attendance_time' => '07:30:00',
            'status' => 'excused',
            'reason' => 'Menghadiri wisuda kakak di luar kota',
            'approval_status' => 'approved',
            'approved_by' => $teacher->id,
            'approved_at' => Carbon::now(),
        ]);
        $attendanceApproved->setRelation('user', $studentUser);
        $attendanceApproved->setRelation('approver', $teacher);

        $mailableApproved = new StudentLeaveRequestStatusMail($attendanceApproved);
        $htmlApproved = $mailableApproved->render();

        $this->assertStringContainsString('Andi Wijaya', $htmlApproved);
        $this->assertStringContainsString('67890', $htmlApproved);
        $this->assertStringContainsString('XI-TKJ 1', $htmlApproved);
        $this->assertStringContainsString('Disetujui', $htmlApproved);
        $this->assertStringContainsString('Ibu Guru Ani', $htmlApproved);
        $this->assertStringContainsString('Menghadiri wisuda kakak di luar kota', $htmlApproved);

        // Kasus 2: Ditolak dengan catatan
        $attendanceRejected = Attendance::create([
            'user_id' => $studentUser->id,
            'attendance_date' => Carbon::parse('2026-10-29')->toDateString(),
            'attendance_time' => '07:30:00',
            'status' => 'excused',
            'reason' => 'Keperluan keluarga',
            'approval_status' => 'rejected',
            'approved_by' => $teacher->id,
            'approved_at' => Carbon::now(),
            'rejection_note' => 'Surat permohonan dari wali belum diserahkan ke ruang guru.',
        ]);
        $attendanceRejected->setRelation('user', $studentUser);
        $attendanceRejected->setRelation('approver', $teacher);

        $mailableRejected = new StudentLeaveRequestStatusMail($attendanceRejected);
        $htmlRejected = $mailableRejected->render();

        $this->assertStringContainsString('Ditolak', $htmlRejected);
        $this->assertStringContainsString('Catatan Penolakan', $htmlRejected);
        $this->assertStringContainsString('Surat permohonan dari wali belum diserahkan ke ruang guru.', $htmlRejected);
    }
}
