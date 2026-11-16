<?php

namespace Tests\Feature;

use App\Contracts\FaceRecognitionEngineInterface;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreAttendanceSystemTest extends TestCase
{
    use RefreshDatabase;

    private User $studentUser;

    private Student $student;

    private User $teacherUser;

    private Teacher $teacher;

    private array $registeredVector;

    private string $registeredFaceDataJson;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registeredVector = array_fill(0, 128, 0.123456);
        $faceEngine = app(FaceRecognitionEngineInterface::class);
        $this->registeredFaceDataJson = $faceEngine->processRegistration($this->registeredVector);

        // Active student with registered biometrics
        $this->studentUser = User::factory()->create([
            'name' => 'Ahmad Rizky Pratama',
            'email' => 'ahmad.rizky@school.test',
            'role' => 'student',
            'status' => 'active',
        ]);

        $this->student = Student::create([
            'user_id' => $this->studentUser->id,
            'nis' => '10001',
            'class' => 'XII RPL 1',
            'face_data' => $this->registeredFaceDataJson,
        ]);

        // Active teacher with registered biometrics
        $this->teacherUser = User::factory()->create([
            'name' => 'Budi Santoso, S.Kom',
            'email' => 'budi.santoso@school.test',
            'role' => 'teacher',
            'status' => 'active',
        ]);

        $this->teacher = Teacher::create([
            'user_id' => $this->teacherUser->id,
            'nip' => '198505152010011002',
            'face_data' => $this->registeredFaceDataJson,
        ]);

        $this->actingAs($this->studentUser);
    }

    /**
     * Test 1: Successful attendance recording (on time -> Hadir / present)
     */
    public function test_successful_attendance_recording_on_time(): void
    {
        // Set time to 07:05 (before late cutoff 07:15)
        Carbon::setTestNow(Carbon::create(2026, 10, 12, 7, 5, 0));

        $response = $this->postJson(route('attendance.record'), [
            'descriptor' => $this->registeredVector,
            'faces_detected' => 1,
            'user_id' => $this->studentUser->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status' => 'SUCCESS',
                'verification_status' => 'verified',
            ]);

        $data = $response->json('attendance');
        $this->assertEquals($this->studentUser->name, $data['name']);
        $this->assertEquals('present', $data['status']);
        $this->assertEquals('Hadir', $data['status_label']);
        $this->assertEquals('2026-10-12', $data['date']);
        $this->assertEquals('07:05:00', $data['time']);

        // Check database persistence
        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->studentUser->id,
            'attendance_time' => '07:05:00',
            'status' => 'present',
            'verification_status' => 'verified',
        ]);
        $saved = Attendance::where('user_id', $this->studentUser->id)->first();
        $this->assertEquals('2026-10-12', Carbon::parse($saved->attendance_date)->toDateString());
    }

    /**
     * Test 2: Successful attendance recording (late -> Terlambat / late)
     */
    public function test_successful_attendance_recording_marked_as_late(): void
    {
        // Set time to 07:30 (after late cutoff 07:15)
        Carbon::setTestNow(Carbon::create(2026, 10, 12, 7, 30, 0));

        $response = $this->postJson(route('attendance.record'), [
            'descriptor' => $this->registeredVector,
            'faces_detected' => 1,
            'user_id' => $this->studentUser->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status' => 'SUCCESS',
            ]);

        $data = $response->json('attendance');
        $this->assertEquals('late', $data['status']);
        $this->assertEquals('Terlambat', $data['status_label']);
        $this->assertEquals('07:30:00', $data['time']);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->studentUser->id,
            'status' => 'late',
        ]);
    }

    /**
     * Test 3: Prevent duplicate attendance on the same day
     */
    public function test_prevent_duplicate_attendance_on_same_day(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 12, 6, 50, 0));

        // First attendance
        $firstResponse = $this->postJson(route('attendance.record'), [
            'descriptor' => $this->registeredVector,
            'faces_detected' => 1,
            'user_id' => $this->studentUser->id,
        ]);
        $firstResponse->assertStatus(200)->assertJson(['status' => 'SUCCESS']);

        $this->assertEquals(1, Attendance::where('user_id', $this->studentUser->id)->count());

        // Second attempt on the same day
        Carbon::setTestNow(Carbon::create(2026, 10, 12, 7, 10, 0));
        $secondResponse = $this->postJson(route('attendance.record'), [
            'descriptor' => $this->registeredVector,
            'faces_detected' => 1,
            'user_id' => $this->studentUser->id,
        ]);

        $secondResponse->assertStatus(200)
            ->assertJson([
                'success' => false,
                'status' => 'ALREADY_ATTENDED',
            ]);

        $this->assertStringContainsString('sudah melakukan absensi hari ini', $secondResponse->json('message'));
        $this->assertStringContainsString('Absensi gagal', $secondResponse->json('message'));

        // Ensure database STILL only has 1 record (no duplicate)
        $this->assertEquals(1, Attendance::where('user_id', $this->studentUser->id)->count());
    }

    /**
     * Test 4: Unknown face / mismatched face fails and does not create attendance
     */
    public function test_unknown_or_mismatched_face_fails_and_creates_no_attendance(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 12, 7, 0, 0));

        $differentVector = array_fill(0, 128, 0.999999);

        $response = $this->postJson(route('attendance.record'), [
            'descriptor' => $differentVector,
            'faces_detected' => 1,
            'user_id' => $this->studentUser->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'status' => 'FAILED',
            ]);

        $this->assertStringContainsString('Absensi gagal', $response->json('message'));

        // Ensure NO attendance record was saved
        $this->assertDatabaseMissing('attendances', [
            'user_id' => $this->studentUser->id,
        ]);
        $this->assertEquals(0, Attendance::count());
    }

    /**
     * Test 5: No face detected prompts user and creates no attendance
     */
    public function test_no_face_detected_prompts_user_and_creates_no_attendance(): void
    {
        $response = $this->postJson(route('attendance.record'), [
            'descriptor' => null,
            'faces_detected' => 0,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'status' => 'FAILED',
            ]);

        $this->assertStringContainsString('Silakan posisikan wajah di depan kamera', $response->json('message'));
        $this->assertEquals(0, Attendance::count());
    }

    /**
     * Test 6: Multiple faces detected rejects and prompts user
     */
    public function test_multiple_faces_detected_rejects_and_creates_no_attendance(): void
    {
        $response = $this->postJson(route('attendance.record'), [
            'descriptor' => $this->registeredVector,
            'faces_detected' => 2,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'status' => 'FAILED',
            ]);

        $this->assertStringContainsString('Pastikan hanya satu wajah berada di depan kamera', $response->json('message'));
        $this->assertEquals(0, Attendance::count());
    }

    /**
     * Test 7: Configurable school hours (not hardcoded)
     */
    public function test_school_hours_are_dynamically_configured(): void
    {
        // Test custom late_after config set to 08:00
        config(['attendance.late_after' => '08:00']);

        Carbon::setTestNow(Carbon::create(2026, 10, 12, 7, 45, 0));

        $response = $this->postJson(route('attendance.record'), [
            'descriptor' => $this->registeredVector,
            'faces_detected' => 1,
            'user_id' => $this->studentUser->id,
        ]);

        $response->assertStatus(200);
        $this->assertEquals('present', $response->json('attendance.status'));
        $this->assertEquals('Hadir', $response->json('attendance.status_label'));
    }

    /**
     * Test 8: Attendance public kiosk page renders with camera elements and school hours
     */
    public function test_attendance_kiosk_page_renders_properly(): void
    {
        $response = $this->get(route('attendance.index'));

        $response->assertStatus(200);
        $response->assertSee('Kamera Pemindai Wajah');
        $response->assertSee('Hasil Presensi Wajah');
        $response->assertSee('Kamera Tidak Tersedia');
        $response->assertSee('Log Presensi Hari Ini');
    }
}
