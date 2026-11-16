<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Services\AttendanceNotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TeacherLeaveRequestController extends Controller
{
    /**
     * Display listing of teacher leave (izin) and sick (sakit) requests for Administrator review (Requirement 1 & 2).
     * Any of the 3 accounts with role ADMIN can access and review.
     */
    public function index(Request $request): View
    {
        // 1. Statistics (strictly for teachers)
        $baseTeacherQuery = Attendance::whereHas('user', fn ($q) => $q->where('role', 'teacher'))
            ->whereIn('status', ['excused', 'sick']);

        $totalRequests = (clone $baseTeacherQuery)->count();
        $pendingCount = (clone $baseTeacherQuery)->where('approval_status', 'pending')->count();
        $approvedCount = (clone $baseTeacherQuery)->where('approval_status', 'approved')->count();
        $rejectedCount = (clone $baseTeacherQuery)->where('approval_status', 'rejected')->count();

        $stats = [
            'total_requests' => $totalRequests,
            'pending_count' => $pendingCount,
            'approved_count' => $approvedCount,
            'rejected_count' => $rejectedCount,
        ];

        // 2. Query Teacher Requests with Eager Loading (Requirement 1)
        $query = Attendance::with([
            'user' => fn ($q) => $q->select('id', 'name', 'email', 'role', 'status'),
            'user.teacher' => fn ($q) => $q->select('id', 'user_id', 'nip'),
            'approver' => fn ($q) => $q->select('id', 'name', 'email', 'role'),
        ])
            ->whereHas('user', fn ($q) => $q->where('role', 'teacher'))
            ->whereIn('status', ['excused', 'sick']);

        // 3. Filters
        if ($request->filled('type')) {
            $query->where('status', $request->type);
        }

        if ($request->filled('approval_status')) {
            $query->where('approval_status', $request->approval_status);
        }

        if ($request->filled('date')) {
            $query->whereDate('attendance_date', $request->date);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($qu) use ($search) {
                    $qu->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                    ->orWhereHas('user.teacher', function ($qt) use ($search) {
                        $qt->where('nip', 'like', "%{$search}%");
                    });
            });
        }

        $requests = $query->latest('attendance_date')
            ->latest('attendance_time')
            ->paginate(15)
            ->withQueryString();

        return view('admin.teacher-leave-requests.index', compact('stats', 'requests'));
    }

    /**
     * Show single teacher leave request details.
     */
    public function show(Attendance $attendance, Request $request): JsonResponse|View
    {
        if (! in_array($attendance->status, ['excused', 'sick'], true)) {
            abort(404, 'Data pengajuan izin atau sakit guru tidak ditemukan.');
        }

        // Ensure this is a teacher request
        if ($attendance->user?->role !== 'teacher') {
            abort(404, 'Data bukan merupakan pengajuan izin guru.');
        }

        $attendance->load([
            'user' => fn ($q) => $q->select('id', 'name', 'email', 'role', 'status'),
            'user.teacher' => fn ($q) => $q->select('id', 'user_id', 'nip'),
            'approver' => fn ($q) => $q->select('id', 'name', 'email', 'role'),
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'id' => $attendance->id,
                'name' => $attendance->user->name ?? '-',
                'role' => 'Guru',
                'nip' => $attendance->user?->teacher?->nip ?? '-',
                'email' => $attendance->user->email ?? '-',
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

        return view('admin.teacher-leave-requests.show', compact('attendance'));
    }

    /**
     * Approve teacher leave / sick request.
     * Strictly restricted to authenticated Administrators (Requirement 7).
     * Prevents double processing by multiple admins via lockForUpdate (Requirement 5).
     * Notifies applicant Teacher via Gmail (Requirement 6).
     */
    public function approve(Attendance $attendance, Request $request, AttendanceNotificationService $notificationService): RedirectResponse|JsonResponse
    {
        $admin = Auth::user();

        // Enforce role-based admin permission (Requirement 7)
        if (! $admin || ! $admin->isAdmin()) {
            abort(403, 'Akses ditolak. Hanya Administrator yang memiliki hak akses untuk memproses permohonan izin guru.');
        }

        if (! in_array($attendance->status, ['excused', 'sick'], true)) {
            return back()->with('error', 'Hanya pengajuan izin dan sakit guru yang dapat disetujui.');
        }

        // Prevent race condition & duplicate processing by different admins (Requirement 4 & 5)
        $processResult = DB::transaction(function () use ($attendance, $admin) {
            $locked = Attendance::where('id', $attendance->id)->lockForUpdate()->first();

            if (! $locked) {
                return ['status' => 'not_found'];
            }

            // Check if already processed
            if ($locked->approval_status !== 'pending') {
                $locked->loadMissing('approver');
                $approverName = $locked->approver?->name ?? 'Admin lain';
                $statusLabel = $locked->approval_status === 'approved' ? 'disetujui' : 'ditolak';

                return [
                    'status' => 'already_processed',
                    'message' => "Pengajuan izin guru ini sudah {$statusLabel} sebelumnya oleh {$approverName}.",
                    'current_status' => $locked->approval_status,
                    'approver' => $approverName,
                ];
            }

            $locked->update([
                'approval_status' => 'approved',
                'approved_by' => $admin->id,
                'approved_at' => Carbon::now(),
                'rejection_note' => null,
            ]);

            return [
                'status' => 'success',
                'attendance' => $locked,
            ];
        });

        if ($processResult['status'] === 'already_processed') {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $processResult['message'],
                    'approval_status' => $processResult['current_status'],
                    'approver' => $processResult['approver'],
                ], 409);
            }

            return back()->with('warning', $processResult['message']);
        }

        if ($processResult['status'] !== 'success') {
            return back()->with('error', 'Gagal memproses pengajuan izin guru.');
        }

        /** @var Attendance $updatedAttendance */
        $updatedAttendance = $processResult['attendance'];
        $updatedAttendance->loadMissing(['user.teacher', 'approver']);

        // Send Gmail notification to the teacher and all administrator recipients (Requirement 6 & 8)
        $emailSent = $notificationService->sendTeacherLeaveStatusNotification($updatedAttendance);
        $adminEmailsSent = $notificationService->sendTeacherLeaveDecisionNotificationToAdmins($updatedAttendance);

        $statusLabel = $updatedAttendance->status === 'sick' ? 'sakit' : 'izin';
        $teacherName = $updatedAttendance->user->name ?? 'Guru';
        $message = "Pengajuan {$statusLabel} atas nama Guru {$teacherName} berhasil disetujui.";
        $message .= $this->notificationSummary($emailSent, $adminEmailsSent);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'approval_status' => 'approved',
                'approver' => $admin->name,
                'email_sent' => $emailSent,
                'admin_emails_sent' => $adminEmailsSent,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Reject teacher leave / sick request with reason.
     * Strictly restricted to authenticated Administrators (Requirement 7).
     * Prevents double processing by multiple admins via lockForUpdate (Requirement 5).
     * Notifies applicant Teacher via Gmail (Requirement 6).
     */
    public function reject(Attendance $attendance, Request $request, AttendanceNotificationService $notificationService): RedirectResponse|JsonResponse
    {
        $admin = Auth::user();

        // Enforce role-based admin permission (Requirement 7)
        if (! $admin || ! $admin->isAdmin()) {
            abort(403, 'Akses ditolak. Hanya Administrator yang memiliki hak akses untuk memproses permohonan izin guru.');
        }

        if (! in_array($attendance->status, ['excused', 'sick'], true)) {
            return back()->with('error', 'Hanya pengajuan izin dan sakit guru yang dapat ditolak.');
        }

        $validated = $request->validate([
            'rejection_note' => ['nullable', 'string', 'max:500'],
        ], [
            'rejection_note.max' => 'Catatan penolakan maksimal 500 karakter.',
        ]);

        // Prevent race condition & duplicate processing by different admins (Requirement 4 & 5)
        $processResult = DB::transaction(function () use ($attendance, $admin, $validated) {
            $locked = Attendance::where('id', $attendance->id)->lockForUpdate()->first();

            if (! $locked) {
                return ['status' => 'not_found'];
            }

            // Check if already processed
            if ($locked->approval_status !== 'pending') {
                $locked->loadMissing('approver');
                $approverName = $locked->approver?->name ?? 'Admin lain';
                $statusLabel = $locked->approval_status === 'approved' ? 'disetujui' : 'ditolak';

                return [
                    'status' => 'already_processed',
                    'message' => "Pengajuan izin guru ini sudah {$statusLabel} sebelumnya oleh {$approverName}.",
                    'current_status' => $locked->approval_status,
                    'approver' => $approverName,
                ];
            }

            $locked->update([
                'approval_status' => 'rejected',
                'approved_by' => $admin->id,
                'approved_at' => Carbon::now(),
                'rejection_note' => $validated['rejection_note'] ?? null,
            ]);

            return [
                'status' => 'success',
                'attendance' => $locked,
            ];
        });

        if ($processResult['status'] === 'already_processed') {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $processResult['message'],
                    'approval_status' => $processResult['current_status'],
                    'approver' => $processResult['approver'],
                ], 409);
            }

            return back()->with('warning', $processResult['message']);
        }

        if ($processResult['status'] !== 'success') {
            return back()->with('error', 'Gagal memproses penolakan izin guru.');
        }

        /** @var Attendance $updatedAttendance */
        $updatedAttendance = $processResult['attendance'];
        $updatedAttendance->loadMissing(['user.teacher', 'approver']);

        // Send Gmail notification to the teacher and all administrator recipients (Requirement 6 & 8)
        $emailSent = $notificationService->sendTeacherLeaveStatusNotification($updatedAttendance);
        $adminEmailsSent = $notificationService->sendTeacherLeaveDecisionNotificationToAdmins($updatedAttendance);

        $statusLabel = $updatedAttendance->status === 'sick' ? 'sakit' : 'izin';
        $teacherName = $updatedAttendance->user->name ?? 'Guru';
        $message = "Pengajuan {$statusLabel} atas nama Guru {$teacherName} telah ditolak.";
        $message .= $this->notificationSummary($emailSent, $adminEmailsSent);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'approval_status' => 'rejected',
                'rejection_note' => $updatedAttendance->rejection_note,
                'approver' => $admin->name,
                'email_sent' => $emailSent,
                'admin_emails_sent' => $adminEmailsSent,
            ]);
        }

        return back()->with('info', $message);
    }

    /**
     * Build the email delivery summary shown to the administrator.
     */
    private function notificationSummary(bool $teacherEmailSent, int $adminEmailsSent): string
    {
        $recipients = [];

        if ($teacherEmailSent) {
            $recipients[] = 'guru';
        }

        if ($adminEmailsSent > 0) {
            $recipients[] = "{$adminEmailsSent} admin";
        }

        if ($recipients === []) {
            return ' Namun notifikasi email gagal dikirim. Periksa konfigurasi email.';
        }

        $summary = ' Notifikasi email terkirim ke '.implode(' dan ', $recipients).'.';

        if (! $teacherEmailSent) {
            $summary .= ' Email ke guru gagal dikirim; periksa alamat email guru.';
        }

        return $summary;
    }
}
