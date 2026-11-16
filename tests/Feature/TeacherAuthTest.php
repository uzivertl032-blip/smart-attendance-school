<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherAuthTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Login Form
    // =========================================================================

    public function test_teacher_login_page_is_accessible_to_guests(): void
    {
        $response = $this->get(route('teacher.login'));

        $response->assertStatus(200);
        $response->assertViewIs('auth.teacher-login');
    }

    public function test_authenticated_teacher_is_redirected_away_from_login_page(): void
    {
        // The 'guest' middleware redirects authenticated users to '/' by default.
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);

        $response = $this->actingAs($teacher)->get(route('teacher.login'));

        $response->assertRedirect('/');
    }

    // =========================================================================
    // Successful Login
    // =========================================================================

    public function test_active_teacher_can_login_successfully(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'status' => 'active',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post(route('teacher.login.post'), [
            'email' => $teacher->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('teacher.dashboard'));
        $this->assertAuthenticatedAs($teacher);
    }

    public function test_teacher_portal_authenticates_the_teacher_when_roles_share_an_email(): void
    {
        User::factory()->create([
            'email' => 'shared@school.test',
            'password' => bcrypt('admin-password'),
            'role' => 'admin',
            'status' => 'active',
        ]);
        $teacher = User::factory()->create([
            'email' => 'shared@school.test',
            'password' => bcrypt('teacher-password'),
            'role' => 'teacher',
            'status' => 'active',
        ]);

        $response = $this->post(route('teacher.login.post'), [
            'email' => 'shared@school.test',
            'password' => 'teacher-password',
        ]);

        $response->assertRedirect(route('teacher.dashboard'));
        $this->assertAuthenticatedAs($teacher);
    }

    // =========================================================================
    // Role Guard
    // =========================================================================

    public function test_student_cannot_login_via_teacher_portal(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'status' => 'active',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post(route('teacher.login.post'), [
            'email' => $student->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_cannot_login_via_teacher_portal(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post(route('teacher.login.post'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    // =========================================================================
    // Inactive Account
    // =========================================================================

    public function test_inactive_teacher_cannot_login(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'status' => 'inactive',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post(route('teacher.login.post'), [
            'email' => $teacher->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    // =========================================================================
    // Validation
    // =========================================================================

    public function test_teacher_login_requires_email_and_password(): void
    {
        $response = $this->post(route('teacher.login.post'), []);

        $response->assertSessionHasErrors(['email', 'password']);
    }

    public function test_teacher_login_rejects_wrong_password(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'status' => 'active',
            'password' => bcrypt('correct-password'),
        ]);

        $response = $this->post(route('teacher.login.post'), [
            'email' => $teacher->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    // =========================================================================
    // Protected Routes (Middleware: auth + teacher)
    // =========================================================================

    public function test_teacher_dashboard_redirects_unauthenticated_user(): void
    {
        $response = $this->get(route('teacher.dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_student_cannot_access_teacher_dashboard(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $response = $this->actingAs($student)->get(route('teacher.dashboard'));

        $response->assertStatus(403);
    }

    public function test_active_teacher_can_access_dashboard(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);

        $response = $this->actingAs($teacher)->get(route('teacher.dashboard'));

        $response->assertStatus(200);
        $response->assertViewIs('teacher.dashboard');
    }

    public function test_active_teacher_can_access_leave_requests_page(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);

        $response = $this->actingAs($teacher)->get(route('teacher.leave-requests.index'));

        $response->assertStatus(200);
    }
}
