<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_home_page_loads_successfully(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Smart Attendance');
        $response->assertSee(route('attendance.index'));
        $response->assertSee(route('login'));
    }

    public function test_guest_is_redirected_to_login_when_accessing_scanner(): void
    {
        $response = $this->get(route('attendance.index'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
    }

    public function test_admin_is_forbidden_from_accessing_attendance_scanner(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->get(route('attendance.index'));

        $response->assertStatus(403);
    }

    public function test_active_student_can_access_attendance_scanner(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'status' => 'active',
        ]);

        $response = $this->actingAs($student)->get(route('attendance.index'));

        $response->assertStatus(200);
        $response->assertSee('Kamera Pemindai Wajah');
        $response->assertSee('Posisikan Wajah di Sini');
    }

    public function test_active_teacher_can_access_attendance_scanner(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'status' => 'active',
        ]);

        $response = $this->actingAs($teacher)->get(route('attendance.index'));

        $response->assertStatus(200);
        $response->assertSee('Kamera Pemindai Wajah');
        $response->assertSee('Posisikan Wajah di Sini');
    }
}
