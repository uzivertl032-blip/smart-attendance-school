<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_accessible_by_guest(): void
    {
        $response = $this->get(route('login'));

        $response->assertStatus(200);
        $response->assertSee('Login Administrator');
    }

    public function test_login_form_uses_https_when_request_is_forwarded_by_a_trusted_proxy(): void
    {
        $this->get(route('login'), ['X-Forwarded-Proto' => 'https'])
            ->assertSee('https://localhost/login')
            ->assertSee('https://localhost/css/smart-ui.css')
            ->assertSee('https://localhost/js/smart-ui.js')
            ->assertDontSee('http://localhost');
    }

    public function test_demo_credentials_are_only_rendered_in_the_local_environment(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');

        $this->get(route('login'))
            ->assertSee('admin@demo.com')
            ->assertSee('admin123');
        $this->get(route('teacher.login'))->assertSee('teacher123');
        $this->get(route('student.login'))->assertSee('student123');

        $this->app->detectEnvironment(fn (): string => 'production');

        $this->get(route('login'))
            ->assertDontSee('admin@demo.com')
            ->assertDontSee('admin123');
        $this->get(route('teacher.login'))->assertDontSee('teacher123');
        $this->get(route('student.login'))->assertDontSee('student123');
    }

    public function test_production_seeding_does_not_create_or_leave_demo_accounts_active(): void
    {
        $demoAccounts = [
            'admin@smartattendance.sch.id' => ['password' => 'admin123', 'role' => 'admin'],
            'admin@demo.com' => ['password' => 'admin123', 'role' => 'admin'],
            'budi.santoso@smartattendance.sch.id' => ['password' => 'teacher123', 'role' => 'teacher'],
            'ahmad.rizky@smartattendance.sch.id' => ['password' => 'student123', 'role' => 'student'],
        ];

        $this->app->detectEnvironment(fn (): string => 'production');
        config(['app.admin_bootstrap' => [
            'name' => null,
            'email' => null,
            'password' => null,
        ]]);
        $this->seed(\Database\Seeders\AdminSeeder::class);

        foreach ($demoAccounts as $email => $credentials) {
            $this->assertDatabaseMissing('users', ['email' => $email]);
            User::factory()->create([
                'email' => $email,
                'password' => Hash::make($credentials['password']),
                'role' => $credentials['role'],
                'status' => 'active',
            ]);
        }

        $this->seed(\Database\Seeders\AdminSeeder::class);

        foreach ($demoAccounts as $email => $credentials) {
            $this->assertDatabaseHas('users', [
                'email' => $email,
                'status' => 'inactive',
            ]);
        }
    }

    public function test_production_seeding_creates_an_initial_admin_from_valid_configuration(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config(['app.admin_bootstrap' => [
            'name' => 'Production Administrator',
            'email' => 'admin@smartattendance.sch.id',
            'password' => 'a-strong-unique-password',
        ]]);

        $this->seed(\Database\Seeders\AdminSeeder::class);

        $admin = User::query()->where('email', 'admin@smartattendance.sch.id')->firstOrFail();
        $this->assertSame('admin', $admin->role);
        $this->assertSame('active', $admin->status);
        $this->assertSame(true, Hash::check('a-strong-unique-password', $admin->password));
    }

    public function test_production_seeding_creates_configured_admin_without_overwriting_another_admin(): void
    {
        $admin = User::factory()->create([
            'email' => 'existing-admin@school.test',
            'password' => Hash::make('existing-strong-password'),
            'role' => 'admin',
            'status' => 'active',
        ]);
        $this->app->detectEnvironment(fn (): string => 'production');
        config(['app.admin_bootstrap' => [
            'name' => 'New Administrator',
            'email' => 'new-admin@smartattendance.sch.id',
            'password' => 'a-new-strong-password',
        ]]);

        $this->seed(\Database\Seeders\AdminSeeder::class);

        $bootstrapAdmin = User::query()->where('email', 'new-admin@smartattendance.sch.id')->firstOrFail();
        $this->assertSame('admin', $bootstrapAdmin->role);
        $this->assertSame('active', $bootstrapAdmin->status);
        $this->assertSame(true, Hash::check('a-new-strong-password', $bootstrapAdmin->password));
        $this->assertSame(true, Hash::check('existing-strong-password', $admin->fresh()->password));
    }

    public function test_production_seeding_does_not_overwrite_an_existing_active_bootstrap_admin(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@smartattendance.sch.id',
            'password' => Hash::make('existing-strong-password'),
            'role' => 'admin',
            'status' => 'active',
        ]);
        $this->app->detectEnvironment(fn (): string => 'production');
        config(['app.admin_bootstrap' => [
            'name' => 'Production Administrator',
            'email' => 'admin@smartattendance.sch.id',
            'password' => 'a-strong-unique-password',
        ]]);

        $this->seed(\Database\Seeders\AdminSeeder::class);

        $this->assertSame(true, Hash::check('existing-strong-password', $admin->fresh()->password));
    }

    public function test_production_seeding_allows_admin_bootstrap_email_used_by_another_role(): void
    {
        $teacher = User::factory()->create([
            'email' => 'admin@smartattendance.sch.id',
            'role' => 'teacher',
            'status' => 'active',
        ]);
        $this->app->detectEnvironment(fn (): string => 'production');
        config(['app.admin_bootstrap' => [
            'name' => 'Production Administrator',
            'email' => 'admin@smartattendance.sch.id',
            'password' => 'a-strong-unique-password',
        ]]);

        $this->seed(\Database\Seeders\AdminSeeder::class);

        $admin = User::query()
            ->where('email', 'admin@smartattendance.sch.id')
            ->where('role', 'admin')
            ->firstOrFail();
        $this->assertSame('teacher', $teacher->fresh()->role);
        $this->assertSame(true, Hash::check('a-strong-unique-password', $admin->password));
    }

    public function test_production_seeding_rejects_incomplete_admin_bootstrap_configuration(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config(['app.admin_bootstrap' => [
            'name' => 'Production Administrator',
            'email' => 'admin@smartattendance.sch.id',
            'password' => 'short',
        ]]);
        $this->expectException(\RuntimeException::class);

        $this->seed(\Database\Seeders\AdminSeeder::class);
    }

    public function test_production_seeding_updates_each_role_to_the_same_email_with_separate_passwords(): void
    {
        $admin = User::factory()->create(['email' => 'old-admin@school.test', 'role' => 'admin']);
        $teacher = User::factory()->create(['email' => 'old-teacher@school.test', 'role' => 'teacher']);
        $student = User::factory()->create(['email' => 'old-student@school.test', 'role' => 'student']);
        Teacher::create(['user_id' => $teacher->id, 'nip' => 'NIP-BOOTSTRAP-TEST']);
        Student::create([
            'user_id' => $student->id,
            'nis' => 'NIS-BOOTSTRAP-TEST',
            'class' => 'X-TEST',
        ]);
        $this->app->detectEnvironment(fn (): string => 'production');
        config(['app.admin_bootstrap' => [
            'name' => null,
            'email' => null,
            'password' => null,
        ]]);
        config(['app.role_bootstrap' => [
            'admin' => [
                'name' => 'Production Admin',
                'email' => 'shared@school.test',
                'password' => 'configured-admin-password',
            ],
            'teacher' => [
                'name' => 'Production Teacher',
                'email' => 'shared@school.test',
                'password' => 'configured-teacher-password',
            ],
            'student' => [
                'name' => 'Production Student',
                'email' => 'shared@school.test',
                'password' => 'configured-student-password',
            ],
        ]]);

        $this->seed(\Database\Seeders\AdminSeeder::class);

        $this->assertDatabaseCount('users', 3);
        foreach ([
            [$admin, 'admin', 'configured-admin-password'],
            [$teacher, 'teacher', 'configured-teacher-password'],
            [$student, 'student', 'configured-student-password'],
        ] as [$user, $role, $password]) {
            $user->refresh();
            $this->assertSame('shared@school.test', $user->email);
            $this->assertSame($role, $user->role);
            $this->assertSame('active', $user->status);
            $this->assertSame(true, Hash::check($password, $user->password));
        }
    }

    public function test_admin_can_login_with_valid_credentials(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@school.test',
            'password' => bcrypt('secret123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $response = $this->post(route('login.post'), [
            'email' => 'admin@school.test',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_portal_authenticates_the_admin_when_roles_share_an_email(): void
    {
        User::factory()->create([
            'email' => 'shared@school.test',
            'password' => bcrypt('teacher-password'),
            'role' => 'teacher',
            'status' => 'active',
        ]);
        $admin = User::factory()->create([
            'email' => 'shared@school.test',
            'password' => bcrypt('admin-password'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $response = $this->post(route('login.post'), [
            'email' => 'shared@school.test',
            'password' => 'admin-password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_cannot_login_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'admin@school.test',
            'password' => bcrypt('secret123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $response = $this->from(route('login'))->post(route('login.post'), [
            'email' => 'admin@school.test',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_student_cannot_login_through_admin_portal(): void
    {
        User::factory()->create([
            'email' => 'student@school.test',
            'password' => bcrypt('secret123'),
            'role' => 'student',
            'status' => 'active',
        ]);

        $response = $this->from(route('login'))->post(route('login.post'), [
            'email' => 'student@school.test',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_teacher_cannot_login_through_admin_portal(): void
    {
        User::factory()->create([
            'email' => 'teacher@school.test',
            'password' => bcrypt('secret123'),
            'role' => 'teacher',
            'status' => 'active',
        ]);

        $response = $this->from(route('login'))->post(route('login.post'), [
            'email' => 'teacher@school.test',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_admin_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'inactive.admin@school.test',
            'password' => bcrypt('secret123'),
            'role' => 'admin',
            'status' => 'inactive',
        ]);

        $response = $this->from(route('login'))->post(route('login.post'), [
            'email' => 'inactive.admin@school.test',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_guest_is_redirected_when_accessing_admin_dashboard(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_student_cannot_access_admin_dashboard(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'status' => 'active',
        ]);

        $response = $this->actingAs($student)->get(route('admin.dashboard'));

        $response->assertStatus(403);
    }

    public function test_teacher_cannot_access_admin_dashboard(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'status' => 'active',
        ]);

        $response = $this->actingAs($teacher)->get(route('admin.dashboard'));

        $response->assertStatus(403);
    }

    public function test_admin_can_access_dashboard(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Ringkasan Sistem Absensi');
    }

    public function test_admin_can_logout(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
