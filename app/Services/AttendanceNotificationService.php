<?php

namespace App\Services;

use App\Mail\AdminTeacherLeaveDecisionMail;
use App\Mail\AdminTeacherLeaveRequestSubmittedMail;
use App\Mail\StudentAttendanceRecordedMail;
use App\Mail\StudentAttendanceReminderMail;
use App\Mail\StudentLeaveRequestStatusMail;
use App\Mail\TeacherLeaveRequestStatusMail;
use App\Mail\TeacherLeaveRequestSubmittedMail;
use App\Mail\TeacherStudentLeaveDecisionMail;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AttendanceNotificationService
{
    /**
     * Send email confirmation to a student after attendance is successfully recorded (present or late).
     * Strictly restricted to students; Administrator and other roles NEVER receive this email.
     */
    public function sendAttendanceRecordedNotification(Attendance $attendance): bool
    {
        $attendance->loadMissing(['user.student']);
        $user = $attendance->user;

        // Policy: Strictly for students only
        if (! $user || $user->role !== 'student') {
            return false;
        }

        // Policy: Only for recorded attendances (present or late)
        if (! in_array($attendance->status, ['present', 'late'], true)) {
            return false;
        }

        // Policy: Prevent duplicate email dispatch
        $cacheKey = "notif_attendance_recorded_{$attendance->id}";
        if (Cache::has($cacheKey)) {
            return true;
        }

        // Validate recipient email address
        $email = trim((string) $user->email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Notifikasi absensi siswa dilewati: alamat email tidak valid atau belum diisi.', [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $email,
                'attendance_id' => $attendance->id,
            ]);

            return false;
        }

        try {
            Mail::to($email)->send(new StudentAttendanceRecordedMail($attendance));
            Cache::put($cacheKey, true, now()->addDay());

            Log::info('Notifikasi email absensi siswa berhasil dikirim.', [
                'user_id' => $user->id,
                'email' => $email,
                'status' => $attendance->status,
                'attendance_id' => $attendance->id,
            ]);

            return true;
        } catch (Throwable $e) {
            // Failure must never roll back or break successful attendance record
            Log::error('Gagal mengirim email notifikasi absensi siswa: '.$e->getMessage(), [
                'user_id' => $user->id,
                'email' => $email,
                'attendance_id' => $attendance->id,
            ]);

            return false;
        }
    }

    /**
     * Send email notification when an excused or sick leave request is submitted.
     * Automatically routes:
     * - Student requests -> sent to all active Teachers
     * - Teacher requests -> sent to all active Administrators (the 3 admin accounts)
     *
     * @return int Count of emails successfully dispatched
     */
    public function sendLeaveRequestSubmittedNotification(Attendance $attendance): int
    {
        $attendance->loadMissing(['user.student', 'user.teacher']);
        $applicant = $attendance->user;

        if (! $applicant) {
            return 0;
        }

        // Route teacher submissions to administrators
        if ($applicant->role === 'teacher') {
            return $this->sendTeacherLeaveSubmittedNotificationToAdmins($attendance);
        }

        if ($applicant->role !== 'student') {
            return 0;
        }

        if (! in_array($attendance->status, ['excused', 'sick'], true)) {
            return 0;
        }

        // Policy: Prevent duplicate submission notifications
        $cacheKey = "notif_leave_submitted_{$attendance->id}";
        if (Cache::has($cacheKey)) {
            return 0;
        }

        // Get all active teachers
        $teachers = User::where('role', 'teacher')
            ->where('status', 'active')
            ->get();

        if ($teachers->isEmpty()) {
            Log::warning('Tidak ada guru aktif yang ditemukan untuk menerima notifikasi pengajuan izin/sakit siswa.', [
                'attendance_id' => $attendance->id,
                'student_id' => $applicant->id,
            ]);

            return 0;
        }

        $sentCount = 0;

        foreach ($teachers as $teacher) {
            $email = trim((string) $teacher->email);
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Log::warning('Email guru tidak valid untuk notifikasi pengajuan izin/sakit:', [
                    'teacher_id' => $teacher->id,
                    'name' => $teacher->name,
                    'email' => $email,
                ]);

                continue;
            }

            try {
                Mail::to($email)->send(new TeacherLeaveRequestSubmittedMail($attendance, $teacher));
                $sentCount++;
            } catch (Throwable $e) {
                // Failure must never cancel the student's submitted leave request
                Log::error("Gagal mengirim email notifikasi izin/sakit ke guru {$teacher->name}: ".$e->getMessage(), [
                    'teacher_id' => $teacher->id,
                    'email' => $email,
                    'attendance_id' => $attendance->id,
                ]);
            }
        }

        if ($sentCount > 0) {
            Cache::put($cacheKey, true, now()->addDay());
            Log::info("Notifikasi pengajuan izin/sakit berhasil dikirim ke {$sentCount} guru.", [
                'attendance_id' => $attendance->id,
                'student' => $applicant->name,
                'status' => $attendance->status,
            ]);
        }

        return $sentCount;
    }

    /**
     * Send the approval/rejection decision of a student leave request to every
     * active teacher so the result is archived in all teacher inboxes, matching
     * the teacher leave decision flow sent to administrators.
     *
     * @return int Count of teacher emails successfully dispatched
     */
    public function sendStudentLeaveDecisionNotificationToTeachers(Attendance $attendance): int
    {
        $attendance->loadMissing(['user.student', 'approver']);
        $student = $attendance->user;

        if (! $student || $student->role !== 'student') {
            return 0;
        }

        if (! in_array($attendance->approval_status, ['approved', 'rejected'], true)) {
            return 0;
        }

        // Policy: Prevent duplicate decision notifications for the same outcome
        $cacheKey = "notif_student_leave_decision_{$attendance->id}_{$attendance->approval_status}";
        if (Cache::has($cacheKey)) {
            return 0;
        }

        $teachers = User::where('role', 'teacher')
            ->where('status', 'active')
            ->get();

        if ($teachers->isEmpty()) {
            Log::warning('Tidak ada guru aktif yang ditemukan untuk menerima notifikasi keputusan izin siswa.', [
                'attendance_id' => $attendance->id,
                'student_id' => $student->id,
            ]);

            return 0;
        }

        $sentCount = 0;
        $sentEmails = [];

        foreach ($teachers as $teacher) {
            $email = trim((string) $teacher->email);

            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                Log::warning('Email guru tidak valid untuk notifikasi keputusan izin siswa:', [
                    'teacher_id' => $teacher->id,
                    'name' => $teacher->name,
                    'email' => $email,
                ]);

                continue;
            }

            if (in_array(strtolower($email), $sentEmails, true)) {
                continue;
            }

            try {
                Mail::to($email)->send(new TeacherStudentLeaveDecisionMail($attendance, $teacher));
                $sentEmails[] = strtolower($email);
                $sentCount++;

                Log::info("Notifikasi keputusan izin siswa berhasil dikirim ke guru [{$teacher->name}] ({$email}).", [
                    'attendance_id' => $attendance->id,
                    'student_name' => $student->name,
                    'approval_status' => $attendance->approval_status,
                ]);
            } catch (Throwable $e) {
                Log::error("Gagal mengirim email keputusan izin siswa ke guru {$teacher->name} ({$email}): ".$e->getMessage(), [
                    'teacher_id' => $teacher->id,
                    'email' => $email,
                    'attendance_id' => $attendance->id,
                ]);
            }
        }

        if ($sentCount > 0) {
            Cache::put($cacheKey, true, now()->addDay());
            Log::info("Notifikasi keputusan izin siswa berhasil dikirim ke {$sentCount} guru.", [
                'attendance_id' => $attendance->id,
                'student' => $student->name,
                'approval_status' => $attendance->approval_status,
            ]);
        }

        return $sentCount;
    }

    /**
     * Send email notification to all active Administrators (Requirement 3)
     * when a Teacher submits an excused or sick leave request.
     *
     * Recipients are resolved from ADMIN_NOTIFICATION_EMAILS when configured.
     * Otherwise every active administrator account in the database is used.
     *
     * @return int Count of admin emails successfully dispatched
     */
    public function sendTeacherLeaveSubmittedNotificationToAdmins(Attendance $attendance): int
    {
        $attendance->loadMissing(['user.teacher']);
        $teacher = $attendance->user;

        if (! $teacher || $teacher->role !== 'teacher') {
            return 0;
        }

        if (! in_array($attendance->status, ['excused', 'sick'], true)) {
            return 0;
        }

        // Policy: Prevent duplicate submission notifications to admins
        $cacheKey = "notif_teacher_leave_submitted_{$attendance->id}";
        if (Cache::has($cacheKey)) {
            return 0;
        }

        $admins = $this->resolveTeacherLeaveNotificationRecipients();

        if ($admins === []) {
            Log::warning('Tidak ada alamat email admin yang valid untuk menerima notifikasi pengajuan izin guru.', [
                'attendance_id' => $attendance->id,
                'teacher_id' => $teacher->id,
            ]);

            return 0;
        }

        $sentCount = 0;

        foreach ($admins as $admin) {
            $email = $admin['email'];

            try {
                $recipient = new User([
                    'name' => $admin['name'],
                    'email' => $email,
                    'role' => 'admin',
                    'status' => 'active',
                ]);

                Mail::to($email)->send(new AdminTeacherLeaveRequestSubmittedMail($attendance, $recipient));
                $sentCount++;

                Log::info("Notifikasi pengajuan izin guru berhasil dikirim ke admin [{$admin['name']}] ({$email}).", [
                    'attendance_id' => $attendance->id,
                    'teacher_name' => $teacher->name,
                ]);
            } catch (Throwable $e) {
                // Failure must never cancel the teacher's submitted leave request
                Log::error("Gagal mengirim email notifikasi izin guru ke admin {$admin['name']} ({$email}): ".$e->getMessage(), [
                    'email' => $email,
                    'attendance_id' => $attendance->id,
                ]);
            }
        }

        if ($sentCount > 0) {
            Cache::put($cacheKey, true, now()->addDay());
            Log::info("Notifikasi pengajuan izin guru berhasil dikirim ke {$sentCount} admin.", [
                'attendance_id' => $attendance->id,
                'teacher' => $teacher->name,
                'status' => $attendance->status,
            ]);
        }

        return $sentCount;
    }

    /**
     * Send the approval/rejection decision of a teacher leave request to every
     * administrator recipient so all admin accounts have a Gmail record.
     *
     * @return int Count of admin emails successfully dispatched
     */
    public function sendTeacherLeaveDecisionNotificationToAdmins(Attendance $attendance): int
    {
        $attendance->loadMissing(['user.teacher', 'approver']);
        $teacher = $attendance->user;

        if (! $teacher || $teacher->role !== 'teacher') {
            return 0;
        }

        if (! in_array($attendance->approval_status, ['approved', 'rejected'], true)) {
            return 0;
        }

        // Policy: Prevent duplicate decision notifications for the same outcome
        $cacheKey = "notif_teacher_leave_decision_{$attendance->id}_{$attendance->approval_status}";
        if (Cache::has($cacheKey)) {
            return 0;
        }

        $admins = $this->resolveTeacherLeaveNotificationRecipients();

        if ($admins === []) {
            Log::warning('Tidak ada alamat email admin yang valid untuk menerima notifikasi keputusan izin guru.', [
                'attendance_id' => $attendance->id,
                'teacher_id' => $teacher->id,
            ]);

            return 0;
        }

        $sentCount = 0;

        foreach ($admins as $admin) {
            $email = $admin['email'];

            try {
                $recipient = new User([
                    'name' => $admin['name'],
                    'email' => $email,
                    'role' => 'admin',
                    'status' => 'active',
                ]);

                Mail::to($email)->send(new AdminTeacherLeaveDecisionMail($attendance, $recipient));
                $sentCount++;

                Log::info("Notifikasi keputusan izin guru berhasil dikirim ke admin [{$admin['name']}] ({$email}).", [
                    'attendance_id' => $attendance->id,
                    'teacher_name' => $teacher->name,
                    'approval_status' => $attendance->approval_status,
                ]);
            } catch (Throwable $e) {
                Log::error("Gagal mengirim email keputusan izin guru ke admin {$admin['name']} ({$email}): ".$e->getMessage(), [
                    'email' => $email,
                    'attendance_id' => $attendance->id,
                ]);
            }
        }

        if ($sentCount > 0) {
            Cache::put($cacheKey, true, now()->addDay());
            Log::info("Notifikasi keputusan izin guru berhasil dikirim ke {$sentCount} admin.", [
                'attendance_id' => $attendance->id,
                'teacher' => $teacher->name,
                'approval_status' => $attendance->approval_status,
            ]);
        }

        return $sentCount;
    }

    /**
     * Resolve administrator recipients for teacher leave notifications.
     *
     * ADMIN_NOTIFICATION_EMAILS takes precedence so real Gmail inboxes can be
     * targeted even when administrator accounts use placeholder addresses.
     * When the configuration is empty, all active administrators are used.
     *
     * @return array<int, array{name: string, email: string}>
     */
    protected function resolveTeacherLeaveNotificationRecipients(): array
    {
        $configuredEmails = collect(config('attendance.admin_notification_emails', []))
            ->map(fn ($email): string => trim((string) $email))
            ->filter(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique(fn (string $email): string => strtolower($email))
            ->values();

        if ($configuredEmails->isNotEmpty()) {
            $knownAdmins = User::where('role', 'admin')
                ->get()
                ->keyBy(fn (User $admin): string => strtolower((string) $admin->email));

            return $configuredEmails->map(function (string $email) use ($knownAdmins): array {
                $admin = $knownAdmins->get(strtolower($email));

                return [
                    'name' => $admin?->name ?? 'Administrator',
                    'email' => $email,
                ];
            })->all();
        }

        return User::where('role', 'admin')
            ->where('status', 'active')
            ->get()
            ->map(fn (User $admin): array => [
                'name' => (string) $admin->name,
                'email' => trim((string) $admin->email),
            ])
            ->filter(fn (array $admin): bool => filter_var($admin['email'], FILTER_VALIDATE_EMAIL) !== false)
            ->unique('email')
            ->values()
            ->all();
    }

    /**
     * Send email notification to Applicant (Student or Teacher) when their leave request
     * has been approved or rejected.
     * Automatically routes:
     * - Student requests -> StudentLeaveRequestStatusMail
     * - Teacher requests -> TeacherLeaveRequestStatusMail (Requirement 6)
     */
    public function sendLeaveRequestStatusNotification(Attendance $attendance): bool
    {
        $attendance->loadMissing(['user.student', 'user.teacher', 'approver']);
        $applicant = $attendance->user;

        if (! $applicant) {
            return false;
        }

        if ($applicant->role === 'teacher') {
            return $this->sendTeacherLeaveStatusNotification($attendance);
        }

        if ($applicant->role !== 'student') {
            return false;
        }

        if (! in_array($attendance->approval_status, ['approved', 'rejected'], true)) {
            return false;
        }

        // Policy: Prevent duplicate status notification for the same decision
        $cacheKey = "notif_leave_status_{$attendance->id}_{$attendance->approval_status}";
        if (Cache::has($cacheKey)) {
            return true;
        }

        $email = trim((string) $applicant->email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Notifikasi status izin/sakit dilewati: email siswa tidak valid.', [
                'user_id' => $applicant->id,
                'name' => $applicant->name,
                'attendance_id' => $attendance->id,
            ]);

            return false;
        }

        try {
            Mail::to($email)->send(new StudentLeaveRequestStatusMail($attendance));
            Cache::put($cacheKey, true, now()->addDay());

            Log::info('Notifikasi status izin/sakit berhasil dikirim ke siswa.', [
                'student_id' => $applicant->id,
                'email' => $email,
                'status' => $attendance->status,
                'approval_status' => $attendance->approval_status,
                'approver' => $attendance->approver?->name,
            ]);

            return true;
        } catch (Throwable $e) {
            Log::error('Gagal mengirim email status izin/sakit ke siswa: '.$e->getMessage(), [
                'student_id' => $applicant->id,
                'email' => $email,
                'attendance_id' => $attendance->id,
            ]);

            return false;
        }
    }

    /**
     * Send email notification to Teacher when their leave request has been approved or rejected by an Admin (Requirement 6).
     * Strictly sent to the applicant Teacher's email stored in database.
     */
    public function sendTeacherLeaveStatusNotification(Attendance $attendance): bool
    {
        $attendance->loadMissing(['user.teacher', 'approver']);
        $teacher = $attendance->user;

        if (! $teacher || $teacher->role !== 'teacher') {
            return false;
        }

        if (! in_array($attendance->approval_status, ['approved', 'rejected'], true)) {
            return false;
        }

        // Policy: Prevent duplicate status notification for the same decision
        $cacheKey = "notif_teacher_leave_status_{$attendance->id}_{$attendance->approval_status}";
        if (Cache::has($cacheKey)) {
            return true;
        }

        $email = trim((string) $teacher->email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Notifikasi status izin/sakit guru dilewati: email guru di database tidak valid.', [
                'teacher_id' => $teacher->id,
                'name' => $teacher->name,
                'attendance_id' => $attendance->id,
            ]);

            return false;
        }

        try {
            Mail::to($email)->send(new TeacherLeaveRequestStatusMail($attendance));
            Cache::put($cacheKey, true, now()->addDay());

            Log::info("Notifikasi status izin/sakit guru berhasil dikirim ke guru [{$teacher->name}] ({$email}).", [
                'teacher_id' => $teacher->id,
                'email' => $email,
                'status' => $attendance->status,
                'approval_status' => $attendance->approval_status,
                'approver' => $attendance->approver?->name,
            ]);

            return true;
        } catch (Throwable $e) {
            Log::error("Gagal mengirim email status izin/sakit ke guru {$teacher->name}: ".$e->getMessage(), [
                'teacher_id' => $teacher->id,
                'email' => $email,
                'attendance_id' => $attendance->id,
            ]);

            return false;
        }
    }

    /**
     * Send attendance reminder email to a Student who has not yet recorded attendance for today.
     */
    public function sendAttendanceReminderNotification(User $student, ?string $date = null): bool
    {
        if ($student->role !== 'student' || $student->status !== 'active') {
            return false;
        }

        $targetDate = $date ?? Carbon::today()->toDateString();

        // Policy: Prevent duplicate reminder for the same student on the same date
        $cacheKey = "notif_reminder_{$student->id}_{$targetDate}";
        if (Cache::has($cacheKey)) {
            return false;
        }

        $email = trim((string) $student->email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Pengingat absensi dilewati: email siswa tidak valid.', [
                'student_id' => $student->id,
                'email' => $email,
            ]);

            return false;
        }

        try {
            Mail::to($email)->send(new StudentAttendanceReminderMail($student, $targetDate));
            Cache::put($cacheKey, true, now()->addDay());

            Log::info('Pengingat absensi berhasil dikirim ke siswa.', [
                'student_id' => $student->id,
                'email' => $email,
                'date' => $targetDate,
            ]);

            return true;
        } catch (Throwable $e) {
            Log::error('Gagal mengirim pengingat absensi ke siswa: '.$e->getMessage(), [
                'student_id' => $student->id,
                'email' => $email,
            ]);

            return false;
        }
    }
}
