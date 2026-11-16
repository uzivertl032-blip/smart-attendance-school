<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAttendanceDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $studentUser1;

    private Student $student1;

    private User $studentUser2;

    private Student $student2;

    private User $teacherUser;

    private Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->studentUser1 = User::factory()->create([
            'name' => 'Ahmad Rizky Pratama',
            'email' => 'ahmad@school.test',
            'role' => 'student',
            'status' => 'active',
        ]);
        $this->student1 = Student::create([
            'user_id' => $this->studentUser1->id,
            'nis' => '10001',
            'class' => 'XII RPL 1',
        ]);

        $this->studentUser2 = User::factory()->create([
            'name' => 'Siti Aisyah',
            'email' => 'siti@school.test',
            'role' => 'student',
            'status' => 'active',
        ]);
        $this->student2 = Student::create([
            'user_id' => $this->studentUser2->id,
            'nis' => '10002',
            'class' => 'XI TKJ 2',
        ]);

        $this->teacherUser = User::factory()->create([
            'name' => 'Budi Santoso, M.Kom',
            'email' => 'budi@school.test',
            'role' => 'teacher',
            'status' => 'active',
        ]);
        $this->teacher = Teacher::create([
            'user_id' => $this->teacherUser->id,
            'nip' => '198505152010011002',
        ]);
    }

    /**
     * Test Authorization: Guest cannot access dashboard
     */
    public function test_guest_cannot_access_attendance_dashboard(): void
    {
        $response = $this->get(route('admin.dashboard'));
        $response->assertRedirect(route('login'));
    }

    /**
     * Test Authorization: Non-admin cannot access dashboard
     */
    public function test_non_admin_cannot_access_attendance_dashboard(): void
    {
        $this->actingAs($this->studentUser1)
            ->get(route('admin.dashboard'))
            ->assertStatus(403);

        $this->actingAs($this->teacherUser)
            ->get(route('admin.dashboard'))
            ->assertStatus(403);
    }

    /**
     * Test Summary cards calculations
     */
    public function test_admin_can_view_summary_cards(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 12, 8, 0, 0));

        // Create 1 present and 1 late attendance today
        Attendance::create([
            'user_id' => $this->studentUser1->id,
            'attendance_date' => '2026-10-12',
            'attendance_time' => '07:05:00',
            'status' => 'present',
            'verification_status' => 'verified',
        ]);

        Attendance::create([
            'user_id' => $this->teacherUser->id,
            'attendance_date' => '2026-10-12',
            'attendance_time' => '07:25:00',
            'status' => 'late',
            'verification_status' => 'verified',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Total Siswa');
        $response->assertSee('Total Guru');
        $response->assertSee('Hadir Hari Ini');
        $response->assertSee('Terlambat Hari Ini');
        $response->assertSee('Belum Absen');

        // Total students: 2, Total teachers: 1
        // Hadir: 1, Late: 1
        // Belum Absen: 3 active users - 2 attended = 1 (student2)
        $stats = $response->viewData('stats');
        $this->assertEquals(2, $stats['total_students']);
        $this->assertEquals(1, $stats['total_teachers']);
        $this->assertEquals(1, $stats['today_present']);
        $this->assertEquals(1, $stats['today_late']);
        $this->assertEquals(1, $stats['today_belum_absen']);
    }

    /**
     * Test Empty State when no attendance data exists
     */
    public function test_dashboard_displays_empty_state_when_no_data(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Tidak Ada Data Presensi Ditemukan');
        $this->assertEmpty($response->viewData('attendances'));
    }

    /**
     * Test Filter by Date
     */
    public function test_dashboard_filters_by_date(): void
    {
        Attendance::create([
            'user_id' => $this->studentUser1->id,
            'attendance_date' => '2026-10-10',
            'attendance_time' => '07:00:00',
            'status' => 'present',
            'verification_status' => 'verified',
        ]);

        Attendance::create([
            'user_id' => $this->studentUser2->id,
            'attendance_date' => '2026-10-12',
            'attendance_time' => '07:10:00',
            'status' => 'present',
            'verification_status' => 'verified',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.dashboard', ['date' => '2026-10-10']));

        $response->assertStatus(200);
        $attendances = $response->viewData('attendances');
        $this->assertCount(1, $attendances);
        $this->assertEquals($this->studentUser1->id, $attendances->first()->user_id);
    }

    /**
     * Test Filter by Role
     */
    public function test_dashboard_filters_by_role(): void
    {
        Attendance::create([
            'user_id' => $this->studentUser1->id,
            'attendance_date' => '2026-10-12',
            'attendance_time' => '07:00:00',
            'status' => 'present',
            'verification_status' => 'verified',
        ]);

        Attendance::create([
            'user_id' => $this->teacherUser->id,
            'attendance_date' => '2026-10-12',
            'attendance_time' => '07:05:00',
            'status' => 'present',
            'verification_status' => 'verified',
        ]);

        // Filter role teacher
        $response = $this->actingAs($this->admin)
            ->get(route('admin.dashboard', ['role' => 'teacher']));

        $response->assertStatus(200);
        $attendances = $response->viewData('attendances');
        $this->assertCount(1, $attendances);
        $this->assertEquals($this->teacherUser->id, $attendances->first()->user_id);
    }

    /**
     * Test Filter by Class
     */
    public function test_dashboard_filters_by_class(): void
    {
        Attendance::create([
            'user_id' => $this->studentUser1->id,
            'attendance_date' => '2026-10-12',
            'attendance_time' => '07:00:00',
            'status' => 'present',
            'verification_status' => 'verified',
        ]);

        Attendance::create([
            'user_id' => $this->studentUser2->id,
            'attendance_date' => '2026-10-12',
            'attendance_time' => '07:05:00',
            'status' => 'present',
            'verification_status' => 'verified',
        ]);

        // Filter XII RPL 1
        $response = $this->actingAs($this->admin)
            ->get(route('admin.dashboard', ['class' => 'XII RPL 1']));

        $response->assertStatus(200);
        $attendances = $response->viewData('attendances');
        $this->assertCount(1, $attendances);
        $this->assertEquals($this->studentUser1->id, $attendances->first()->user_id);
    }

    /**
     * Test Filter by Status
     */
    public function test_dashboard_filters_by_status(): void
    {
        Attendance::create([
            'user_id' => $this->studentUser1->id,
            'attendance_date' => '2026-10-12',
            'attendance_time' => '07:00:00',
            'status' => 'present',
            'verification_status' => 'verified',
        ]);

        Attendance::create([
            'user_id' => $this->studentUser2->id,
            'attendance_date' => '2026-10-12',
            'attendance_time' => '07:30:00',
            'status' => 'late',
            'verification_status' => 'verified',
        ]);

        // Filter status late
        $response = $this->actingAs($this->admin)
            ->get(route('admin.dashboard', ['status' => 'late']));

        $response->assertStatus(200);
        $attendances = $response->viewData('attendances');
        $this->assertCount(1, $attendances);
        $this->assertEquals('late', $attendances->first()->status);
    }

    /**
     * Test Search by Name, NIS, NIP
     */
    public function test_dashboard_searches_by_name_nis_and_nip(): void
    {
        Attendance::create([
            'user_id' => $this->studentUser1->id,
            'attendance_date' => '2026-10-12',
            'attendance_time' => '07:00:00',
            'status' => 'present',
            'verification_status' => 'verified',
        ]);

        Attendance::create([
            'user_id' => $this->teacherUser->id,
            'attendance_date' => '2026-10-12',
            'attendance_time' => '07:05:00',
            'status' => 'present',
            'verification_status' => 'verified',
        ]);

        // Search by student name
        $resName = $this->actingAs($this->admin)
            ->get(route('admin.dashboard', ['search' => 'Ahmad']));
        $this->assertCount(1, $resName->viewData('attendances'));

        // Search by student NIS
        $resNis = $this->actingAs($this->admin)
            ->get(route('admin.dashboard', ['search' => '10001']));
        $this->assertCount(1, $resNis->viewData('attendances'));

        // Search by teacher NIP
        $resNip = $this->actingAs($this->admin)
            ->get(route('admin.dashboard', ['search' => '19850515']));
        $this->assertCount(1, $resNip->viewData('attendances'));
    }

    /**
     * Test Pagination with multiple records
     */
    public function test_dashboard_pagination_with_many_records(): void
    {
        // Create 20 attendance records
        for ($i = 1; $i <= 20; $i++) {
            Attendance::create([
                'user_id' => $this->studentUser1->id,
                'attendance_date' => "2026-09-{$i}",
                'attendance_time' => '07:00:00',
                'status' => 'present',
                'verification_status' => 'verified',
            ]);
        }

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertStatus(200);
        $attendances = $response->viewData('attendances');
        $this->assertEquals(15, $attendances->count()); // Page 1 has 15 items
        $this->assertEquals(20, $attendances->total());
        $this->assertTrue($attendances->hasPages());

        // Check page 2
        $responsePage2 = $this->actingAs($this->admin)->get(route('admin.dashboard', ['page' => 2]));
        $this->assertEquals(5, $responsePage2->viewData('attendances')->count()); // Page 2 has 5 items
    }

    /**
     * Test Detail Attendance endpoint
     */
    public function test_attendance_detail_endpoint_returns_json(): void
    {
        $att = Attendance::create([
            'user_id' => $this->studentUser1->id,
            'attendance_date' => '2026-10-12',
            'attendance_time' => '07:05:00',
            'status' => 'present',
            'verification_status' => 'verified',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.attendances.show', $att));

        $response->assertStatus(200)
            ->assertJson([
                'id' => $att->id,
                'name' => $this->studentUser1->name,
                'nis' => '10001',
                'class' => 'XII RPL 1',
                'status' => 'present',
                'status_label' => 'Hadir',
            ]);
    }

    /**
     * Test Face Data is never exposed on dashboard view or detail endpoint
     */
    public function test_face_data_is_never_exposed_on_dashboard_or_detail(): void
    {
        $this->student1->update(['face_data' => 'secret-biometric-vector-12345']);

        $att = Attendance::create([
            'user_id' => $this->studentUser1->id,
            'attendance_date' => '2026-10-12',
            'attendance_time' => '07:05:00',
            'status' => 'present',
            'verification_status' => 'verified',
        ]);

        $dashboardResponse = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $dashboardResponse->assertStatus(200);
        $this->assertStringNotContainsString('secret-biometric-vector', $dashboardResponse->getContent());

        $detailResponse = $this->actingAs($this->admin)->getJson(route('admin.attendances.show', $att));
        $detailResponse->assertStatus(200);
        $this->assertArrayNotHasKey('face_data', $detailResponse->json());
        $this->assertStringNotContainsString('secret-biometric-vector', $detailResponse->getContent());
    }
}
