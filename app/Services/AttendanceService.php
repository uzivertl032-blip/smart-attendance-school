<?php

namespace App\Services;

use App\Contracts\FaceAntiSpoofingInterface;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AttendanceService
{
    protected AttendanceNotificationService $notificationService;

    public function __construct(
        protected FaceVerificationService $verificationService,
        protected FaceAntiSpoofingInterface $livenessService,
        ?AttendanceNotificationService $notificationService = null
    ) {
        $this->notificationService = $notificationService ?? app(AttendanceNotificationService::class);
    }

    /**
     * Issue a single-use verification token after biometric verification succeeds.
     * Valid for 10 minutes.
     */
    public function issueVerificationToken(User $user): string
    {
        $token = Str::random(48);
        $payload = [
            'user_id' => $user->id,
            'verified_at' => Carbon::now()->timestamp,
        ];
        Cache::put('face_verified_'.$token, $payload, now()->addMinutes(10));

        return $token;
    }

    /**
     * Resolve and validate a single-use verification token.
     * Invalidates the token after consumption to prevent replay.
     */
    public function resolveVerifiedUserFromToken(string $token): ?User
    {
        $data = Cache::get('face_verified_'.$token);
        if (! $data || empty($data['user_id'])) {
            return null;
        }

        // Single-use token consumption
        Cache::forget('face_verified_'.$token);

        return User::with(['student', 'teacher'])->find($data['user_id']);
    }

    /**
     * Record attendance or leave request following full verification flow:
     * 1. Validate identity from verification token OR biometric descriptor with anti-spoofing
     * 2. If matched, verify user is active
     * 3. Check duplicate attendance or leave request for the targeted date
     * 4. For Hadir (present): compute on-time vs late based on server time and config
     * 5. For Izin (excused) / Sakit (sick): validate reason and set approval_status to pending
     *
     * @param  string|array|null  $descriptor  Biometric feature vector
     * @param  int  $facesDetected  Count of detected faces in camera frame
     * @param  mixed  $target  Optional target identifier for 1:1 matching
     * @param  Carbon|null  $customTime  Optional timestamp for testing
     * @param  array  $livenessMetadata  Anti-spoofing capture metadata
     * @param  string  $status  Requested status ('present', 'excused', 'sick')
     * @param  string|null  $reason  Reason for leave/sick request
     * @param  string|null  $attendanceDate  Optional target date (default today)
     * @param  string|null  $verificationToken  One-time token issued from verifyFace
     */
    public function recordAttendance(
        string|array|null $descriptor = null,
        int $facesDetected = 1,
        mixed $target = null,
        ?Carbon $customTime = null,
        array $livenessMetadata = [],
        string $status = 'present',
        ?string $reason = null,
        ?string $attendanceDate = null,
        ?string $verificationToken = null
    ): array {
        $user = null;
        $person = null;
        $personType = null;

        // 1. Resolve Verified Identity (Token-based OR Biometric Direct)
        if (! empty($verificationToken)) {
            $user = $this->resolveVerifiedUserFromToken($verificationToken);
            if (! $user || ! $user->isActive()) {
                return [
                    'success' => false,
                    'status' => 'INVALID_TOKEN',
                    'verification_status' => 'failed',
                    'message' => 'Token verifikasi wajah tidak valid atau sudah kedaluwarsa. Silakan lakukan pemindaian wajah ulang.',
                    'user' => null,
                    'attendance' => null,
                ];
            }
            $person = $user->student ?? $user->teacher ?? null;
            $personType = $user->role;
        } else {
            // Direct Biometric Verification Mode (Kiosk fallback & feature tests)
            // Liveness / Anti-Spoofing Check
            $liveness = $this->livenessService->verifyLiveness($livenessMetadata);

            if (! $liveness['passed']) {
                Log::warning('Attendance blocked by liveness check.', [
                    'reason' => $liveness['reason'],
                    'score' => $liveness['score'],
                    'ip' => request()->ip(),
                ]);

                return [
                    'success' => false,
                    'status' => 'LIVENESS_FAILED',
                    'verification_status' => 'failed',
                    'message' => 'Verifikasi kehadiran ditolak. '.$liveness['reason'],
                    'user' => null,
                    'attendance' => null,
                ];
            }

            // Perform Face Verification
            $verificationResult = $this->verificationService->verify($descriptor, $facesDetected, $target);

            if ($verificationResult['status'] !== FaceVerificationService::STATUS_MATCH || empty($verificationResult['user'])) {
                $message = 'Absensi gagal. '.$verificationResult['message'];

                if ($verificationResult['status'] === FaceVerificationService::STATUS_NO_FACE) {
                    $message = 'Silakan posisikan wajah di depan kamera.';
                } elseif ($verificationResult['status'] === FaceVerificationService::STATUS_MULTIPLE_FACES) {
                    $message = 'Pastikan hanya satu wajah berada di depan kamera.';
                }

                return [
                    'success' => false,
                    'status' => 'FAILED',
                    'verification_status' => $verificationResult['status'],
                    'message' => $message,
                    'user' => null,
                    'attendance' => null,
                ];
            }

            /** @var User $user */
            $user = $verificationResult['user'];
            $person = $verificationResult['person'];
            $personType = $verificationResult['person_type'];
        }

        if (! $user || ! $user->isActive()) {
            return [
                'success' => false,
                'status' => 'FAILED',
                'verification_status' => 'failed',
                'message' => 'Akun pengguna tidak aktif.',
                'user' => null,
                'attendance' => null,
            ];
        }

        $now = $customTime ? $customTime->copy() : Carbon::now();
        $date = $attendanceDate ? Carbon::parse($attendanceDate)->toDateString() : $now->toDateString();
        $time = $now->format('H:i:s');

        // 2. Prevent Double Attendance / Request on the same date
        $existing = Attendance::where('user_id', $user->id)
            ->whereDate('attendance_date', $date)
            ->first();

        if ($existing) {
            $isLeave = in_array($existing->status, ['excused', 'sick'], true);
            $actionWord = $isLeave ? 'pengajuan' : 'absensi';

            $message = $isLeave
                ? "Absensi gagal. {$user->name} sudah memiliki catatan {$actionWord} pada tanggal ".Carbon::parse($existing->attendance_date)->translatedFormat('d F Y')." ({$existing->attendance_time})."
                : "Absensi gagal. {$user->name} sudah melakukan absensi hari ini pada ".Carbon::parse($existing->attendance_date)->translatedFormat('d F Y')." ({$existing->attendance_time}).";

            return [
                'success' => false,
                'status' => 'ALREADY_ATTENDED',
                'verification_status' => 'verified',
                'message' => $message,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role,
                ],
                'attendance' => [
                    'id' => $existing->id,
                    'name' => $user->name,
                    'role' => $user->role,
                    'status' => $existing->status,
                    'status_label' => $this->getStatusLabel($existing->status),
                    'approval_status' => $existing->approval_status,
                    'approval_status_label' => $existing->getApprovalStatusLabel(),
                    'reason' => $existing->reason,
                    'date' => Carbon::parse($existing->attendance_date)->toDateString(),
                    'date_formatted' => Carbon::parse($existing->attendance_date)->translatedFormat('d F Y'),
                    'time' => $existing->attendance_time,
                    'verification_status' => $existing->verification_status,
                ],
            ];
        }

        // 3. Process Status: Hadir vs Izin / Sakit
        $finalStatus = $status;
        $approvalStatus = 'approved';
        $savedReason = null;
        $successMessage = 'Absensi berhasil dicatat!';

        if (in_array($status, ['excused', 'sick'], true)) {
            // Leave request requires valid reason
            if (empty($reason) || mb_strlen(trim($reason)) < 5) {
                return [
                    'success' => false,
                    'status' => 'VALIDATION_FAILED',
                    'verification_status' => 'verified',
                    'message' => 'Alasan pengajuan izin/sakit wajib diisi minimal 5 karakter.',
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'role' => $user->role,
                    ],
                    'attendance' => null,
                ];
            }

            $finalStatus = $status;
            $approvalStatus = 'pending';
            $savedReason = trim($reason);
            $typeLabel = $status === 'sick' ? 'sakit' : 'izin';
            $successMessage = "Pengajuan {$typeLabel} berhasil disimpan dan sedang menunggu persetujuan guru.";
        } else {
            // Present (Hadir): Compute on-time vs late based on late_after configuration
            $lateAfter = config('attendance.late_after', '07:15');
            $currentTimeH_i = $now->format('H:i');
            $finalStatus = ($currentTimeH_i > $lateAfter) ? 'late' : 'present';
            $approvalStatus = 'approved';
            $savedReason = null;
            $successMessage = ($finalStatus === 'late')
                ? 'Absensi berhasil dicatat (Terlambat)!'
                : 'Absensi berhasil dicatat (Tepat Waktu)!';
        }

        // 4. Save Attendance Record
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'attendance_date' => $date,
            'attendance_time' => $time,
            'status' => $finalStatus,
            'verification_status' => 'verified',
            'reason' => $savedReason,
            'approval_status' => $approvalStatus,
        ]);

        // 5. Send automated notifications safely (Students & Teachers only; Admin excluded)
        if (in_array($attendance->status, ['present', 'late'], true)) {
            $this->notificationService->sendAttendanceRecordedNotification($attendance);
        } elseif (in_array($attendance->status, ['excused', 'sick'], true)) {
            $this->notificationService->sendLeaveRequestSubmittedNotification($attendance);
        }

        $personCode = $person ? ($person->nis ?? $person->nip ?? '-') : '-';

        return [
            'success' => true,
            'status' => 'SUCCESS',
            'verification_status' => 'verified',
            'message' => $successMessage,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
            ],
            'attendance' => [
                'id' => $attendance->id,
                'name' => $user->name,
                'role' => $user->role,
                'person_code' => $personCode,
                'status' => $attendance->status,
                'status_label' => $this->getStatusLabel($attendance->status),
                'approval_status' => $attendance->approval_status,
                'approval_status_label' => $attendance->getApprovalStatusLabel(),
                'reason' => $attendance->reason,
                'date' => $date,
                'date_formatted' => $now->translatedFormat('d F Y'),
                'time' => $time,
                'verification_status' => $attendance->verification_status,
            ],
        ];
    }

    /**
     * Human-readable Indonesian label for attendance status.
     */
    public function getStatusLabel(string $status): string
    {
        return match ($status) {
            'present' => 'Hadir',
            'late' => 'Terlambat',
            'sick' => 'Sakit',
            'excused' => 'Izin',
            'absent' => 'Alfa',
            default => ucfirst($status),
        };
    }
}
