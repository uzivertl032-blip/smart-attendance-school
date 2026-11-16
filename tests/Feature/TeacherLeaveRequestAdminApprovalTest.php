<?php

namespace Tests\Feature;

use App\Mail\AdminTeacherLeaveDecisionMail;
use App\Mail\AdminTeacherLeaveRequestSubmittedMail;
use App\Mail\TeacherLeaveRequestStatusMail;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TeacherLeaveRequestAdminApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin1;

    protected User $admin2;

    protected User $admin3;

    protected User $teacher1;

    protected User $teacher2;

    protected User $student;

    protected function setUp(): void
    {
        parent::setUp();

        // 3 Admin Accounts (matching database criteria: role=admin, status=active)
        $this->admin1 = User::factory()->create([
            'name' => 'Administrator Smart Attendance',
            'email' => 'admin@smartattendance.sch.id',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->admin2 = User::factory()->create([
            'name' => 'Admin Demo',
            'email' => 'admin@demo.com',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->admin3 = User::factory()->create([
            'name' => 'Arya Kusuma',
            'email' => 'aryakusuma@admin.id',
            'role' => 'admin',
            'status' => 'active',
        ]);

        // Teachers
        $this->teacher1 = User::factory()->create([
            'name' => 'Budi Santoso, S.Kom',
            'email' => 'budi.santoso@smartattendance.sch.id',
            'role' => 'teacher',
            'status' => 'active',
        ]);
        Teacher::create(['user_id' => $this->teacher1->id, 'nip' => '198501012010011001']);

        $this->teacher2 = User::factory()->create([
            'name' => 'Dhika Praja',
            'email' => 'dhikaguru@gmail.com',
            'role' => 'teacher',
            'status' => 'active',
        ]);
        Teacher::create(['user_id' => $this->teacher2->id, 'nip' => '199002022015021002']);

        // Student
        $this->student = User::factory()->create([
            'name' => 'Galih Bakbar',
            'email' => 'galih@gmail.com',
            'role' => 'student',
            'status' => 'active',
        ]);
        Student::create(['user_id' => $this->student->id, 'nis' => '10001', 'class' => 'XII-RPL 1']);
    }

    /**
     * Test Requirement 2 & 3: Teacher submits leave request -> 3 admins receive Gmail notification.
     */
    public function test_teacher_submits_leave_request_and_all_3_admins_receive_email(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->teacher1)->post(route('teacher.my-leave-requests.store'), [
            'status' => 'excused',
            'attendance_date' => Carbon::tomorrow()->toDateString(),
            'reason' => 'Mengikuti pelatihan kurikulum dinas pendidikan provinsi.',
        ]);

        $response->assertRedirect(route('teacher.my-leave-requests.index'));
        $response->assertSessionHas('success');

        // Verify Attendance created
        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->teacher1->id,
            'status' => 'excused',
            'approval_status' => 'pending',
            'reason' => 'Mengikuti pelatihan kurikulum dinas pendidikan provinsi.',
        ]);

        // Requirement 3: All 3 active admin emails in database receive notification
        Mail::assertSent(AdminTeacherLeaveRequestSubmittedMail::class, function ($mail) {
            return $mail->hasTo('admin@smartattendance.sch.id');
        });
        Mail::assertSent(AdminTeacherLeaveRequestSubmittedMail::class, function ($mail) {
            return $mail->hasTo('admin@demo.com');
        });
        Mail::assertSent(AdminTeacherLeaveRequestSubmittedMail::class, function ($mail) {
            return $mail->hasTo('aryakusuma@admin.id');
        });

        // Ensure NOT sent to other teachers
        Mail::assertNotSent(AdminTeacherLeaveRequestSubmittedMail::class, function ($mail) {
            return $mail->hasTo('dhikaguru@gmail.com');
        });
    }

    /**
     * Test Requirement 2: Teacher leave request does NOT appear in teacher approval list.
     */
    public function test_teacher_leave_request_does_not_appear_in_teacher_student_approval_list(): void
    {
        // 1. Create a teacher leave request
        $teacherAttendance = Attendance::create([
            'user_id' => $this->teacher1->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'attendance_time' => '07:00:00',
            'status' => 'sick',
            'reason' => 'Sakit demam perlu istirahat di rumah.',
            'approval_status' => 'pending',
        ]);

        // 2. Create a student leave request
        $studentAttendance = Attendance::create([
            'user_id' => $this->student->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'attendance_time' => '07:05:00',
            'status' => 'excused',
            'reason' => 'Acara keluarga mendesak.',
            'approval_status' => 'pending',
        ]);

        // Teacher 2 logs in to review student requests
        $response = $this->actingAs($this->teacher2)->get(route('teacher.leave-requests.index'));
        $response->assertStatus(200);

        // Student request MUST be visible
        $response->assertSee('Galih Bakbar');
        $response->assertSee('Acara keluarga mendesak.');

        // Teacher request MUST NOT be visible
        $response->assertDontSee('Budi Santoso, S.Kom');
        $response->assertDontSee('Sakit demam perlu istirahat di rumah.');

        // If teacher attempts to approve teacher leave request -> 403 Forbidden
        $approveAttempt = $this->actingAs($this->teacher2)->patch(route('teacher.leave-requests.approve', $teacherAttendance));
        $approveAttempt->assertStatus(403);
    }

    /**
     * Test Requirement 1 & 7: All 3 Admin accounts can view teacher leave requests.
     */
    public function test_all_3_admin_accounts_can_view_teacher_leave_requests(): void
    {
        Attendance::create([
            'user_id' => $this->teacher1->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'attendance_time' => '07:00:00',
            'status' => 'excused',
            'reason' => 'Dinas luar mengikuti seminar pendidikan nasional.',
            'approval_status' => 'pending',
        ]);

        // Admin 1 check
        $res1 = $this->actingAs($this->admin1)->get(route('admin.teacher-leave-requests.index'));
        $res1->assertStatus(200);
        $res1->assertSee('Budi Santoso, S.Kom');
        $res1->assertSee('Dinas luar mengikuti seminar pendidikan nasional.');

        // Admin 2 check
        $res2 = $this->actingAs($this->admin2)->get(route('admin.teacher-leave-requests.index'));
        $res2->assertStatus(200);
        $res2->assertSee('Budi Santoso, S.Kom');

        // Admin 3 check
        $res3 = $this->actingAs($this->admin3)->get(route('admin.teacher-leave-requests.index'));
        $res3->assertStatus(200);
        $res3->assertSee('Budi Santoso, S.Kom');
    }

    /**
     * Test Requirement 4 & 6: Admin 1 approves, status updates immediately, teacher notified.
     */
    public function test_admin_approves_teacher_leave_updates_status_and_notifies_teacher(): void
    {
        Mail::fake();

        $attendance = Attendance::create([
            'user_id' => $this->teacher1->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'attendance_time' => '07:00:00',
            'status' => 'excused',
            'reason' => 'Izin keperluan keluarga.',
            'approval_status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin1)->patch(route('admin.teacher-leave-requests.approve', $attendance));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Requirement 4: Database status immediately updated
        $attendance->refresh();
        $this->assertEquals('approved', $attendance->approval_status);
        $this->assertEquals($this->admin1->id, $attendance->approved_by);
        $this->assertNotNull($attendance->approved_at);

        // Requirement 6: Teacher receives email notification
        Mail::assertSent(TeacherLeaveRequestStatusMail::class, function ($mail) {
            return $mail->hasTo($this->teacher1->email);
        });

        // All administrator accounts also receive the decision by email
        foreach ([$this->admin1, $this->admin2, $this->admin3] as $admin) {
            Mail::assertSent(AdminTeacherLeaveDecisionMail::class, function ($mail) use ($admin) {
                return $mail->hasTo($admin->email);
            });
        }

        $response->assertSessionHas('success', function (string $message): bool {
            return str_contains($message, 'Notifikasi email terkirim ke guru dan 3 admin.');
        });

        // Other admins immediately see the approved status and approver name
        $resAdmin2 = $this->actingAs($this->admin2)->get(route('admin.teacher-leave-requests.index'));
        $resAdmin2->assertStatus(200);
        $resAdmin2->assertSee('Disetujui');
        $resAdmin2->assertSee('Administrator Smart Attendance');
    }

    /**
     * Test Requirement 5: Prevent duplicate processing by multiple admins.
     */
    public function test_prevents_duplicate_processing_by_another_admin(): void
    {
        Mail::fake();

        $attendance = Attendance::create([
            'user_id' => $this->teacher1->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'attendance_time' => '07:00:00',
            'status' => 'sick',
            'reason' => 'Sakit migrain.',
            'approval_status' => 'pending',
        ]);

        // Admin 1 approves first
        $this->actingAs($this->admin1)->patch(route('admin.teacher-leave-requests.approve', $attendance));
        $attendance->refresh();
        $this->assertEquals('approved', $attendance->approval_status);

        // Admin 2 now tries to approve or reject the same attendance
        $resAdmin2Approve = $this->actingAs($this->admin2)->patch(route('admin.teacher-leave-requests.approve', $attendance));
        $resAdmin2Approve->assertSessionHas('warning');

        $resAdmin2Reject = $this->actingAs($this->admin2)->patch(route('admin.teacher-leave-requests.reject', $attendance), [
            'rejection_note' => 'Coba tolak pengajuan yang sudah disetujui',
        ]);
        $resAdmin2Reject->assertSessionHas('warning');

        // Status remains approved by Admin 1, NOT overwritten by Admin 2
        $attendance->refresh();
        $this->assertEquals('approved', $attendance->approval_status);
        $this->assertEquals($this->admin1->id, $attendance->approved_by);
    }

    /**
     * Test Requirement 6: Admin rejects teacher leave with note -> teacher notified with note.
     */
    public function test_admin_rejects_teacher_leave_with_note_and_notifies_teacher(): void
    {
        Mail::fake();

        $attendance = Attendance::create([
            'user_id' => $this->teacher1->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'attendance_time' => '07:00:00',
            'status' => 'excused',
            'reason' => 'Izin menghadiri reuni.',
            'approval_status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin3)->patch(route('admin.teacher-leave-requests.reject', $attendance), [
            'rejection_note' => 'Jadwal mengajar kelas XII tidak dapat ditinggalkan hari ini.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('info');

        $attendance->refresh();
        $this->assertEquals('rejected', $attendance->approval_status);
        $this->assertEquals($this->admin3->id, $attendance->approved_by);
        $this->assertEquals('Jadwal mengajar kelas XII tidak dapat ditinggalkan hari ini.', $attendance->rejection_note);

        // Teacher notified
        Mail::assertSent(TeacherLeaveRequestStatusMail::class, function ($mail) {
            return $mail->hasTo($this->teacher1->email);
        });

        // All administrator accounts also receive the rejection decision
        foreach ([$this->admin1, $this->admin2, $this->admin3] as $admin) {
            Mail::assertSent(AdminTeacherLeaveDecisionMail::class, function ($mail) use ($admin) {
                return $mail->hasTo($admin->email);
            });
        }
    }

    /**
     * Test ADMIN_NOTIFICATION_EMAILS overrides the administrator account
     * emails so real Gmail inboxes can receive teacher leave submissions.
     */
    public function test_configured_admin_notification_emails_override_database_admin_emails(): void
    {
        Mail::fake();

        config(['attendance.admin_notification_emails' => [
            'admin.satu@gmail.com',
            'admin.dua@gmail.com',
            'admin.tiga@gmail.com',
        ]]);

        $response = $this->actingAs($this->teacher1)->post(route('teacher.my-leave-requests.store'), [
            'status' => 'excused',
            'attendance_date' => Carbon::tomorrow()->toDateString(),
            'reason' => 'Menghadiri rapat koordinasi dinas pendidikan provinsi.',
        ]);

        $response->assertRedirect(route('teacher.my-leave-requests.index'));
        $response->assertSessionHas('success', function (string $message): bool {
            return str_contains($message, '3 alamat admin');
        });

        foreach (['admin.satu@gmail.com', 'admin.dua@gmail.com', 'admin.tiga@gmail.com'] as $email) {
            Mail::assertSent(AdminTeacherLeaveRequestSubmittedMail::class, function ($mail) use ($email) {
                return $mail->hasTo($email);
            });
        }

        Mail::assertNotSent(AdminTeacherLeaveRequestSubmittedMail::class, function ($mail) {
            return $mail->hasTo('admin@demo.com');
        });
    }

    /**
     * Test duplicates and invalid configured recipients are ignored.
     */
    public function test_duplicate_and_invalid_configured_admin_emails_are_ignored(): void
    {
        Mail::fake();

        config(['attendance.admin_notification_emails' => [
            'admin.satu@gmail.com',
            'admin.satu@gmail.com',
            'bukan-email',
            '',
        ]]);

        $response = $this->actingAs($this->teacher1)->post(route('teacher.my-leave-requests.store'), [
            'status' => 'sick',
            'attendance_date' => Carbon::tomorrow()->toDateString(),
            'reason' => 'Sakit demam dan perlu istirahat sesuai anjuran dokter.',
        ]);

        $response->assertSessionHas('success', function (string $message): bool {
            return str_contains($message, '1 alamat admin');
        });

        Mail::assertSent(AdminTeacherLeaveRequestSubmittedMail::class, 1);
    }

    /**
     * Test approval still succeeds and reports a warning when the teacher email
     * stored in the database is invalid.
     */
    public function test_approval_still_succeeds_when_teacher_email_is_invalid(): void
    {
        Mail::fake();

        $this->teacher1->update(['email' => 'bukan-email-valid']);

        $attendance = Attendance::create([
            'user_id' => $this->teacher1->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'attendance_time' => '07:00:00',
            'status' => 'excused',
            'reason' => 'Izin keperluan keluarga.',
            'approval_status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin1)->patch(route('admin.teacher-leave-requests.approve', $attendance));

        $response->assertRedirect();
        $response->assertSessionHas('success', function (string $message): bool {
            return str_contains($message, 'gagal dikirim');
        });

        $attendance->refresh();
        $this->assertEquals('approved', $attendance->approval_status);
        $this->assertEquals($this->admin1->id, $attendance->approved_by);

        Mail::assertNotSent(TeacherLeaveRequestStatusMail::class);

        foreach ([$this->admin1, $this->admin2, $this->admin3] as $admin) {
            Mail::assertSent(AdminTeacherLeaveDecisionMail::class, function ($mail) use ($admin) {
                return $mail->hasTo($admin->email);
            });
        }
    }

    /**
     * Test the decision email is sent to the configured Gmail recipients.
     */
    public function test_decision_notification_is_sent_to_configured_admin_recipients(): void
    {
        Mail::fake();

        config(['attendance.admin_notification_emails' => [
            'admin.satu@gmail.com',
            'admin.dua@gmail.com',
            'admin.tiga@gmail.com',
        ]]);

        $attendance = Attendance::create([
            'user_id' => $this->teacher1->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'attendance_time' => '07:00:00',
            'status' => 'sick',
            'reason' => 'Sakit dan memerlukan istirahat.',
            'approval_status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin1)->patch(route('admin.teacher-leave-requests.approve', $attendance));

        $response->assertSessionHas('success', function (string $message): bool {
            return str_contains($message, 'Notifikasi email terkirim ke guru dan 3 admin.');
        });

        foreach (['admin.satu@gmail.com', 'admin.dua@gmail.com', 'admin.tiga@gmail.com'] as $email) {
            Mail::assertSent(AdminTeacherLeaveDecisionMail::class, function ($mail) use ($email) {
                return $mail->hasTo($email);
            });
        }

        Mail::assertNotSent(AdminTeacherLeaveDecisionMail::class, function ($mail) {
            return $mail->hasTo('admin@demo.com');
        });
    }

    /**
     * Test Non-Admin cannot access admin teacher leave routes.
     */
    public function test_non_admin_cannot_access_admin_teacher_leave_routes(): void
    {
        $attendance = Attendance::create([
            'user_id' => $this->teacher1->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'status' => 'excused',
            'reason' => 'Izin tes.',
            'approval_status' => 'pending',
        ]);

        // Teacher attempt
        $resTeacher = $this->actingAs($this->teacher2)->get(route('admin.teacher-leave-requests.index'));
        $resTeacher->assertStatus(403);

        $resTeacherApprove = $this->actingAs($this->teacher2)->patch(route('admin.teacher-leave-requests.approve', $attendance));
        $resTeacherApprove->assertStatus(403);

        // Student attempt
        $resStudent = $this->actingAs($this->student)->get(route('admin.teacher-leave-requests.index'));
        $resStudent->assertStatus(403);
    }
}
