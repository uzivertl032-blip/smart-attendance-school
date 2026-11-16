<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentTeacherCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $studentUser;

    private User $teacherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->studentUser = User::factory()->create([
            'name' => 'Siswa A',
            'email' => 'siswaa@school.test',
            'role' => 'student',
            'status' => 'active',
        ]);

        $this->teacherUser = User::factory()->create([
            'name' => 'Guru A',
            'email' => 'gurua@school.test',
            'role' => 'teacher',
            'status' => 'active',
        ]);
    }

    /* -------------------------------------------------------------------------- */
    /* AUTHORIZATION TESTS */
    /* -------------------------------------------------------------------------- */

    public function test_guest_cannot_access_student_or_teacher_crud(): void
    {
        $this->get(route('admin.students.index'))->assertRedirect(route('login'));
        $this->get(route('admin.teachers.index'))->assertRedirect(route('login'));
    }

    public function test_student_cannot_access_admin_crud(): void
    {
        $this->actingAs($this->studentUser)
            ->get(route('admin.students.index'))
            ->assertStatus(403);

        $this->actingAs($this->studentUser)
            ->get(route('admin.teachers.index'))
            ->assertStatus(403);
    }

    public function test_teacher_cannot_access_admin_crud(): void
    {
        $this->actingAs($this->teacherUser)
            ->get(route('admin.students.index'))
            ->assertStatus(403);

        $this->actingAs($this->teacherUser)
            ->get(route('admin.teachers.index'))
            ->assertStatus(403);
    }

    /* -------------------------------------------------------------------------- */
    /* STUDENT CRUD TESTS */
    /* -------------------------------------------------------------------------- */

    public function test_admin_can_view_students_list_and_search(): void
    {
        $student = Student::create([
            'user_id' => $this->studentUser->id,
            'nis' => '10001',
            'class' => 'XII RPL 1',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.students.index'));
        $response->assertStatus(200);
        $response->assertSee('Siswa A');
        $response->assertSee('10001');

        // Search test
        $responseSearch = $this->actingAs($this->admin)->get(route('admin.students.index', ['search' => '10001']));
        $responseSearch->assertStatus(200);
        $responseSearch->assertSee('Siswa A');

        $responseNoMatch = $this->actingAs($this->admin)->get(route('admin.students.index', ['search' => 'NonExistentNIS']));
        $responseNoMatch->assertStatus(200);
        $responseNoMatch->assertDontSee('Siswa A');
    }

    public function test_admin_can_create_student(): void
    {
        $data = [
            'name' => 'Budi Siswa',
            'email' => 'budi.siswa@school.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nis' => '10002',
            'class' => 'XI TKJ 2',
            'status' => 'active',
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.students.store'), $data);

        $response->assertRedirect(route('admin.students.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'budi.siswa@school.test',
            'role' => 'student',
        ]);
        $this->assertDatabaseHas('students', [
            'nis' => '10002',
            'class' => 'XI TKJ 2',
        ]);
    }

    public function test_create_student_validation(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.students.store'), []);

        $response->assertSessionHasErrors(['name', 'email', 'password', 'nis', 'class', 'status']);
    }

    public function test_admin_can_view_student_detail(): void
    {
        $student = Student::create([
            'user_id' => $this->studentUser->id,
            'nis' => '10001',
            'class' => 'XII RPL 1',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.students.show', $student));

        $response->assertStatus(200);
        $response->assertSee('Siswa A');
        $response->assertSee('10001');
    }

    public function test_admin_can_update_student(): void
    {
        $student = Student::create([
            'user_id' => $this->studentUser->id,
            'nis' => '10001',
            'class' => 'XII RPL 1',
        ]);

        $updateData = [
            'name' => 'Siswa A Updated',
            'email' => 'siswaa@school.test',
            'nis' => '10001-UPDATED',
            'class' => 'XII RPL 2',
            'status' => 'active',
        ];

        $response = $this->actingAs($this->admin)
            ->put(route('admin.students.update', $student), $updateData);

        $response->assertRedirect(route('admin.students.index'));
        $this->assertDatabaseHas('users', [
            'id' => $this->studentUser->id,
            'name' => 'Siswa A Updated',
        ]);
        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'nis' => '10001-UPDATED',
            'class' => 'XII RPL 2',
        ]);
    }

    public function test_admin_can_delete_student(): void
    {
        $student = Student::create([
            'user_id' => $this->studentUser->id,
            'nis' => '10001',
            'class' => 'XII RPL 1',
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.students.destroy', $student));

        $response->assertRedirect(route('admin.students.index'));
        $response->assertSessionHas('success', 'Akun siswa berhasil dihapus.');
        $this->assertDatabaseMissing('users', ['id' => $this->studentUser->id]);
        $this->assertDatabaseMissing('students', ['id' => $student->id]);
    }

    public function test_admin_can_delete_student_by_user_id(): void
    {
        $student = Student::create([
            'user_id' => $this->studentUser->id,
            'nis' => '10001',
            'class' => 'XII RPL 1',
        ]);

        // Send user_id (account ID) instead of students.id
        $response = $this->actingAs($this->admin)
            ->delete('/admin/students/'.$this->studentUser->id);

        $response->assertRedirect(route('admin.students.index'));
        $response->assertSessionHas('success', 'Akun siswa berhasil dihapus.');
        $this->assertDatabaseMissing('users', ['id' => $this->studentUser->id]);
        $this->assertDatabaseMissing('students', ['id' => $student->id]);
    }

    public function test_admin_cannot_delete_student_with_attendance_history(): void
    {
        $student = Student::create([
            'user_id' => $this->studentUser->id,
            'nis' => '10001',
            'class' => 'XII RPL 1',
        ]);

        Attendance::create([
            'user_id' => $this->studentUser->id,
            'attendance_date' => now()->toDateString(),
            'attendance_time' => '07:00:00',
            'status' => 'present',
            'verification_status' => 'verified',
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.students.destroy', $student));

        $response->assertRedirect(route('admin.students.index'));
        $response->assertSessionHas('error');
        // Ensure student and user still exist in database
        $this->assertDatabaseHas('users', ['id' => $this->studentUser->id]);
        $this->assertDatabaseHas('students', ['id' => $student->id]);
        $this->assertDatabaseHas('attendances', ['user_id' => $this->studentUser->id]);
    }

    public function test_admin_can_toggle_student_status(): void
    {
        $student = Student::create([
            'user_id' => $this->studentUser->id,
            'nis' => '10001',
            'class' => 'XII RPL 1',
        ]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.students.toggle-status', $student));

        $response->assertRedirect();
        $this->assertEquals('inactive', $this->studentUser->fresh()->status);
    }

    /* -------------------------------------------------------------------------- */
    /* TEACHER CRUD TESTS */
    /* -------------------------------------------------------------------------- */

    public function test_admin_can_view_teachers_list_and_search(): void
    {
        $teacher = Teacher::create([
            'user_id' => $this->teacherUser->id,
            'nip' => '198001012005011001',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.teachers.index'));
        $response->assertStatus(200);
        $response->assertSee('Guru A');
        $response->assertSee('198001012005011001');

        // Search test
        $responseSearch = $this->actingAs($this->admin)->get(route('admin.teachers.index', ['search' => '198001012005011001']));
        $responseSearch->assertStatus(200);
        $responseSearch->assertSee('Guru A');

        $responseNoMatch = $this->actingAs($this->admin)->get(route('admin.teachers.index', ['search' => 'NonExistentNIP']));
        $responseNoMatch->assertStatus(200);
        $responseNoMatch->assertDontSee('Guru A');
    }

    public function test_admin_can_create_teacher(): void
    {
        $data = [
            'name' => 'Siti Guru',
            'email' => 'siti.guru@school.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nip' => '198502022010022002',
            'status' => 'active',
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.teachers.store'), $data);

        $response->assertRedirect(route('admin.teachers.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'siti.guru@school.test',
            'role' => 'teacher',
        ]);
        $this->assertDatabaseHas('teachers', [
            'nip' => '198502022010022002',
        ]);
    }

    public function test_create_teacher_validation(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.teachers.store'), []);

        $response->assertSessionHasErrors(['name', 'email', 'password', 'nip', 'status']);
    }

    public function test_admin_can_view_teacher_detail(): void
    {
        $teacher = Teacher::create([
            'user_id' => $this->teacherUser->id,
            'nip' => '198001012005011001',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.teachers.show', $teacher));

        $response->assertStatus(200);
        $response->assertSee('Guru A');
        $response->assertSee('198001012005011001');
    }

    public function test_admin_can_update_teacher(): void
    {
        $teacher = Teacher::create([
            'user_id' => $this->teacherUser->id,
            'nip' => '198001012005011001',
        ]);

        $updateData = [
            'name' => 'Guru A Updated',
            'email' => 'gurua@school.test',
            'nip' => '198001012005011001-UPDATED',
            'status' => 'active',
        ];

        $response = $this->actingAs($this->admin)
            ->put(route('admin.teachers.update', $teacher), $updateData);

        $response->assertRedirect(route('admin.teachers.index'));
        $this->assertDatabaseHas('users', [
            'id' => $this->teacherUser->id,
            'name' => 'Guru A Updated',
        ]);
        $this->assertDatabaseHas('teachers', [
            'id' => $teacher->id,
            'nip' => '198001012005011001-UPDATED',
        ]);
    }

    public function test_admin_can_delete_teacher(): void
    {
        $teacher = Teacher::create([
            'user_id' => $this->teacherUser->id,
            'nip' => '198001012005011001',
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.teachers.destroy', $teacher));

        $response->assertRedirect(route('admin.teachers.index'));
        $response->assertSessionHas('success', 'Akun guru berhasil dihapus.');
        $this->assertDatabaseMissing('users', ['id' => $this->teacherUser->id]);
        $this->assertDatabaseMissing('teachers', ['id' => $teacher->id]);
    }

    public function test_admin_can_delete_teacher_by_user_id(): void
    {
        $teacher = Teacher::create([
            'user_id' => $this->teacherUser->id,
            'nip' => '198001012005011001',
        ]);

        // Send user_id (account ID) instead of teachers.id
        $response = $this->actingAs($this->admin)
            ->delete('/admin/teachers/'.$this->teacherUser->id);

        $response->assertRedirect(route('admin.teachers.index'));
        $response->assertSessionHas('success', 'Akun guru berhasil dihapus.');
        $this->assertDatabaseMissing('users', ['id' => $this->teacherUser->id]);
        $this->assertDatabaseMissing('teachers', ['id' => $teacher->id]);
    }

    public function test_admin_cannot_delete_teacher_with_attendance_history(): void
    {
        $teacher = Teacher::create([
            'user_id' => $this->teacherUser->id,
            'nip' => '198001012005011001',
        ]);

        Attendance::create([
            'user_id' => $this->teacherUser->id,
            'attendance_date' => now()->toDateString(),
            'attendance_time' => '07:00:00',
            'status' => 'present',
            'verification_status' => 'verified',
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.teachers.destroy', $teacher));

        $response->assertRedirect(route('admin.teachers.index'));
        $response->assertSessionHas('error');
        // Ensure teacher and user still exist in database
        $this->assertDatabaseHas('users', ['id' => $this->teacherUser->id]);
        $this->assertDatabaseHas('teachers', ['id' => $teacher->id]);
        $this->assertDatabaseHas('attendances', ['user_id' => $this->teacherUser->id]);
    }

    public function test_admin_can_toggle_teacher_status(): void
    {
        $teacher = Teacher::create([
            'user_id' => $this->teacherUser->id,
            'nip' => '198001012005011001',
        ]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.teachers.toggle-status', $teacher));

        $response->assertRedirect();
        $this->assertEquals('inactive', $this->teacherUser->fresh()->status);
    }
}
