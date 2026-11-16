<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Display the main landing page / welcome entry point for Smart Attendance School.
     */
    public function index(): View
    {
        $today = Carbon::today()->toDateString();

        $openingTime = config('attendance.opening_time', '06:30');
        $lateAfter = config('attendance.late_after', '07:15');

        $totalPresentToday = 0;
        $totalLateToday = 0;

        try {
            $totalPresentToday = Attendance::whereDate('attendance_date', $today)
                ->where('status', 'present')
                ->count();

            $totalLateToday = Attendance::whereDate('attendance_date', $today)
                ->where('status', 'late')
                ->count();
        } catch (\Throwable $e) {
            // Graceful fallback if attendances table is unavailable
        }

        return view('home', compact(
            'openingTime',
            'lateAfter',
            'totalPresentToday',
            'totalLateToday'
        ));
    }
}
