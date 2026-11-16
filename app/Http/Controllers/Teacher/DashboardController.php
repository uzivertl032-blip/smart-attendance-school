<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the Teacher Portal Dashboard.
     */
    public function index(Request $request): View
    {
        $teacherUser = Auth::user();
        $today = Carbon::today()->toDateString();

        // 1. Summary Cards (strictly student requests for teachers)
        $totalStudents = Student::count();

        $studentLeaveQuery = Attendance::whereHas('user', fn ($q) => $q->where('role', 'student'))
            ->whereIn('status', ['excused', 'sick']);

        $pendingLeaves = (clone $studentLeaveQuery)
            ->where('approval_status', 'pending')
            ->count();

        $todayApprovedLeaves = (clone $studentLeaveQuery)
            ->where('approval_status', 'approved')
            ->whereDate('attendance_date', $today)
            ->count();

        $todayPresent = Attendance::whereDate('attendance_date', $today)
            ->whereIn('status', ['present', 'late'])
            ->count();

        $stats = [
            'total_students' => $totalStudents,
            'pending_leaves' => $pendingLeaves,
            'today_approved_leaves' => $todayApprovedLeaves,
            'today_present' => $todayPresent,
        ];

        // 2. Recent Student Leave Requests awaiting teacher approval
        $pendingRequests = Attendance::with([
            'user' => fn ($q) => $q->select('id', 'name', 'email', 'role'),
            'user.student' => fn ($q) => $q->select('id', 'user_id', 'nis', 'class'),
        ])
            ->whereHas('user', fn ($q) => $q->where('role', 'student'))
            ->whereIn('status', ['excused', 'sick'])
            ->where('approval_status', 'pending')
            ->latest('attendance_date')
            ->take(5)
            ->get();

        // 3. Recent today's attendances
        $recentAttendances = Attendance::with([
            'user' => fn ($q) => $q->select('id', 'name', 'email', 'role'),
            'user.student' => fn ($q) => $q->select('id', 'user_id', 'nis', 'class'),
        ])
            ->whereDate('attendance_date', $today)
            ->latest('attendance_time')
            ->take(6)
            ->get();

        return view('teacher.dashboard', compact('stats', 'pendingRequests', 'recentAttendances', 'teacherUser'));
    }
}
