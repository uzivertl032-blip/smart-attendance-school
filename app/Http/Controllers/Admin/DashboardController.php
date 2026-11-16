<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the Admin Attendance Dashboard.
     */
    public function index(Request $request): View
    {
        $today = Carbon::today()->toDateString();

        // 1. Summary Cards
        $totalStudents = Student::count();
        $totalTeachers = Teacher::count();
        $totalActivePersonnel = User::whereIn('role', ['student', 'teacher'])
            ->where('status', 'active')
            ->count();

        $todayPresent = Attendance::whereDate('attendance_date', $today)
            ->where('status', 'present')
            ->count();

        $todayLate = Attendance::whereDate('attendance_date', $today)
            ->where('status', 'late')
            ->count();

        $todayAttendedUserCount = Attendance::whereDate('attendance_date', $today)
            ->distinct('user_id')
            ->count('user_id');

        $todayBelumAbsen = max(0, $totalActivePersonnel - $todayAttendedUserCount);

        // Pending teacher leaves awaiting Administrator approval (Requirement 1 & 2)
        $pendingTeacherLeaves = Attendance::whereHas('user', fn ($q) => $q->where('role', 'teacher'))
            ->whereIn('status', ['excused', 'sick'])
            ->where('approval_status', 'pending')
            ->count();

        $stats = [
            'total_students' => $totalStudents,
            'total_teachers' => $totalTeachers,
            'today_present' => $todayPresent,
            'today_late' => $todayLate,
            'today_belum_absen' => $todayBelumAbsen,
            'pending_teacher_leaves' => $pendingTeacherLeaves,
        ];

        // 2. Query Attendances with Eager Loading (prevent N+1 and exclude face_data)
        $query = Attendance::with([
            'user' => function ($q) {
                $q->select('id', 'name', 'email', 'role', 'status');
            },
            'user.student' => function ($q) {
                $q->select('id', 'user_id', 'nis', 'class');
            },
            'user.teacher' => function ($q) {
                $q->select('id', 'user_id', 'nip');
            },
            'approver' => function ($q) {
                $q->select('id', 'name', 'email');
            },
        ]);

        // 3. Filters
        // Filter Tanggal
        if ($request->filled('date')) {
            $query->whereDate('attendance_date', $request->date);
        }

        // Filter Role
        if ($request->filled('role')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('role', $request->role);
            });
        }

        // Filter Kelas
        if ($request->filled('class')) {
            $query->whereHas('user.student', function ($q) use ($request) {
                $q->where('class', $request->class);
            });
        }

        // Filter Status (present, late, sick, excused, absent)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // 4. Search berdasarkan Nama, NIS, NIP
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($qu) use ($search) {
                    $qu->where('name', 'like', "%{$search}%");
                })
                    ->orWhereHas('user.student', function ($qs) use ($search) {
                        $qs->where('nis', 'like', "%{$search}%");
                    })
                    ->orWhereHas('user.teacher', function ($qt) use ($search) {
                        $qt->where('nip', 'like', "%{$search}%");
                    });
            });
        }

        // 5. Pagination
        $attendances = $query->latest('attendance_date')
            ->latest('attendance_time')
            ->paginate(15)
            ->withQueryString();

        // Distinct classes for filter dropdown
        $availableClasses = Student::select('class')->distinct()->orderBy('class')->pluck('class');

        return view('admin.dashboard', compact('stats', 'attendances', 'availableClasses'));
    }

    /**
     * Show detailed attendance information.
     */
    public function showAttendance(Attendance $attendance, Request $request): JsonResponse|View
    {
        $attendance->load([
            'user' => fn ($q) => $q->select('id', 'name', 'email', 'role', 'status'),
            'user.student' => fn ($q) => $q->select('id', 'user_id', 'nis', 'class'),
            'user.teacher' => fn ($q) => $q->select('id', 'user_id', 'nip'),
            'approver' => fn ($q) => $q->select('id', 'name', 'email'),
        ]);

        $statusLabel = match ($attendance->status) {
            'present' => 'Hadir',
            'late' => 'Terlambat',
            'sick' => 'Sakit',
            'excused' => 'Izin',
            'absent' => 'Alfa',
            default => ucfirst($attendance->status),
        };

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'id' => $attendance->id,
                'user_id' => $attendance->user_id,
                'name' => $attendance->user->name ?? '-',
                'email' => $attendance->user->email ?? '-',
                'role' => $attendance->user->role ?? '-',
                'role_label' => $attendance->user?->role === 'teacher' ? 'Guru' : 'Siswa',
                'nis' => $attendance->user?->student?->nis,
                'nip' => $attendance->user?->teacher?->nip,
                'class' => $attendance->user?->student?->class ?? '-',
                'attendance_date' => Carbon::parse($attendance->attendance_date)->toDateString(),
                'attendance_date_fmt' => Carbon::parse($attendance->attendance_date)->translatedFormat('l, d F Y'),
                'attendance_time' => $attendance->attendance_time,
                'status' => $attendance->status,
                'status_label' => $statusLabel,
                'verification_status' => $attendance->verification_status,
                'reason' => $attendance->reason,
                'approval_status' => $attendance->approval_status,
                'approval_status_label' => $attendance->getApprovalStatusLabel(),
                'approver_name' => $attendance->approver->name ?? null,
                'approved_at' => $attendance->approved_at?->translatedFormat('d F Y H:i:s'),
                'rejection_note' => $attendance->rejection_note,
                'created_at' => $attendance->created_at?->translatedFormat('d F Y H:i:s'),
            ]);
        }

        return view('admin.attendances.show', compact('attendance', 'statusLabel'));
    }
}
