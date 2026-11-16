<?php

namespace Tests\Feature;

use App\Contracts\FaceRecognitionEngineInterface;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaceRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $studentUser;

    private Student $student;

    private User $teacherUser;

    private Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->studentUser = User::factory()->create([
            'name' => 'Siswa Biometrik',
            'email' => 'siswambio@school.test',
            'role' => 'student',
            'status' => 'active',
        ]);

        $this->student = Student::create([
            'user_id' => $this->studentUser->id,
            'nis' => '99001',
            'class' => 'XII RPL 1',
        ]);

        $this->teacherUser = User::factory()->create([
            'name' => 'Guru Biometrik',
            'email' => 'gurubio@school.test',
            'role' => 'teacher',
            'status' => 'active',
        ]);

        $this->teacher = Teacher::create([
            'user_id' => $this->teacherUser->id,
            'nip' => '199001012020011001',
        ]);
    }

    public function test_guest_cannot_access_face_registration(): void
    {
        $this->get(route('admin.students.face-register', $this->student))
            ->assertRedirect(route('login'));

        $this->get(route('admin.teachers.face-register', $this->teacher))
            ->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_access_face_registration(): void
    {
        $this->actingAs($this->studentUser)
            ->get(route('admin.students.face-register', $this->student))
            ->assertStatus(403);

        $this->actingAs($this->teacherUser)
            ->get(route('admin.teachers.face-register', $this->teacher))
            ->assertStatus(403);
    }

    public function test_admin_can_view_student_face_registration_page(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.students.face-register', $this->student));

        $response->assertStatus(200);
        $response->assertSee('Registrasi Wajah');
        $response->assertSee('Siswa Biometrik');
    }

    public function test_admin_can_view_teacher_face_registration_page(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.teachers.face-register', $this->teacher));

        $response->assertStatus(200);
        $response->assertSee('Registrasi Wajah');
        $response->assertSee('Guru Biometrik');
    }

    public function test_admin_can_store_student_face_data(): void
    {
        // Sample 128-float descriptor payload
        $dummyDescriptor = array_fill(0, 128, 0.123456);
        $payload = json_encode([
            'engine' => 'BrowserLandmarkVector_v1',
            'descriptor' => $dummyDescriptor,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.students.face-register.store', $this->student), [
                'face_data' => $payload,
            ]);

        $response->assertRedirect(route('admin.students.show', $this->student));
        $response->assertSessionHas('success');

        $this->student->refresh();
        $this->assertTrue($this->student->hasFaceRegistered());
        $this->assertNotNull($this->student->face_data);
    }

    public function test_admin_can_store_teacher_face_data(): void
    {
        $dummyDescriptor = array_fill(0, 128, 0.654321);
        $payload = json_encode([
            'engine' => 'BrowserLandmarkVector_v1',
            'descriptor' => $dummyDescriptor,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.teachers.face-register.store', $this->teacher), [
                'face_data' => $payload,
            ]);

        $response->assertRedirect(route('admin.teachers.show', $this->teacher));
        $response->assertSessionHas('success');

        $this->teacher->refresh();
        $this->assertTrue($this->teacher->hasFaceRegistered());
        $this->assertNotNull($this->teacher->face_data);
    }

    public function test_storing_invalid_face_data_fails_validation(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.students.face-register.store', $this->student), [
                'face_data' => 'invalid-non-json-data',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_admin_can_delete_student_and_teacher_face_data(): void
    {
        $dummyDescriptor = array_fill(0, 128, 0.5);
        $processed = app(FaceRecognitionEngineInterface::class)->processRegistration($dummyDescriptor);

        $this->student->update(['face_data' => $processed]);
        $this->teacher->update(['face_data' => $processed]);

        $this->assertTrue($this->student->hasFaceRegistered());
        $this->assertTrue($this->teacher->hasFaceRegistered());

        // Delete student face data
        $this->actingAs($this->admin)
            ->delete(route('admin.students.face-register.destroy', $this->student))
            ->assertRedirect(route('admin.students.show', $this->student));

        $this->assertFalse($this->student->fresh()->hasFaceRegistered());

        // Delete teacher face data
        $this->actingAs($this->admin)
            ->delete(route('admin.teachers.face-register.destroy', $this->teacher))
            ->assertRedirect(route('admin.teachers.show', $this->teacher));

        $this->assertFalse($this->teacher->fresh()->hasFaceRegistered());
    }

    public function test_biometric_face_data_is_hidden_from_json_serialization(): void
    {
        $dummyDescriptor = array_fill(0, 128, 0.5);
        $processed = app(FaceRecognitionEngineInterface::class)->processRegistration($dummyDescriptor);
        $this->student->update(['face_data' => $processed]);

        $arrayData = $this->student->toArray();
        $jsonData = json_encode($this->student);

        $this->assertArrayNotHasKey('face_data', $arrayData);
        $this->assertStringNotContainsString('face_data', $jsonData);
    }
}
