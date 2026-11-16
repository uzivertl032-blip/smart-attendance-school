<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Services\AttendanceNotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LeaveRequestController extends Controller
{
    /**
     * Display listing of leave (izin) and sick (sakit) requests for teacher review.
     */
    /**
     * Display listing of student leave (izin) and sick (sakit) requests for teacher review.
     * Teacher leave requests are strictly excluded and directed to Administrator approval.
     */
    public function index(Request $request): View
    {
        // 1. Statistics (strictly for students)
        $studentLeaveQuery = Attendance::whereHas('user', fn ($q) => $q->where('role', 'student'))
            ->whereIn('status', ['excused', 'sick']);

        $totalRequests = (clone $studentLeaveQuery)->count();
        $pendingCount = (clone $studentLeaveQuery)->where('approval_status', 'pending')->count();
        $approvedCount = (clone $studentLeaveQuery)->where('approval_status', 'approved')->count();
        $rejectedCount = (clone $studentLeaveQuery)->where('approval_status', 'rejected')->count();

        $stats = [
            'total_requests' => $totalRequests,
            'pending_count' => $pendingCount,
            'approved_count' => $approvedCount,
            'rejected_count' => $rejectedCount,
        ];

        // 2. Query Student Requests with Eager Loading
        $query = Attendance::with([
            'user' => fn ($q) => $q->select('id', 'name', 'email', 'role', 'status'),
            'user.student' => fn ($q) => $q->select('id', 'user_id', 'nis', 'class'),
            'approver' => fn ($q) => $q->select('id', 'name', 'email'),
        ])
            ->whereHas('user', fn ($q) => $q->where('role', 'student'))
            ->whereIn('status', ['excused', 'sick']);

        // 3. Filters
        // Filter Jenis (excused / sick)
        if ($request->filled('type')) {
            $query->where('status', $request->type);
        }

        // Filter Status Persetujuan (pending, approved, rejected)
        if ($request->filled('approval_status')) {
            $query->where('approval_status', $request->approval_status);
        }

        // Filter Tanggal Presensi
        if ($request->filled('date')) {
            $query->whereDate('attendance_date', $request->date);
        }

        // Search Nama, NIS
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($qu) use ($search) {
                    $qu->where('name', 'like', "%{$search}%");
                })
                    ->orWhereHas('user.student', function ($qs) use ($search) {
                        $qs->where('nis', 'like', "%{$search}%");
                    });
            });
        }

        $requests = $query->latest('attendance_date')
            ->latest('attendance_time')
            ->paginate(15)
            ->withQueryString();

        return view('teacher.leave-requests.index', compact('stats', 'requests'));
    }

    /**
     * Show single student leave request details.
     */
    public function show(Attendance $attendance, Request $request): JsonResponse|View
    {
        if (! in_array($attendance->status, ['excused', 'sick'], true)) {
            abort(404, 'Data pengajuan izin atau sakit tidak ditemukan.');
        }

        // Ensure this is a student request
        if ($attendance->user?->role !== 'student') {
            abort(403, 'Akses ditolak. Pengajuan izin guru hanya dapat ditinjau oleh Administrator.');
        }

        $attendance->load([
            'user' => fn ($q) => $q->select('id', 'name', 'email', 'role', 'status'),
            'user.student' => fn ($q) => $q->select('id', 'user_id', 'nis', 'class'),
            'approver' => fn ($q) => $q->select('id', 'name', 'email'),
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'id' => $attendance->id,
                'name' => $attendance->user->name ?? '-',
                'role' => $attendance->user->role ?? '-',
                'role_label' => 'Siswa',
                'nis' => $attendance->user?->student?->nis,
                'class' => $attendance->user?->student?->class ?? '-',
                'status' => $attendance->status,
                'status_label' => $attendance->status === 'sick' ? 'Sakit' : 'Izin',
                'reason' => $attendance->reason,
                'approval_status' => $attendance->approval_status,
                'approval_status_label' => $attendance->getApprovalStatusLabel(),
                'approver_name' => $attendance->approver->name ?? null,
                'approved_at' => $attendance->approved_at?->translatedFormat('d F Y H:i:s'),
                'rejection_note' => $attendance->rejection_note,
                'date' => Carbon::parse($attendance->attendance_date)->translatedFormat('l, d F Y'),
                'time' => $attendance->attendance_time,
            ]);
        }

        return view('teacher.leave-requests.show', compact('attendance'));
    }

    /**
     * Approve student leave / sick request.
     * Strictly restricted to authenticated teachers for students.
     */
    public function approve(Attendance $attendance, Request $request, AttendanceNotificationService $notificationService): RedirectResponse|JsonResponse
    {
        $user = Auth::user();

        // Enforce strict teacher-only approval rule
        if (! $user || ! $user->isTeacher()) {
            abort(403, 'Akses ditolak. Wewenang ini khusus untuk Guru.');
        }

        // Ensure this is a student request (Requirement 2: Teacher leaves go to Admin)
        if ($attendance->user?->role !== 'student') {
            abort(403, 'Guru tidak memiliki wewenang menyetujui izin sesama guru. Pengajuan izin guru wajib diproses oleh Administrator.');
        }

        if (! in_array($attendance->status, ['excused', 'sick'], true)) {
            return back()->with('error', 'Hanya pengajuan izin dan sakit yang dapat disetujui.');
        }

        if ($attendance->approval_status === 'approved') {
            return back()->with('info', 'Pengajuan ini sudah berstatus disetujui sebelumnya.');
        }

        $attendance->update([
            'approval_status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => Carbon::now(),
            'rejection_note' => null,
        ]);

        // Send notification to the student and all active teachers
        $studentEmailSent = $notificationService->sendLeaveRequestStatusNotification($attendance);
        $teacherEmailsSent = $notificationService->sendStudentLeaveDecisionNotificationToTeachers($attendance);

        $statusLabel = $attendance->status === 'sick' ? 'sakit' : 'izin';
        $applicantName = $attendance->user->name ?? 'Pemohon';
        $message = "Pengajuan {$statusLabel} atas nama {$applicantName} berhasil disetujui.";
        $message .= $this->notificationSummary($studentEmailSent, $teacherEmailsSent);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'approval_status' => 'approved',
                'approver' => $user->name,
                'email_sent' => $studentEmailSent,
                'teacher_emails_sent' => $teacherEmailsSent,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Reject leave / sick request.
     * Strictly restricted to authenticated teachers.
     * Admin is strictly prohibited.
     */
    public function reject(Attendance $attendance, Request $request, AttendanceNotificationService $notificationService): RedirectResponse|JsonResponse
    {
        $user = Auth::user();

        // Enforce strict teacher-only rejection rule
        if (! $user || ! $user->isTeacher()) {
            abort(403, 'Akses ditolak. Wewenang ini khusus untuk Guru.');
        }

        // Ensure this is a student request (Requirement 2: Teacher leaves go to Admin)
        if ($attendance->user?->role !== 'student') {
            abort(403, 'Guru tidak memiliki wewenang menolak izin sesama guru. Pengajuan izin guru wajib diproses oleh Administrator.');
        }

        if (! in_array($attendance->status, ['excused', 'sick'], true)) {
            return back()->with('error', 'Hanya pengajuan izin dan sakit yang dapat ditolak.');
        }

        $validated = $request->validate([
            'rejection_note' => ['nullable', 'string', 'max:500'],
        ]);

        $attendance->update([
            'approval_status' => 'rejected',
            'approved_by' => $user->id,
            'approved_at' => Carbon::now(),
            'rejection_note' => $validated['rejection_note'] ?? null,
        ]);

        // Send notification to the student and all active teachers
        $studentEmailSent = $notificationService->sendLeaveRequestStatusNotification($attendance);
        $teacherEmailsSent = $notificationService->sendStudentLeaveDecisionNotificationToTeachers($attendance);

        $statusLabel = $attendance->status === 'sick' ? 'sakit' : 'izin';
        $applicantName = $attendance->user->name ?? 'Pemohon';
        $message = "Pengajuan {$statusLabel} atas nama {$applicantName} telah ditolak.";
        $message .= $this->notificationSummary($studentEmailSent, $teacherEmailsSent);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'approval_status' => 'rejected',
                'rejection_note' => $attendance->rejection_note,
                'approver' => $user->name,
                'email_sent' => $studentEmailSent,
                'teacher_emails_sent' => $teacherEmailsSent,
            ]);
        }

        return back()->with('info', $message);
    }

    /**
     * Build the email delivery summary shown to the teacher.
     */
    private function notificationSummary(bool $studentEmailSent, int $teacherEmailsSent): string
    {
        $recipients = [];

        if ($studentEmailSent) {
            $recipients[] = 'siswa';
        }

        if ($teacherEmailsSent > 0) {
            $recipients[] = "{$teacherEmailsSent} guru";
        }

        if ($recipients === []) {
            return ' Namun notifikasi email gagal dikirim. Periksa konfigurasi email.';
        }

        $summary = ' Notifikasi email terkirim ke '.implode(' dan ', $recipients).'.';

        if (! $studentEmailSent) {
            $summary .= ' Email ke siswa gagal dikirim; periksa alamat email siswa.';
        }

        return $summary;
    }
}
