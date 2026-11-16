<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Services\AttendanceNotificationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LeaveRequestController extends Controller
{
    /**
     * Display the student's leave requests history and submission form.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $student = $user->student;

        $requests = Attendance::where('user_id', $user->id)
            ->whereIn('status', ['excused', 'sick'])
            ->with('approver')
            ->latest('attendance_date')
            ->latest('attendance_time')
            ->paginate(10);

        return view('student.leave-requests.index', compact('user', 'student', 'requests'));
    }

    /**
     * Store a new leave / sick request submitted by the student.
     */
    public function store(Request $request, AttendanceNotificationService $notificationService): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'status' => ['required', 'in:excused,sick'],
            'attendance_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'status.required' => 'Pilih jenis permohonan (Izin atau Sakit).',
            'status.in' => 'Jenis permohonan harus berupa Izin atau Sakit.',
            'attendance_date.required' => 'Tanggal izin/sakit wajib diisi.',
            'attendance_date.date' => 'Format tanggal tidak valid.',
            'reason.required' => 'Keterangan alasan wajib diisi.',
            'reason.min' => 'Keterangan alasan minimal 5 karakter.',
            'reason.max' => 'Keterangan alasan maksimal 500 karakter.',
        ]);

        $targetDate = Carbon::parse($validated['attendance_date'])->toDateString();

        // Prevent duplicate attendance/leave entry on the same date
        $existing = Attendance::where('user_id', $user->id)
            ->whereDate('attendance_date', $targetDate)
            ->first();

        if ($existing) {
            $statusLabel = match ($existing->status) {
                'present' => 'hadir tepat waktu',
                'late' => 'hadir terlambat',
                'excused' => 'izin',
                'sick' => 'sakit',
                default => 'presensi',
            };

            return back()->withInput()->with('error', "Anda sudah memiliki catatan {$statusLabel} pada tanggal ".Carbon::parse($targetDate)->translatedFormat('d F Y').'.');
        }

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'attendance_date' => $targetDate,
            'attendance_time' => Carbon::now()->toTimeString(),
            'status' => $validated['status'],
            'verification_status' => 'verified',
            'reason' => $validated['reason'],
            'approval_status' => 'pending',
        ]);

        // Notify active Teachers regarding new leave request
        $notificationService->sendLeaveRequestSubmittedNotification($attendance);

        $typeLabel = $validated['status'] === 'sick' ? 'sakit' : 'izin';

        return redirect()->route('student.leave-requests.index')->with('success', "Pengajuan {$typeLabel} berhasil dikirim dan sedang menunggu peninjauan dari Guru.");
    }
}
