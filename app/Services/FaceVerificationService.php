<?php

namespace App\Services;

use App\Contracts\FaceRecognitionEngineInterface;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;

class FaceVerificationService
{
    /**
     * Standard status constant definitions.
     */
    public const STATUS_MATCH = 'MATCH';

    public const STATUS_NO_MATCH = 'NO_MATCH';

    public const STATUS_NOT_REGISTERED = 'NOT_REGISTERED';

    public const STATUS_NO_FACE = 'NO_FACE';

    public const STATUS_MULTIPLE_FACES = 'MULTIPLE_FACES';

    public const STATUS_VERIFICATION_ERROR = 'VERIFICATION_ERROR';

    public function __construct(
        protected FaceRecognitionEngineInterface $engine
    ) {}

    /**
     * Perform face verification for a candidate face input against registered database biometrics.
     * Supports both 1:1 verification (targeted) and 1:N recognition (kiosk scan).
     *
     * @param  int  $facesDetected  Count of detected faces in the camera frame
     * @param  mixed  $target  Optional target user/student/teacher for 1:1 verification
     * @return array{
     *     status: string,
     *     message: string,
     *     user: User|null,
     *     person: Student|Teacher|null,
     *     person_type: string|null,
     *     confidence: float,
     *     distance: float
     * }
     */
    public function verify(string|array|null $inputDescriptor, int $facesDetected = 1, mixed $target = null): array
    {
        // 1. Check for NO_FACE condition
        if ($facesDetected === 0 || empty($inputDescriptor)) {
            return [
                'status' => self::STATUS_NO_FACE,
                'message' => 'Silakan posisikan wajah di depan kamera.',
                'user' => null,
                'person' => null,
                'person_type' => null,
                'confidence' => 0.0,
                'distance' => 999.0,
            ];
        }

        // 2. Check for MULTIPLE_FACES condition
        $maxAllowed = config('attendance.max_faces_allowed', 1);
        if ($facesDetected > $maxAllowed) {
            return [
                'status' => self::STATUS_MULTIPLE_FACES,
                'message' => 'Pastikan hanya satu wajah berada di depan kamera.',
                'user' => null,
                'person' => null,
                'person_type' => null,
                'confidence' => 0.0,
                'distance' => 999.0,
            ];
        }

        // 3. Validate Descriptor Structural Integrity
        if (! $this->engine->validateDescriptor($inputDescriptor)) {
            return [
                'status' => self::STATUS_VERIFICATION_ERROR,
                'message' => 'Terjadi kesalahan pada sistem verifikasi. Format biometrik tidak valid.',
                'user' => null,
                'person' => null,
                'person_type' => null,
                'confidence' => 0.0,
                'distance' => 999.0,
            ];
        }

        try {
            $threshold = config('attendance.face_match_threshold', 0.55);

            // 4. Targeted 1:1 Verification Mode
            if ($target !== null) {
                $person = $this->resolveTarget($target);

                if (! $person || ! $person->user || $person->user->status !== 'active') {
                    return [
                        'status' => self::STATUS_NOT_REGISTERED,
                        'message' => 'Wajah tidak terdaftar.',
                        'user' => $person?->user,
                        'person' => $person,
                        'person_type' => $person instanceof Student ? 'student' : ($person instanceof Teacher ? 'teacher' : null),
                        'confidence' => 0.0,
                        'distance' => 999.0,
                    ];
                }

                if (empty($person->face_data)) {
                    return [
                        'status' => self::STATUS_NOT_REGISTERED,
                        'message' => 'Wajah tidak terdaftar.',
                        'user' => $person->user,
                        'person' => $person,
                        'person_type' => $person instanceof Student ? 'student' : 'teacher',
                        'confidence' => 0.0,
                        'distance' => 999.0,
                    ];
                }

                $comparison = $this->engine->compareDescriptors($person->face_data, $inputDescriptor, $threshold);

                if ($comparison['matched']) {
                    return [
                        'status' => self::STATUS_MATCH,
                        'message' => "Verifikasi berhasil. Identitas terverifikasi: {$person->user->name}",
                        'user' => $person->user,
                        'person' => $person,
                        'person_type' => $person instanceof Student ? 'student' : 'teacher',
                        'confidence' => $comparison['confidence'],
                        'distance' => $comparison['distance'],
                    ];
                }

                return [
                    'status' => self::STATUS_NO_MATCH,
                    'message' => 'Verifikasi gagal. Wajah tidak sesuai.',
                    'user' => $person->user,
                    'person' => $person,
                    'person_type' => $person instanceof Student ? 'student' : 'teacher',
                    'confidence' => $comparison['confidence'],
                    'distance' => $comparison['distance'],
                ];
            }

            // 5. 1:N Identification Mode (Kiosk Scanner)
            $bestMatch = null;
            $bestDistance = 999.0;
            $bestConfidence = 0.0;
            $matchedPerson = null;
            $personType = null;

            // Search registered active Students with face_data
            $students = Student::with('user')
                ->whereNotNull('face_data')
                ->whereHas('user', fn ($q) => $q->where('status', 'active'))
                ->get();

            // Search registered active Teachers with face_data
            $teachers = Teacher::with('user')
                ->whereNotNull('face_data')
                ->whereHas('user', fn ($q) => $q->where('status', 'active'))
                ->get();

            if ($students->isEmpty() && $teachers->isEmpty()) {
                return [
                    'status' => self::STATUS_NOT_REGISTERED,
                    'message' => 'Wajah tidak terdaftar.',
                    'user' => null,
                    'person' => null,
                    'person_type' => null,
                    'confidence' => 0.0,
                    'distance' => 999.0,
                ];
            }

            foreach ($students as $student) {
                $comparison = $this->engine->compareDescriptors($student->face_data, $inputDescriptor, $threshold);

                if ($comparison['distance'] < $bestDistance) {
                    $bestDistance = $comparison['distance'];
                    $bestConfidence = $comparison['confidence'];

                    if ($comparison['matched']) {
                        $bestMatch = $student->user;
                        $matchedPerson = $student;
                        $personType = 'student';
                    }
                }
            }

            foreach ($teachers as $teacher) {
                $comparison = $this->engine->compareDescriptors($teacher->face_data, $inputDescriptor, $threshold);

                if ($comparison['distance'] < $bestDistance) {
                    $bestDistance = $comparison['distance'];
                    $bestConfidence = $comparison['confidence'];

                    if ($comparison['matched']) {
                        $bestMatch = $teacher->user;
                        $matchedPerson = $teacher;
                        $personType = 'teacher';
                    }
                }
            }

            // Return MATCH or NOT_REGISTERED / NO_MATCH
            if ($bestMatch && $matchedPerson) {
                return [
                    'status' => self::STATUS_MATCH,
                    'message' => "Verifikasi berhasil. Identitas terverifikasi: {$bestMatch->name}",
                    'user' => $bestMatch,
                    'person' => $matchedPerson,
                    'person_type' => $personType,
                    'confidence' => $bestConfidence,
                    'distance' => $bestDistance,
                ];
            }

            return [
                'status' => self::STATUS_NOT_REGISTERED,
                'message' => 'Wajah tidak terdaftar. Verifikasi gagal.',
                'user' => null,
                'person' => null,
                'person_type' => null,
                'confidence' => $bestConfidence,
                'distance' => $bestDistance,
            ];

        } catch (\Throwable $e) {
            return [
                'status' => self::STATUS_VERIFICATION_ERROR,
                'message' => 'Terjadi kesalahan sistem saat memproses verifikasi wajah.',
                'user' => null,
                'person' => null,
                'person_type' => null,
                'confidence' => 0.0,
                'distance' => 999.0,
            ];
        }
    }

    /**
     * Resolve target student or teacher record for 1:1 verification.
     */
    protected function resolveTarget(mixed $target): Student|Teacher|null
    {
        if (! $target) {
            return null;
        }

        if ($target instanceof Student || $target instanceof Teacher) {
            return $target;
        }

        if ($target instanceof User) {
            return $target->student ?? $target->teacher ?? null;
        }

        if (is_numeric($target)) {
            $user = User::with(['student', 'teacher'])->find($target);
            if ($user) {
                return $user->student ?? $user->teacher ?? null;
            }

            return Student::find($target) ?? Teacher::find($target) ?? null;
        }

        if (is_string($target)) {
            return Student::with('user')->where('nis', $target)->first()
                ?? Teacher::with('user')->where('nip', $target)->first()
                ?? (User::with(['student', 'teacher'])->where('email', $target)->first()?->student
                    ?? User::with(['student', 'teacher'])->where('email', $target)->first()?->teacher);
        }

        return null;
    }
}
