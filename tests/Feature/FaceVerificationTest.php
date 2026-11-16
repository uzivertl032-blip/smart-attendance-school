<?php

namespace Tests\Feature;

use App\Contracts\FaceRecognitionEngineInterface;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaceVerificationTest extends TestCase
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

        // 128-dimensional standard feature vector
        $this->registeredVector = array_fill(0, 128, 0.123456);

        $faceEngine = app(FaceRecognitionEngineInterface::class);
        $this->registeredFaceDataJson = $faceEngine->processRegistration($this->registeredVector);

        // Student with registered face
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

        // Teacher with registered face
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
     * Test 1: Wajah terdaftar + wajah sesuai
     * Expected: Verifikasi berhasil
     */
    public function test_test1_wajah_terdaftar_dan_sesuai_verifikasi_berhasil(): void
    {
        // 1:1 Targeted Verification Mode (with target user_id)
        $response1 = $this->postJson(route('attendance.verify'), [
            'descriptor' => $this->registeredVector,
            'faces_detected' => 1,
            'user_id' => $this->studentUser->id,
        ]);

        $response1->assertStatus(200)
            ->assertJson([
                'status' => 'MATCH',
            ]);
        $this->assertStringContainsString('Verifikasi berhasil', $response1->json('message'));
        $this->assertEquals($this->studentUser->name, $response1->json('user.name'));
        $this->assertEquals('10001', $response1->json('person_code'));

        // 1:N Kiosk Recognition Mode (without target user_id)
        $response2 = $this->postJson(route('attendance.verify'), [
            'descriptor' => $this->registeredVector,
            'faces_detected' => 1,
        ]);

        $response2->assertStatus(200)
            ->assertJson([
                'status' => 'MATCH',
            ]);
        $this->assertStringContainsString('Verifikasi berhasil', $response2->json('message'));
        $this->assertNotNull($response2->json('user.name'));
    }

    /**
     * Test 2: Wajah tidak sesuai
     * Expected: Verifikasi gagal
     */
    public function test_test2_wajah_tidak_sesuai_verifikasi_gagal(): void
    {
        // Completely different 128-float vector (distance exceeds threshold)
        $differentVector = array_fill(0, 128, 0.987654);

        $response = $this->postJson(route('attendance.verify'), [
            'descriptor' => $differentVector,
            'faces_detected' => 1,
            'user_id' => $this->studentUser->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'NO_MATCH',
            ]);
        $this->assertStringContainsString('Verifikasi gagal', $response->json('message'));
    }

    /**
     * Test 3: Wajah belum terdaftar
     * Expected: Wajah tidak terdaftar
     */
    public function test_test3_wajah_belum_terdaftar(): void
    {
        // Create student without registered face_data
        $unregisteredUser = User::factory()->create([
            'name' => 'Siswa Baru',
            'email' => 'siswabaru@school.test',
            'role' => 'student',
            'status' => 'active',
        ]);
        $unregisteredStudent = Student::create([
            'user_id' => $unregisteredUser->id,
            'nis' => '10002',
            'class' => 'X RPL 2',
            'face_data' => null,
        ]);

        // Case A: 1:1 Targeted check for user with no face data
        $response1 = $this->postJson(route('attendance.verify'), [
            'descriptor' => $this->registeredVector,
            'faces_detected' => 1,
            'user_id' => $unregisteredUser->id,
        ]);

        $response1->assertStatus(200)
            ->assertJson([
                'status' => 'NOT_REGISTERED',
            ]);
        $this->assertStringContainsString('Wajah tidak terdaftar', $response1->json('message'));

        // Case B: 1:N Kiosk check when no registered face matches
        $unknownVector = array_fill(0, 128, 0.987654);
        $response2 = $this->postJson(route('attendance.verify'), [
            'descriptor' => $unknownVector,
            'faces_detected' => 1,
        ]);

        $response2->assertStatus(200);
        $this->assertStringContainsString('Wajah tidak terdaftar', $response2->json('message'));
    }

    /**
     * Test 4: Tidak ada wajah
     * Expected: Silakan posisikan wajah di depan kamera
     */
    public function test_test4_tidak_ada_wajah(): void
    {
        // 0 faces detected
        $response = $this->postJson(route('attendance.verify'), [
            'descriptor' => null,
            'faces_detected' => 0,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'NO_FACE',
            ]);
        $this->assertStringContainsString('Silakan posisikan wajah di depan kamera', $response->json('message'));
    }

    /**
     * Test 5: Lebih dari satu wajah
     * Expected: Pastikan hanya satu wajah berada di depan kamera
     */
    public function test_test5_lebih_dari_satu_wajah(): void
    {
        $response = $this->postJson(route('attendance.verify'), [
            'descriptor' => $this->registeredVector,
            'faces_detected' => 2,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'MULTIPLE_FACES',
            ]);
        $this->assertStringContainsString('Pastikan hanya satu wajah berada di depan kamera', $response->json('message'));
    }

    /**
     * Security Test: Data biometrik wajah tidak pernah diexpose dalam JSON response.
     */
    public function test_biometric_face_data_is_never_exposed_in_api_response(): void
    {
        $response = $this->postJson(route('attendance.verify'), [
            'descriptor' => $this->registeredVector,
            'faces_detected' => 1,
            'user_id' => $this->studentUser->id,
        ]);

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('face_data', $response->json());
        $this->assertArrayNotHasKey('vector', $response->json());
        $this->assertStringNotContainsString('BrowserLandmarkVector', $response->getContent());
    }

    /**
     * Inactive User Test: Akun non-aktif tidak dapat diverifikasi.
     */
    public function test_inactive_user_face_is_rejected(): void
    {
        $inactiveStudentUser = User::factory()->create([
            'name' => 'Siswa Non-Aktif',
            'email' => 'inactive@school.test',
            'role' => 'student',
            'status' => 'inactive',
        ]);

        Student::create([
            'user_id' => $inactiveStudentUser->id,
            'nis' => '99999',
            'class' => 'XII RPL 1',
            'face_data' => $this->registeredFaceDataJson,
        ]);

        $response = $this->actingAs($this->teacherUser)->postJson(route('attendance.verify'), [
            'descriptor' => $this->registeredVector,
            'faces_detected' => 1,
            'user_id' => $inactiveStudentUser->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'NOT_REGISTERED',
            ]);
        $this->assertStringContainsString('Wajah tidak terdaftar', $response->json('message'));
    }
}
