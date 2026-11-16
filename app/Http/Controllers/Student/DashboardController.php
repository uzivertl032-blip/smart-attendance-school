<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the Student Portal Dashboard.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $student = $user->student;
        $today = Carbon::today()->toDateString();

        // 1. Today's attendance
        $todayAttendance = Attendance::where('user_id', $user->id)
            ->whereDate('attendance_date', $today)
            ->first();

        // 2. Personal Attendance Statistics
        $totalPresent = Attendance::where('user_id', $user->id)
            ->where('status', 'present')
            ->count();

        $totalLate = Attendance::where('user_id', $user->id)
            ->where('status', 'late')
            ->count();

        $totalExcused = Attendance::where('user_id', $user->id)
            ->where('status', 'excused')
            ->where('approval_status', 'approved')
            ->count();

        $totalSick = Attendance::where('user_id', $user->id)
            ->where('status', 'sick')
            ->where('approval_status', 'approved')
            ->count();

        $totalPendingLeave = Attendance::where('user_id', $user->id)
            ->whereIn('status', ['excused', 'sick'])
            ->where('approval_status', 'pending')
            ->count();

        $allRecordsCount = $totalPresent + $totalLate + $totalExcused + $totalSick;
        $attendancePercentage = $allRecordsCount > 0
            ? round((($totalPresent + $totalLate) / $allRecordsCount) * 100, 1)
            : 100.0;

        $stats = [
            'total_present' => $totalPresent,
            'total_late' => $totalLate,
            'total_excused' => $totalExcused,
            'total_sick' => $totalSick,
            'total_pending_leave' => $totalPendingLeave,
            'attendance_percentage' => $attendancePercentage,
        ];

        // 3. Recent 7 attendance records
        $recentAttendances = Attendance::where('user_id', $user->id)
            ->latest('attendance_date')
            ->latest('attendance_time')
            ->take(7)
            ->get();

        return view('student.dashboard', compact(
            'user',
            'student',
            'todayAttendance',
            'stats',
            'recentAttendances'
        ));
    }

    /**
     * Display paginated attendance history for the student.
     */
    public function history(Request $request): View
    {
        $user = Auth::user();
        $student = $user->student;

        $query = Attendance::where('user_id', $user->id)
            ->with('approver');

        // Optional status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Optional month filter
        if ($request->filled('month')) {
            $month = Carbon::parse($request->month);
            $query->whereYear('attendance_date', $month->year)
                ->whereMonth('attendance_date', $month->month);
        }

        $attendances = $query->latest('attendance_date')
            ->latest('attendance_time')
            ->paginate(15)
            ->withQueryString();

        return view('student.history', compact('user', 'student', 'attendances'));
    }
}
