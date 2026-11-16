<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Services\AttendanceNotificationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MyLeaveRequestController extends Controller
{
    /**
     * Display the teacher's own leave requests history and submission form.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $teacher = $user->teacher;

        $requests = Attendance::where('user_id', $user->id)
            ->whereIn('status', ['excused', 'sick'])
            ->with('approver')
            ->latest('attendance_date')
            ->latest('attendance_time')
            ->paginate(10);

        return view('teacher.my-leave-requests.index', compact('user', 'teacher', 'requests'));
    }

    /**
     * Show the form for creating a new teacher leave request.
     */
    public function create(Request $request): View
    {
        return $this->index($request);
    }

    /**
     * Store a new leave / sick request submitted by the teacher.
     * Sent to all 3 Administrators for review (Requirement 2 & 3).
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
            'reason.required' => 'Keterangan alasan izin/sakit wajib diisi.',
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

        // Send Gmail notifications to all 3 Administrators (Requirement 3 & 8)
        $sentCount = $notificationService->sendTeacherLeaveSubmittedNotificationToAdmins($attendance);

        $typeLabel = $validated['status'] === 'sick' ? 'sakit' : 'izin';

        $message = "Pengajuan {$typeLabel} guru berhasil dikirimkan ke Administrator.";

        if ($sentCount > 0) {
            $message .= " Notifikasi email telah terkirim ke {$sentCount} alamat admin.";
        } else {
            $message .= ' Namun notifikasi email belum terkirim. Periksa konfigurasi email admin.';
        }

        $redirect = redirect()->route('teacher.my-leave-requests.index')->with('success', $message);

        if ($sentCount === 0) {
            $redirect->with('warning', 'Pengajuan tetap tersimpan, tetapi email pemberitahuan ke admin gagal dikirim.');
        }

        return $redirect;
    }
}
