<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create([
            'email' => 'primary.admin@smartattendance.sch.id',
            'status' => 'active',
        ]);
    }

    public function test_guest_cannot_access_admin_management_pages(): void
    {
        $this->get(route('admin.admins.index'))
            ->assertRedirect(route('login'));

        $this->get(route('admin.admins.create'))
            ->assertRedirect(route('login'));

        $this->post(route('admin.admins.store'), [
            'name' => 'New Admin',
            'email' => 'new.admin@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'status' => 'active',
        ])->assertRedirect(route('login'));
    }

    public function test_student_and_teacher_cannot_access_admin_management(): void
    {
        $studentUser = User::factory()->create([
            'role' => 'student',
            'status' => 'active',
        ]);
        Student::create([
            'user_id' => $studentUser->id,
            'nis' => '10101',
            'class' => 'X-A',
        ]);

        $teacherUser = User::factory()->teacher()->create([
            'status' => 'active',
        ]);
        Teacher::create([
            'user_id' => $teacherUser->id,
            'nip' => '19900101',
        ]);

        // Student attempt
        $this->actingAs($studentUser)->get(route('admin.admins.index'))
            ->assertStatus(403);
        $this->actingAs($studentUser)->get(route('admin.admins.create'))
            ->assertStatus(403);
        $this->actingAs($studentUser)->post(route('admin.admins.store'), [
            'name' => 'Hacker Admin',
            'email' => 'hacker@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'status' => 'active',
        ])->assertStatus(403);

        // Teacher attempt
        $this->actingAs($teacherUser)->get(route('admin.admins.index'))
            ->assertStatus(403);
        $this->actingAs($teacherUser)->get(route('admin.admins.create'))
            ->assertStatus(403);
        $this->actingAs($teacherUser)->post(route('admin.admins.store'), [
            'name' => 'Teacher As Admin',
            'email' => 'teachasadmin@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'status' => 'active',
        ])->assertStatus(403);
    }

    public function test_authorized_admin_can_view_admin_list(): void
    {
        $anotherAdmin = User::factory()->admin()->create([
            'name' => 'Siti Aminah',
            'email' => 'siti@smartattendance.sch.id',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.admins.index'));

        $response->assertStatus(200);
        $response->assertSee('Manajemen Akun Administrator');
        $response->assertSee('primary.admin@smartattendance.sch.id');
        $response->assertSee('Siti Aminah');
        $response->assertSee('siti@smartattendance.sch.id');
        $response->assertSee('Tambah Admin');
        $response->assertSee('+ Tambah Admin');
    }

    public function test_admin_can_search_administrators_by_name_and_email(): void
    {
        User::factory()->admin()->create([
            'name' => 'Dewi Sartika',
            'email' => 'dewi@smartattendance.sch.id',
        ]);

        User::factory()->admin()->create([
            'name' => 'Ki Hajar Dewantara',
            'email' => 'kihajar@smartattendance.sch.id',
        ]);

        // Search by name
        $response = $this->actingAs($this->admin)->get(route('admin.admins.index', ['search' => 'Dewi']));
        $response->assertStatus(200);
        $response->assertSee('Dewi Sartika');
        $response->assertDontSee('Ki Hajar Dewantara');

        // Search by email
        $response2 = $this->actingAs($this->admin)->get(route('admin.admins.index', ['search' => 'kihajar@']));
        $response2->assertStatus(200);
        $response2->assertSee('Ki Hajar Dewantara');
        $response2->assertDontSee('Dewi Sartika');
    }

    public function test_authorized_admin_can_view_create_admin_form(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.admins.create'));

        $response->assertStatus(200);
        $response->assertSee('Tambah Akun Administrator');
        $response->assertSee('Simpan Administrator');
    }

    public function test_authorized_admin_can_create_new_administrator(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.admins.store'), [
            'name' => 'Bambang Pamungkas',
            'email' => 'bambang.admin@smartattendance.sch.id',
            'password' => 'adminSecure123!',
            'password_confirmation' => 'adminSecure123!',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('admin.admins.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'name' => 'Bambang Pamungkas',
            'email' => 'bambang.admin@smartattendance.sch.id',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $createdUser = User::where('email', 'bambang.admin@smartattendance.sch.id')->first();
        $this->assertNotNull($createdUser);
        $this->assertTrue(Hash::check('adminSecure123!', $createdUser->password));
    }

    public function test_duplicate_email_is_rejected_with_clear_message(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.admins.store'), [
            'name' => 'Duplicate Attempt',
            'email' => 'primary.admin@smartattendance.sch.id', // Already exists
            'password' => 'adminSecure123!',
            'password_confirmation' => 'adminSecure123!',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertEquals(
            'Email ini sudah digunakan oleh akun lain.',
            session('errors')->first('email')
        );
    }

    public function test_invalid_input_is_rejected(): void
    {
        // 1. Missing required fields
        $response = $this->actingAs($this->admin)->post(route('admin.admins.store'), [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
            'status' => 'invalid-status',
        ]);

        $response->assertSessionHasErrors(['name', 'email', 'password', 'status']);

        // 2. Password confirmation mismatch
        $responseMismatch = $this->actingAs($this->admin)->post(route('admin.admins.store'), [
            'name' => 'Valid Name',
            'email' => 'valid@test.com',
            'password' => 'password123',
            'password_confirmation' => 'differentPassword',
            'status' => 'active',
        ]);

        $responseMismatch->assertSessionHasErrors(['password']);
    }

    public function test_newly_created_admin_can_login_and_access_admin_dashboard(): void
    {
        // 1. Create admin via store endpoint
        $this->actingAs($this->admin)->post(route('admin.admins.store'), [
            'name' => 'New Login Admin',
            'email' => 'newlogin.admin@smartattendance.sch.id',
            'password' => 'SuperPassword2026!',
            'password_confirmation' => 'SuperPassword2026!',
            'status' => 'active',
        ]);

        // 2. Logout current admin
        $this->post(route('logout'));
        $this->assertGuest();

        // 3. Attempt login with new credentials through admin portal
        $loginResponse = $this->post(route('login.post'), [
            'email' => 'newlogin.admin@smartattendance.sch.id',
            'password' => 'SuperPassword2026!',
        ]);

        $loginResponse->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated();

        // 4. Access admin dashboard
        $dashboardResponse = $this->get(route('admin.dashboard'));
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('Ringkasan Sistem Absensi');
        $dashboardResponse->assertSee('New Login Admin');
    }

    public function test_inactive_new_admin_cannot_login(): void
    {
        $this->actingAs($this->admin)->post(route('admin.admins.store'), [
            'name' => 'Inactive Admin',
            'email' => 'inactive.admin@smartattendance.sch.id',
            'password' => 'SuperPassword2026!',
            'password_confirmation' => 'SuperPassword2026!',
            'status' => 'inactive',
        ]);

        $this->post(route('logout'));

        $loginResponse = $this->post(route('login.post'), [
            'email' => 'inactive.admin@smartattendance.sch.id',
            'password' => 'SuperPassword2026!',
        ]);

        $loginResponse->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_can_toggle_status_of_other_admin(): void
    {
        $targetAdmin = User::factory()->admin()->create([
            'status' => 'active',
        ]);

        // Toggle to inactive
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.admins.toggle-status', $targetAdmin));

        $response->assertSessionHas('success');
        $this->assertEquals('inactive', $targetAdmin->fresh()->status);

        // Toggle back to active
        $response2 = $this->actingAs($this->admin)
            ->patch(route('admin.admins.toggle-status', $targetAdmin));

        $response2->assertSessionHas('success');
        $this->assertEquals('active', $targetAdmin->fresh()->status);
    }

    public function test_admin_cannot_toggle_own_active_status(): void
    {
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.admins.toggle-status', $this->admin));

        $response->assertSessionHas('error');
        $this->assertEquals('active', $this->admin->fresh()->status);
    }

    public function test_existing_admin_accounts_remain_intact_and_functional(): void
    {
        $existingAdmin = User::factory()->admin()->create([
            'email' => 'old.admin@smartattendance.sch.id',
            'password' => bcrypt('oldPassword123'),
            'status' => 'active',
        ]);

        $response = $this->post(route('login.post'), [
            'email' => 'old.admin@smartattendance.sch.id',
            'password' => 'oldPassword123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($existingAdmin);
    }
}
