<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentAuthTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Login Form
    // =========================================================================

    public function test_student_login_page_is_accessible_to_guests(): void
    {
        $response = $this->get(route('student.login'));

        $response->assertStatus(200);
        $response->assertViewIs('auth.student-login');
    }

    public function test_authenticated_student_is_redirected_away_from_login_page(): void
    {
        // The 'guest' middleware redirects authenticated users to the app root '/'.
        // This is default Laravel behaviour for all guest-guarded routes.
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $response = $this->actingAs($student)->get(route('student.login'));

        $response->assertRedirect('/');
    }

    public function test_authenticated_teacher_visiting_student_login_is_redirected_away(): void
    {
        // The 'guest' middleware redirects authenticated users to the app root '/'.
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);

        $response = $this->actingAs($teacher)->get(route('student.login'));

        $response->assertRedirect('/');
    }

    // =========================================================================
    // Successful Login
    // =========================================================================

    public function test_active_student_can_login_successfully(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'status' => 'active',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post(route('student.login.post'), [
            'email' => $student->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($student);
    }

    public function test_student_portal_authenticates_the_student_when_roles_share_an_email(): void
    {
        User::factory()->create([
            'email' => 'shared@school.test',
            'password' => bcrypt('admin-password'),
            'role' => 'admin',
            'status' => 'active',
        ]);
        User::factory()->create([
            'email' => 'shared@school.test',
            'password' => bcrypt('teacher-password'),
            'role' => 'teacher',
            'status' => 'active',
        ]);
        $student = User::factory()->create([
            'email' => 'shared@school.test',
            'password' => bcrypt('student-password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        $response = $this->post(route('student.login.post'), [
            'email' => 'shared@school.test',
            'password' => 'student-password',
        ]);

        $response->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($student);
    }

    public function test_shared_email_passwords_do_not_authenticate_a_different_role(): void
    {
        User::factory()->create([
            'email' => 'shared@school.test',
            'password' => bcrypt('admin-password'),
            'role' => 'admin',
            'status' => 'active',
        ]);
        User::factory()->create([
            'email' => 'shared@school.test',
            'password' => bcrypt('teacher-password'),
            'role' => 'teacher',
            'status' => 'active',
        ]);
        User::factory()->create([
            'email' => 'shared@school.test',
            'password' => bcrypt('student-password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        $this->post(route('login.post'), [
            'email' => 'shared@school.test',
            'password' => 'teacher-password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post(route('teacher.login.post'), [
            'email' => 'shared@school.test',
            'password' => 'student-password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post(route('student.login.post'), [
            'email' => 'shared@school.test',
            'password' => 'admin-password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    // =========================================================================
    // Role Guard
    // =========================================================================

    public function test_teacher_cannot_login_via_student_portal(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'status' => 'active',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post(route('student.login.post'), [
            'email' => $teacher->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_cannot_login_via_student_portal(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post(route('student.login.post'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    // =========================================================================
    // Inactive Account
    // =========================================================================

    public function test_inactive_student_cannot_login(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'status' => 'inactive',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post(route('student.login.post'), [
            'email' => $student->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    // =========================================================================
    // Validation
    // =========================================================================

    public function test_student_login_requires_email_and_password(): void
    {
        $response = $this->post(route('student.login.post'), []);

        $response->assertSessionHasErrors(['email', 'password']);
    }

    public function test_student_login_rejects_wrong_password(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'status' => 'active',
            'password' => bcrypt('correct-password'),
        ]);

        $response = $this->post(route('student.login.post'), [
            'email' => $student->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    // =========================================================================
    // Protected Routes (Middleware: auth + student)
    // =========================================================================

    public function test_student_dashboard_redirects_unauthenticated_user(): void
    {
        $response = $this->get(route('student.dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_teacher_cannot_access_student_dashboard(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);

        $response = $this->actingAs($teacher)->get(route('student.dashboard'));

        $response->assertStatus(403);
    }

    public function test_active_student_can_access_dashboard(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $response = $this->actingAs($student)->get(route('student.dashboard'));

        $response->assertStatus(200);
        $response->assertViewIs('student.dashboard');
    }

    public function test_active_student_can_access_attendance_history(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $response = $this->actingAs($student)->get(route('student.history'));

        $response->assertStatus(200);
        $response->assertViewIs('student.history');
    }

    public function test_active_student_can_access_leave_requests_page(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $response = $this->actingAs($student)->get(route('student.leave-requests.index'));

        $response->assertStatus(200);
    }

    // =========================================================================
    // Logout — redirect to role-specific login portal
    // =========================================================================

    public function test_student_logout_redirects_to_student_login(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $response = $this->actingAs($student)->post(route('logout'));

        $response->assertRedirect(route('student.login'));
        $this->assertGuest();
    }

    public function test_teacher_logout_redirects_to_teacher_login(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);

        $response = $this->actingAs($teacher)->post(route('logout'));

        $response->assertRedirect(route('teacher.login'));
        $this->assertGuest();
    }

    public function test_admin_logout_redirects_to_admin_login(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($admin)->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
