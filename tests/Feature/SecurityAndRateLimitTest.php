<?php

namespace Tests\Feature;

use App\Contracts\FaceRecognitionEngineInterface;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityAndRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private User $studentUser;

    private Student $student;

    private array $registeredVector;

    private string $registeredFaceDataJson;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registeredVector = array_fill(0, 128, 0.123456);
        $faceEngine = app(FaceRecognitionEngineInterface::class);
        $this->registeredFaceDataJson = $faceEngine->processRegistration($this->registeredVector);

        $this->studentUser = User::factory()->create([
            'name' => 'Siswa Security Test',
            'email' => 'security@school.test',
            'role' => 'student',
            'status' => 'active',
        ]);

        $this->student = Student::create([
            'user_id' => $this->studentUser->id,
            'nis' => '77001',
            'class' => 'XII RPL 1',
            'face_data' => $this->registeredFaceDataJson,
        ]);

        $this->actingAs($this->studentUser);
    }

    /* ─────────────────────────────────────────────────────────────────────── */
    /* RATE LIMITING */
    /* ─────────────────────────────────────────────────────────────────────── */

    /**
     * Attendance endpoints are protected by rate limiting (throttle:attendance-kiosk).
     * After exceeding the limit, the server should return HTTP 429.
     */
    public function test_attendance_verify_endpoint_returns_429_when_rate_limit_exceeded(): void
    {
        // Clear any existing rate limiter hits so the test is isolated.
        RateLimiter::clear('attendance-kiosk|'.$this->app->make('request')->ip());

        // Exhaust all 30 allowed requests
        for ($i = 0; $i < 30; $i++) {
            $this->postJson(route('attendance.verify'), [
                'descriptor' => $this->registeredVector,
                'faces_detected' => 1,
            ]);
        }

        // The 31st request should be throttled
        $response = $this->postJson(route('attendance.verify'), [
            'descriptor' => $this->registeredVector,
            'faces_detected' => 1,
        ]);

        $response->assertStatus(429);
    }

    public function test_attendance_record_endpoint_returns_429_when_rate_limit_exceeded(): void
    {
        RateLimiter::clear('attendance-kiosk|'.$this->app->make('request')->ip());

        for ($i = 0; $i < 30; $i++) {
            $this->postJson(route('attendance.record'), [
                'descriptor' => $this->registeredVector,
                'faces_detected' => 1,
            ]);
        }

        $response = $this->postJson(route('attendance.record'), [
            'descriptor' => $this->registeredVector,
            'faces_detected' => 1,
        ]);

        $response->assertStatus(429);
    }

    /* ─────────────────────────────────────────────────────────────────────── */
    /* LIVENESS CHECK — REPLAY ATTACK PREVENTION */
    /* ─────────────────────────────────────────────────────────────────────── */

    /**
     * When no liveness metadata is provided (empty array), the service falls back
     * to compatibility mode and allows the attendance through.
     */
    public function test_attendance_succeeds_without_liveness_metadata(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 12, 7, 0, 0));

        $response = $this->postJson(route('attendance.record'), [
            'descriptor' => $this->registeredVector,
            'faces_detected' => 1,
            'user_id' => $this->studentUser->id,
            // No liveness_metadata key — backward compatibility mode
        ]);

        $response->assertStatus(200)->assertJson(['success' => true, 'status' => 'SUCCESS']);
    }

    /**
     * When a fresh timestamp is provided, liveness check passes.
     */
    public function test_attendance_succeeds_with_fresh_liveness_timestamp(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 12, 7, 0, 0));

        $response = $this->postJson(route('attendance.record'), [
            'descriptor' => $this->registeredVector,
            'faces_detected' => 1,
            'user_id' => $this->studentUser->id,
            'liveness_metadata' => [
                'captured_at' => now()->toIso8601String(), // fresh timestamp
                'variance' => 0.05,
            ],
        ]);

        $response->assertStatus(200)->assertJson(['success' => true, 'status' => 'SUCCESS']);
    }

    /**
     * An expired timestamp (older than 30 seconds) should be rejected as a replay attack.
     */
    public function test_attendance_blocked_by_expired_liveness_timestamp_replay_attack(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 12, 7, 0, 0));

        $response = $this->postJson(route('attendance.record'), [
            'descriptor' => $this->registeredVector,
            'faces_detected' => 1,
            'user_id' => $this->studentUser->id,
            'liveness_metadata' => [
                // 60 seconds in the past — exceeds the 30s freshness window
                'captured_at' => now()->subSeconds(60)->toIso8601String(),
                'variance' => 0.05,
            ],
        ]);

        $response->assertStatus(200)->assertJson([
            'success' => false,
            'status' => 'LIVENESS_FAILED',
        ]);

        // Ensure no attendance record was saved
        $this->assertEquals(0, Attendance::count());
    }

    /**
     * A near-zero variance signals a static/frozen image spoof attempt and must be rejected.
     */
    public function test_attendance_blocked_by_zero_variance_static_image(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 12, 7, 0, 0));

        $response = $this->postJson(route('attendance.record'), [
            'descriptor' => $this->registeredVector,
            'faces_detected' => 1,
            'user_id' => $this->studentUser->id,
            'liveness_metadata' => [
                'captured_at' => now()->toIso8601String(),
                'variance' => 0.00001, // near-zero — static image spoof
            ],
        ]);

        $response->assertStatus(200)->assertJson([
            'success' => false,
            'status' => 'LIVENESS_FAILED',
        ]);

        $this->assertEquals(0, Attendance::count());
    }

    /* ─────────────────────────────────────────────────────────────────────── */
    /* DB-LEVEL UNIQUE CONSTRAINT */
    /* ─────────────────────────────────────────────────────────────────────── */

    /**
     * Directly inserting a duplicate (user_id, attendance_date) into the DB should throw
     * a unique constraint violation — proving the DB-level safeguard works independently
     * of the application-level check in AttendanceService.
     */
    public function test_database_unique_constraint_prevents_duplicate_attendance(): void
    {
        $date = '2026-10-12';

        Attendance::create([
            'user_id' => $this->studentUser->id,
            'attendance_date' => $date,
            'attendance_time' => '07:00:00',
            'status' => 'present',
            'verification_status' => 'verified',
        ]);

        $this->expectException(QueryException::class);

        // Second insert for the same user on the same date must fail at the DB level
        Attendance::create([
            'user_id' => $this->studentUser->id,
            'attendance_date' => $date,
            'attendance_time' => '07:30:00',
            'status' => 'late',
            'verification_status' => 'verified',
        ]);
    }
}
