<?php

namespace App\Http\Controllers;

use App\Contracts\FaceAntiSpoofingInterface;
use App\Models\Attendance;
use App\Models\Student;
use App\Services\AttendanceService;
use App\Services\FaceVerificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    /**
     * Display the initial attendance scanner / kiosk landing page.
     */
    public function index(): View
    {
        $today = Carbon::today()->toDateString();

        $todayAttendances = Attendance::with('user')
            ->whereDate('attendance_date', $today)
            ->latest('attendance_time')
            ->take(8)
            ->get();

        $totalPresentToday = Attendance::whereDate('attendance_date', $today)
            ->whereIn('status', ['present', 'late'])
            ->count();

        $openingTime = config('attendance.opening_time', '06:30');
        $lateAfter = config('attendance.late_after', '07:15');

        return view('attendance.index', compact('todayAttendances', 'totalPresentToday', 'openingTime', 'lateAfter'));
    }

    /**
     * Record attendance or leave request following complete face verification and duplicate prevention flow.
     */
    public function recordAttendance(Request $request, AttendanceService $attendanceService): JsonResponse
    {
        $descriptor = $request->input('descriptor') ?? $request->input('face_data');
        $facesDetected = (int) $request->input('faces_detected', 1);
        $target = $request->input('user_id')
            ?? $request->input('identifier')
            ?? $request->input('nis')
            ?? $request->input('nip');

        // Optional liveness metadata sent by the browser JS (captured_at timestamp, variance, etc.)
        $livenessMetadata = (array) $request->input('liveness_metadata', []);

        $status = $request->input('status', 'present');
        $reason = $request->input('reason');
        $attendanceDate = $request->input('attendance_date');
        $verificationToken = $request->input('verification_token');

        // Validate leave request payload if applying for Izin or Sakit
        if (in_array($status, ['excused', 'sick'], true)) {
            $validated = $request->validate([
                'reason' => ['required', 'string', 'min:5', 'max:500'],
                'attendance_date' => ['nullable', 'date'],
            ], [
                'reason.required' => 'Alasan izin/sakit wajib diisi.',
                'reason.min' => 'Alasan izin/sakit minimal 5 karakter.',
                'attendance_date.date' => 'Format tanggal tidak valid.',
            ]);

            $reason = $validated['reason'];
            $attendanceDate = $validated['attendance_date'] ?? null;
        }

        $result = $attendanceService->recordAttendance(
            descriptor: $descriptor,
            facesDetected: $facesDetected,
            target: $target,
            customTime: null,
            livenessMetadata: $livenessMetadata,
            status: $status,
            reason: $reason,
            attendanceDate: $attendanceDate,
            verificationToken: $verificationToken
        );

        return response()->json($result);
    }

    /**
     * Perform face verification for incoming face descriptor without recording attendance log.
     * Supports both 1:1 verification and 1:N kiosk face recognition.
     * Issues secure one-time verification token upon successful match.
     */
    public function verifyFace(
        Request $request,
        FaceVerificationService $verificationService,
        FaceAntiSpoofingInterface $livenessService,
        AttendanceService $attendanceService
    ): JsonResponse {
        $descriptor = $request->input('descriptor') ?? $request->input('face_data');
        $facesDetected = (int) $request->input('faces_detected', 1);
        $target = $request->input('user_id')
            ?? $request->input('identifier')
            ?? $request->input('nis')
            ?? $request->input('nip');

        $livenessMetadata = (array) $request->input('liveness_metadata', []);

        // Anti-spoofing check
        $liveness = $livenessService->verifyLiveness($livenessMetadata);
        if (! $liveness['passed']) {
            return response()->json([
                'status' => 'LIVENESS_FAILED',
                'message' => 'Verifikasi kehadiran ditolak. '.$liveness['reason'],
                'user' => null,
                'person_code' => null,
                'person_class' => null,
                'person_type' => null,
                'confidence' => 0.0,
                'distance' => 999.0,
                'verification_token' => null,
                'already_attended' => false,
            ]);
        }

        $result = $verificationService->verify($descriptor, $facesDetected, $target);

        $verificationToken = null;
        $alreadyAttended = false;
        $existingAttendance = null;

        if ($result['status'] === FaceVerificationService::STATUS_MATCH && ! empty($result['user'])) {
            $user = $result['user'];
            $today = Carbon::today()->toDateString();

            // Check if user already attended today
            $existing = Attendance::where('user_id', $user->id)
                ->whereDate('attendance_date', $today)
                ->first();

            if ($existing) {
                $alreadyAttended = true;
                $existingAttendance = [
                    'id' => $existing->id,
                    'status' => $existing->status,
                    'status_label' => $attendanceService->getStatusLabel($existing->status),
                    'approval_status' => $existing->approval_status,
                    'approval_status_label' => $existing->getApprovalStatusLabel(),
                    'reason' => $existing->reason,
                    'date' => Carbon::parse($existing->attendance_date)->toDateString(),
                    'date_formatted' => Carbon::parse($existing->attendance_date)->translatedFormat('d F Y'),
                    'time' => $existing->attendance_time,
                ];
            }

            // Issue secure temporary token for confirming status selection
            $verificationToken = $attendanceService->issueVerificationToken($user);
        }

        $person = $result['person'];
        $personClass = ($person instanceof Student) ? ($person->class ?? '-') : '-';
        $personCode = $person ? ($person->nis ?? $person->nip ?? '-') : null;

        return response()->json([
            'status' => $result['status'],
            'message' => $result['message'],
            'verification_token' => $verificationToken,
            'user' => $result['user'] ? [
                'id' => $result['user']->id,
                'name' => $result['user']->name,
                'role' => $result['user']->role,
                'role_label' => $result['user']->role === 'teacher' ? 'Guru' : 'Siswa',
            ] : null,
            'person_code' => $personCode,
            'person_class' => $personClass,
            'person_type' => $result['person_type'],
            'confidence' => $result['confidence'],
            'distance' => $result['distance'],
            'already_attended' => $alreadyAttended,
            'existing_attendance' => $existingAttendance,
        ]);
    }
}
