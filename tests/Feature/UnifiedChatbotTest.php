<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnifiedChatbotTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test unified chatbot component is rendered on landing page (guest) and dashboards.
     */
    public function test_unified_chatbot_renders_on_all_interfaces(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        Teacher::create(['user_id' => $teacher->id, 'nip' => '19800101']);

        $student = User::factory()->create(['name' => 'Budi Siswa', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $student->id, 'nis' => '10001', 'class' => 'X-A']);

        // 1. Landing Page (Guest)
        $homeRes = $this->get(route('home'));
        $homeRes->assertStatus(200)
            ->assertSee('smartbot-root')
            ->assertSee('SmartBot');

        // 2. Admin Dashboard
        $adminRes = $this->actingAs($admin)->get(route('admin.dashboard'));
        $adminRes->assertStatus(200)
            ->assertSee('smartbot-root')
            ->assertSee('Chatbot+');

        // 3. Teacher Dashboard
        $teacherRes = $this->actingAs($teacher)->get(route('teacher.dashboard'));
        $teacherRes->assertStatus(200)
            ->assertSee('smartbot-root')
            ->assertSee('Chatbot+');

        // 4. Student Dashboard
        $studentRes = $this->actingAs($student)->get(route('student.dashboard'));
        $studentRes->assertStatus(200)
            ->assertSee('smartbot-root')
            ->assertSee('Chatbot+');
    }

    /**
     * Test query: "Kenapa Andi tidak masuk hari ini?"
     */
    public function test_chatbot_answers_why_student_is_absent_today(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $andiUser = User::factory()->create(['name' => 'Andi Pratama', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $andiUser->id, 'nis' => '2001', 'class' => 'XI-IPA 1']);

        Attendance::create([
            'user_id' => $andiUser->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'status' => 'sick',
            'reason' => 'Demam tinggi dan flu berat',
            'approval_status' => 'approved',
        ]);

        $response = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Kenapa Andi tidak masuk hari ini?',
        ]);

        $response->assertStatus(200);
        $reply = $response->json('reply');
        $this->assertStringContainsString('Andi Pratama', $reply);
        $this->assertStringContainsString('Demam tinggi dan flu berat', $reply);
    }

    /**
     * Test query: "Andi sakit apa?" with documented reason.
     */
    public function test_chatbot_answers_student_illness_with_recorded_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $andiUser = User::factory()->create(['name' => 'Andi Wijaya', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $andiUser->id, 'nis' => '2002', 'class' => 'XI-IPA 2']);

        Attendance::create([
            'user_id' => $andiUser->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'status' => 'sick',
            'reason' => 'Cacar air istirahat dokter',
            'approval_status' => 'approved',
        ]);

        $response = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Andi sakit apa?',
        ]);

        $response->assertStatus(200);
        $reply = $response->json('reply');
        $this->assertStringContainsString('Andi Wijaya', $reply);
        $this->assertStringContainsString('Cacar air istirahat dokter', $reply);
        $this->assertStringContainsString('bukan bukti diagnosis medis resmi', $reply);
    }

    /**
     * Test query: "Andi sakit apa?" when reason is empty or generic (No hallucinated diagnosis).
     */
    public function test_chatbot_handles_student_illness_without_diagnosis_without_fabrication(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $andiUser = User::factory()->create(['name' => 'Andi Susanto', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $andiUser->id, 'nis' => '2003', 'class' => 'XI-IPA 3']);

        Attendance::create([
            'user_id' => $andiUser->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'status' => 'sick',
            'reason' => 'Sakit',
            'approval_status' => 'approved',
        ]);

        $response = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Andi sakit apa?',
        ]);

        $response->assertStatus(200);
        $reply = $response->json('reply');
        $this->assertStringContainsString('Andi Susanto', $reply);
        $this->assertStringContainsString('tanpa keterangan diagnosis atau penyebab spesifik', $reply);
        $this->assertStringContainsString('tidak membuat dugaan diagnosis medis', $reply);
    }

    /**
     * Test query: "Apa alasan Siti tidak hadir selama tiga hari terakhir?"
     */
    public function test_chatbot_answers_multi_day_absence_reasons(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        Teacher::create(['user_id' => $teacher->id, 'nip' => '19850202']);

        $sitiUser = User::factory()->create(['name' => 'Siti Aminah', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $sitiUser->id, 'nis' => '3001', 'class' => 'XII-IPS 1']);

        Attendance::create([
            'user_id' => $sitiUser->id,
            'attendance_date' => Carbon::today()->subDays(1)->toDateString(),
            'status' => 'sick',
            'reason' => 'Migrain berkepanjangan',
            'approval_status' => 'approved',
        ]);

        $response = $this->actingAs($teacher)->postJson(route('chatbot.ask'), [
            'message' => 'Apa alasan Siti tidak hadir selama tiga hari terakhir?',
        ]);

        $response->assertStatus(200);
        $reply = $response->json('reply');
        $this->assertStringContainsString('Siti Aminah', $reply);
        $this->assertStringContainsString('Migrain berkepanjangan', $reply);
    }

    /**
     * Test query: "Berapa kali siswa ini sakit bulan ini?"
     */
    public function test_chatbot_answers_monthly_sick_count(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $budiUser = User::factory()->create(['name' => 'Budi Santoso', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $budiUser->id, 'nis' => '4001', 'class' => 'X-B']);

        Attendance::create([
            'user_id' => $budiUser->id,
            'attendance_date' => Carbon::now()->startOfMonth()->toDateString(),
            'status' => 'sick',
            'reason' => 'Demam',
            'approval_status' => 'approved',
        ]);

        Attendance::create([
            'user_id' => $budiUser->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'status' => 'sick',
            'reason' => 'Flu',
            'approval_status' => 'approved',
        ]);

        $response = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Berapa kali Budi sakit bulan ini?',
        ]);

        $response->assertStatus(200);
        $reply = $response->json('reply');
        $this->assertStringContainsString('Budi Santoso', $reply);
        $this->assertStringContainsString('2 kali', $reply);
    }

    /**
     * Test query: "Siapa saja siswa yang izin sakit minggu ini?"
     */
    public function test_chatbot_answers_weekly_leave_students_list(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $user1 = User::factory()->create(['name' => 'Dina Marlina', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $user1->id, 'nis' => '5001', 'class' => 'X-C']);

        Attendance::create([
            'user_id' => $user1->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'status' => 'sick',
            'reason' => 'Radang tenggorokan',
            'approval_status' => 'approved',
        ]);

        $response = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Siapa saja siswa yang izin sakit minggu ini?',
        ]);

        $response->assertStatus(200);
        $reply = $response->json('reply');
        $this->assertStringContainsString('Dina Marlina', $reply);
        $this->assertStringContainsString('Radang tenggorokan', $reply);
    }

    /**
     * Test query: "Tampilkan rekap absensi siswa berdasarkan status kehadiran."
     */
    public function test_chatbot_answers_attendance_recap(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $user1 = User::factory()->create(['name' => 'Siswa Hadir', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $user1->id, 'nis' => '6001', 'class' => 'X-A']);

        Attendance::create([
            'user_id' => $user1->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'status' => 'present',
        ]);

        $response = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Tampilkan rekap absensi siswa berdasarkan status kehadiran.',
        ]);

        $response->assertStatus(200);
        $reply = $response->json('reply');
        $this->assertStringContainsString('Rekap Absensi Siswa', $reply);
        $this->assertStringContainsString('Hadir Tepat Waktu', $reply);
        $this->assertStringContainsString('Terlambat', $reply);
        $this->assertStringContainsString('Sakit', $reply);
        $this->assertStringContainsString('Izin', $reply);
    }

    /**
     * Test query: "Apakah siswa ini sering terlambat?"
     */
    public function test_chatbot_answers_late_frequency_analysis(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        Teacher::create(['user_id' => $teacher->id, 'nip' => '19870303']);

        $user1 = User::factory()->create(['name' => 'Rian Wijaya', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $user1->id, 'nis' => '7001', 'class' => 'XI-MIPA']);

        // Create 4 late attendances
        for ($i = 1; $i <= 4; $i++) {
            Attendance::create([
                'user_id' => $user1->id,
                'attendance_date' => Carbon::today()->subDays($i)->toDateString(),
                'status' => 'late',
            ]);
        }

        $response = $this->actingAs($teacher)->postJson(route('chatbot.ask'), [
            'message' => 'Apakah Rian sering terlambat?',
        ]);

        $response->assertStatus(200);
        $reply = $response->json('reply');
        $this->assertStringContainsString('Rian Wijaya', $reply);
        $this->assertStringContainsString('4 kali', $reply);
        $this->assertStringContainsString('cukup sering terlambat', $reply);
    }

    /**
     * Test student cannot view another student's sick or absence details (Privacy enforcement).
     */
    public function test_student_cannot_view_other_student_sick_reason(): void
    {
        $studentA = User::factory()->create(['name' => 'Siswa Pengakses', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $studentA->id, 'nis' => '8001', 'class' => 'X-A']);

        $studentB = User::factory()->create(['name' => 'Siswa Target', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $studentB->id, 'nis' => '8002', 'class' => 'X-A']);

        Attendance::create([
            'user_id' => $studentB->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'status' => 'sick',
            'reason' => 'Demam Berdarah Dengue',
            'approval_status' => 'approved',
        ]);

        $response = $this->actingAs($studentA)->postJson(route('chatbot.ask'), [
            'message' => 'Kenapa Siswa Target tidak masuk hari ini?',
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('privasi', $response->json('reply'));
        $this->assertStringNotContainsString('Demam Berdarah Dengue', $response->json('reply'));
    }

    /**
     * Test query: "Arya Kusuma Firdaus izin tidak masuk sekolah kenapa" with recorded reason.
     */
    public function test_chatbot_answers_student_leave_reason_when_reason_is_provided(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        Teacher::create(['user_id' => $teacher->id, 'nip' => '19870101']);

        $aryaUser = User::factory()->create(['name' => 'Arya Kusuma Firdaus', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $aryaUser->id, 'nis' => '9001', 'class' => 'XII-RPL 1']);

        Attendance::create([
            'user_id' => $aryaUser->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'status' => 'excused',
            'reason' => 'acara keluarga',
            'approval_status' => 'approved',
        ]);

        $response = $this->actingAs($teacher)->postJson(route('chatbot.ask'), [
            'message' => 'Arya Kusuma Firdaus izin tidak masuk sekolah kenapa',
        ]);

        $response->assertStatus(200);
        $reply = $response->json('reply');
        $this->assertStringContainsString('Arya Kusuma Firdaus tercatat izin tidak masuk pada', $reply);
        $this->assertStringContainsString('Keterangan yang tersimpan adalah acara keluarga.', $reply);
    }

    /**
     * Test query: "Arya Kusuma Firdaus izin tidak masuk sekolah kenapa" when leave has no reason recorded.
     */
    public function test_chatbot_answers_student_leave_when_reason_is_empty(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $aryaUser = User::factory()->create(['name' => 'Arya Kusuma Firdaus', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $aryaUser->id, 'nis' => '9002', 'class' => 'XII-RPL 1']);

        Attendance::create([
            'user_id' => $aryaUser->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'status' => 'excused',
            'reason' => null,
            'approval_status' => 'approved',
        ]);

        $response = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Arya Kusuma Firdaus izin tidak masuk sekolah kenapa',
        ]);

        $response->assertStatus(200);
        $reply = $response->json('reply');
        $this->assertStringContainsString('Arya Kusuma Firdaus tercatat izin tidak masuk pada', $reply);
        $this->assertStringContainsString('tetapi alasan izin belum dicantumkan dalam data.', $reply);
    }

    /**
     * Test query for student leave when no matching record is found.
     */
    public function test_chatbot_answers_when_no_matching_leave_record_exists(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        Teacher::create(['user_id' => $teacher->id, 'nip' => '19870102']);

        $aryaUser = User::factory()->create(['name' => 'Arya Kusuma Firdaus', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $aryaUser->id, 'nis' => '9003', 'class' => 'XII-RPL 1']);

        $response = $this->actingAs($teacher)->postJson(route('chatbot.ask'), [
            'message' => 'Arya Kusuma Firdaus izin tidak masuk sekolah kenapa',
        ]);

        $response->assertStatus(200);
        $reply = $response->json('reply');
        $this->assertStringContainsString('Saya belum menemukan catatan izin yang sesuai untuk siswa tersebut. Silakan periksa nama atau tanggal yang dimaksud.', $reply);
    }

    /**
     * Test query for leave reason of non-existent student name.
     */
    public function test_chatbot_answers_when_student_name_not_found(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        Teacher::create(['user_id' => $teacher->id, 'nip' => '19870103']);

        $response = $this->actingAs($teacher)->postJson(route('chatbot.ask'), [
            'message' => 'Fulan bin Fulan izin tidak masuk sekolah kenapa',
        ]);

        $response->assertStatus(200);
        $reply = $response->json('reply');
        $this->assertStringContainsString('Saya belum menemukan catatan izin yang sesuai untuk siswa tersebut. Silakan periksa nama atau tanggal yang dimaksud.', $reply);
    }

    /**
     * Test query: "Berapa kali siswa tidak masuk karena izin atau sakit?"
     */
    public function test_chatbot_answers_leave_frequency_count(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        Teacher::create(['user_id' => $teacher->id, 'nip' => '19870104']);

        $studentUser = User::factory()->create(['name' => 'Arya Kusuma Firdaus', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $studentUser->id, 'nis' => '9004', 'class' => 'XII-RPL 1']);

        // Create 2 sick records and 1 excused record
        Attendance::create([
            'user_id' => $studentUser->id,
            'attendance_date' => Carbon::today()->subDays(2)->toDateString(),
            'status' => 'sick',
            'reason' => 'Demam',
            'approval_status' => 'approved',
        ]);
        Attendance::create([
            'user_id' => $studentUser->id,
            'attendance_date' => Carbon::today()->subDays(1)->toDateString(),
            'status' => 'excused',
            'reason' => 'Keperluan keluarga',
            'approval_status' => 'approved',
        ]);

        $response = $this->actingAs($teacher)->postJson(route('chatbot.ask'), [
            'message' => 'Berapa kali Arya Kusuma Firdaus tidak masuk karena izin atau sakit?',
        ]);

        $response->assertStatus(200);
        $reply = $response->json('reply');
        $this->assertStringContainsString('Arya Kusuma Firdaus', $reply);
        $this->assertStringContainsString('tidak masuk sebanyak 2 kali', $reply);
    }
}
