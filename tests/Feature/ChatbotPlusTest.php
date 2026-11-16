<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatbotPlusTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test guest cannot access Chatbot+ endpoint.
     */
    public function test_guest_cannot_access_chatbot(): void
    {
        $response = $this->postJson(route('chatbot.ask'), [
            'message' => 'Halo',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test empty query is rejected with friendly message.
     */
    public function test_empty_message_is_rejected(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => '   ',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    /**
     * Test off-topic query is rejected with the exact standard message.
     */
    public function test_off_topic_questions_are_strictly_rejected(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);

        $offTopicQuestions = [
            'Buatkan saya kode python untuk web scraping',
            'Bagaimana resep membuat kue bolu?',
            'Siapa presiden pertama Indonesia?',
            'Berapa hasil hitung 25 + 75?',
            'Tuliskan cerita pendek tentang petualangan luar angkasa',
        ];

        foreach ($offTopicQuestions as $question) {
            $response = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
                'message' => $question,
            ]);

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'reply' => 'Maaf, Chatbot+ hanya dapat membantu pertanyaan seputar SmartAttendance, identitas akun, statistik dashboard, absensi, izin/sakit, dan panduan penggunaan aplikasi.',
                    'intent' => 'off_topic',
                ]);
        }
    }

    /**
     * Test Administrator identity and statistics queries.
     */
    public function test_admin_identity_and_statistical_queries(): void
    {
        $admin = User::factory()->create([
            'name' => 'Budi Administrator',
            'role' => 'admin',
            'status' => 'active',
        ]);

        // Create sample students
        $studentUser1 = User::factory()->create(['name' => 'Siswa Satu', 'role' => 'student', 'status' => 'active']);
        $student1 = Student::create(['user_id' => $studentUser1->id, 'nis' => '1001', 'class' => 'X-A']);

        $studentUser2 = User::factory()->create(['name' => 'Siswa Dua', 'role' => 'student', 'status' => 'active']);
        $student2 = Student::create(['user_id' => $studentUser2->id, 'nis' => '1002', 'class' => 'X-B']);

        // Create sample teacher
        $teacherUser = User::factory()->create(['name' => 'Pak Guru Joko', 'role' => 'teacher', 'status' => 'active']);
        $teacher = Teacher::create(['user_id' => $teacherUser->id, 'nip' => '19800101']);

        // Create attendances for today
        $today = Carbon::today()->toDateString();

        // 1 present, 1 late
        Attendance::create([
            'user_id' => $studentUser1->id,
            'attendance_date' => $today,
            'attendance_time' => '07:05:00',
            'status' => 'present',
            'verification_status' => 'verified',
        ]);

        Attendance::create([
            'user_id' => $teacherUser->id,
            'attendance_date' => $today,
            'attendance_time' => '07:25:00',
            'status' => 'late',
            'verification_status' => 'verified',
        ]);

        // 1. Identity query
        $resIdentity = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Siapa saya?',
        ]);
        $resIdentity->assertStatus(200)
            ->assertJson([
                'success' => true,
                'reply' => 'Kamu adalah Budi Administrator, administrator SmartAttendance.',
                'intent' => 'identity',
            ]);

        // 2. Total siswa
        $resStudents = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Berapa total siswa?',
        ]);
        $resStudents->assertStatus(200)
            ->assertJson([
                'success' => true,
                'reply' => 'Total siswa terdaftar saat ini adalah 2 siswa.',
                'intent' => 'admin_total_students',
            ]);

        // 3. Total guru
        $resTeachers = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Berapa total guru?',
        ]);
        $resTeachers->assertStatus(200)
            ->assertJson([
                'success' => true,
                'reply' => 'Total guru terdaftar saat ini adalah 1 guru.',
                'intent' => 'admin_total_teachers',
            ]);

        // 4. Hadir hari ini
        $resPresent = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Berapa yang hadir hari ini?',
        ]);
        $resPresent->assertStatus(200)
            ->assertJson([
                'success' => true,
                'reply' => 'Jumlah kehadiran hari ini adalah 1 orang.',
                'intent' => 'admin_today_present',
            ]);

        // 5. Terlambat hari ini
        $resLate = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Berapa yang terlambat hari ini?',
        ]);
        $resLate->assertStatus(200)
            ->assertJson([
                'success' => true,
                'reply' => 'Jumlah yang terlambat hari ini adalah 1 orang.',
                'intent' => 'admin_today_late',
            ]);

        // 6. Siapa yang belum absen?
        // Total active personnel: student1, student2, teacher = 3. Attended: student1, teacher = 2. Unattended: student2 = 1.
        $resUnattended = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Siapa yang belum absen?',
        ]);
        $resUnattended->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'admin_today_unattended',
            ]);
        $this->assertStringContainsString('Jumlah yang belum absen hari ini adalah 1 orang', $resUnattended->json('reply'));
        $this->assertStringContainsString('Siswa Dua (Siswa)', $resUnattended->json('reply'));
    }

    /**
     * Test Teacher identity and statistics queries.
     */
    public function test_teacher_identity_and_statistical_queries(): void
    {
        $teacherUser = User::factory()->create([
            'name' => 'Siti Aminah, S.Pd',
            'role' => 'teacher',
            'status' => 'active',
        ]);
        Teacher::create(['user_id' => $teacherUser->id, 'nip' => '19850202']);

        $studentUser = User::factory()->create(['name' => 'Ahmad Siswa', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $studentUser->id, 'nis' => '2001', 'class' => 'XI-IPA']);

        $today = Carbon::today()->toDateString();

        // 1 pending leave, 1 approved leave today, 1 present today
        Attendance::create([
            'user_id' => $studentUser->id,
            'attendance_date' => $today,
            'attendance_time' => '07:00:00',
            'status' => 'sick',
            'approval_status' => 'pending',
            'reason' => 'Demam tinggi',
        ]);

        Attendance::create([
            'user_id' => $teacherUser->id,
            'attendance_date' => $today,
            'attendance_time' => '06:50:00',
            'status' => 'present',
            'verification_status' => 'verified',
        ]);

        // 1. Identity
        $resIdentity = $this->actingAs($teacherUser)->postJson(route('chatbot.ask'), [
            'message' => 'Siapa saya?',
        ]);
        $resIdentity->assertStatus(200)
            ->assertJson([
                'success' => true,
                'reply' => 'Kamu adalah Siti Aminah, S.Pd, guru SmartAttendance.',
                'intent' => 'identity',
            ]);

        // 2. Total siswa terdaftar
        $resStudents = $this->actingAs($teacherUser)->postJson(route('chatbot.ask'), [
            'message' => 'Berapa total siswa terdaftar?',
        ]);
        $resStudents->assertStatus(200)
            ->assertJson([
                'success' => true,
                'reply' => 'Total siswa terdaftar saat ini adalah 1 siswa.',
                'intent' => 'teacher_total_students',
            ]);

        // 3. Izin pending
        $resPending = $this->actingAs($teacherUser)->postJson(route('chatbot.ask'), [
            'message' => 'Pengajuan izin dan sakit yang menunggu persetujuan?',
        ]);
        $resPending->assertStatus(200)
            ->assertJson([
                'success' => true,
                'reply' => 'Pengajuan izin dan sakit yang menunggu persetujuan saat ini adalah 1 pengajuan.',
                'intent' => 'teacher_pending_leaves',
            ]);

        // 4. Hadir hari ini
        $resPresent = $this->actingAs($teacherUser)->postJson(route('chatbot.ask'), [
            'message' => 'Berapa jumlah kehadiran hari ini?',
        ]);
        $resPresent->assertStatus(200)
            ->assertJson([
                'success' => true,
                'reply' => 'Jumlah kehadiran hari ini adalah 1 orang.',
                'intent' => 'teacher_today_present',
            ]);
    }

    /**
     * Test Student identity and private statistics isolation.
     */
    public function test_student_identity_and_statistics_isolation(): void
    {
        $studentUserA = User::factory()->create([
            'name' => 'Dimas Anggara',
            'role' => 'student',
            'status' => 'active',
        ]);
        Student::create(['user_id' => $studentUserA->id, 'nis' => '3001', 'class' => 'XII-RPL']);

        $studentUserB = User::factory()->create([
            'name' => 'Rina Nose',
            'role' => 'student',
            'status' => 'active',
        ]);
        Student::create(['user_id' => $studentUserB->id, 'nis' => '3002', 'class' => 'XII-TKJ']);

        $today = Carbon::today()->toDateString();

        // Student A has 2 present records, 1 late record
        Attendance::create([
            'user_id' => $studentUserA->id,
            'attendance_date' => $today,
            'attendance_time' => '07:00:00',
            'status' => 'present',
            'verification_status' => 'verified',
        ]);
        Attendance::create([
            'user_id' => $studentUserA->id,
            'attendance_date' => Carbon::yesterday()->toDateString(),
            'attendance_time' => '07:05:00',
            'status' => 'present',
            'verification_status' => 'verified',
        ]);
        Attendance::create([
            'user_id' => $studentUserA->id,
            'attendance_date' => Carbon::now()->subDays(2)->toDateString(),
            'attendance_time' => '07:30:00',
            'status' => 'late',
            'verification_status' => 'verified',
        ]);

        // Student B has 10 present records (should NOT affect Student A)
        for ($i = 1; $i <= 10; $i++) {
            Attendance::create([
                'user_id' => $studentUserB->id,
                'attendance_date' => Carbon::now()->subDays($i)->toDateString(),
                'attendance_time' => '06:55:00',
                'status' => 'present',
                'verification_status' => 'verified',
            ]);
        }

        // 1. Identity
        $resIdentity = $this->actingAs($studentUserA)->postJson(route('chatbot.ask'), [
            'message' => 'Siapa saya?',
        ]);
        $resIdentity->assertStatus(200)
            ->assertJson([
                'success' => true,
                'reply' => 'Kamu adalah Dimas Anggara, siswa SmartAttendance.',
                'intent' => 'identity',
            ]);

        // 2. Hadir tepat waktu (Student A only: 2, not 12)
        $resPresent = $this->actingAs($studentUserA)->postJson(route('chatbot.ask'), [
            'message' => 'Berapa kali hadir tepat waktu?',
        ]);
        $resPresent->assertStatus(200)
            ->assertJson([
                'success' => true,
                'reply' => 'Jumlah kehadiran tepat waktu kamu adalah 2 kali.',
                'intent' => 'student_total_present',
            ]);

        // 3. Keterlambatan (Student A only: 1)
        $resLate = $this->actingAs($studentUserA)->postJson(route('chatbot.ask'), [
            'message' => 'Berapa jumlah keterlambatan saya?',
        ]);
        $resLate->assertStatus(200)
            ->assertJson([
                'success' => true,
                'reply' => 'Jumlah keterlambatan kamu adalah 1 kali.',
                'intent' => 'student_total_late',
            ]);

        // 4. Status absensi hari ini
        $resToday = $this->actingAs($studentUserA)->postJson(route('chatbot.ask'), [
            'message' => 'Bagaimana status absensi hari ini?',
        ]);
        $resToday->assertStatus(200)
            ->assertJson([
                'success' => true,
                'reply' => 'Status absensi kamu hari ini adalah Hadir (Tepat Waktu) pada pukul 07:00:00 WIB.',
                'intent' => 'student_today_status',
            ]);

        // 5. If student has not attended today
        $resTodayB = $this->actingAs($studentUserB)->postJson(route('chatbot.ask'), [
            'message' => 'Apakah saya sudah absen hari ini?',
        ]);
        $resTodayB->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'student_today_status',
            ]);
        $this->assertStringContainsString('belum melakukan absensi', $resTodayB->json('reply'));
    }

    /**
     * Test Indonesian variations matching.
     */
    public function test_indonesian_variations_matching(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);

        $variations = [
            'aku ini siapa?' => 'identity',
            'saya login sebagai apa?' => 'identity',
            'SIAPA SAYA???' => 'identity',
            'jam berapa masuk dan batas terlambat?' => 'system_hours',
            'bagaimana cara absensi mandiri?' => 'system_attendance_guide',
            'kamera saya tidak aktif' => 'system_camera_troubleshoot',
            'verifikasi wajah gagal terus' => 'system_face_troubleshoot',
        ];

        foreach ($variations as $msg => $expectedIntent) {
            $response = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
                'message' => $msg,
            ]);

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'intent' => $expectedIntent,
                ]);
        }
    }

    /**
     * Test Dashboards render the Chatbot+ component.
     */
    public function test_dashboards_render_chatbot_plus(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        Teacher::create(['user_id' => $teacher->id, 'nip' => '12345']);

        $student = User::factory()->create(['name' => 'Budi Siswa', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $student->id, 'nis' => '54321', 'class' => 'X-A']);

        // 1. Admin Dashboard
        $adminRes = $this->actingAs($admin)->get(route('admin.dashboard'));
        $adminRes->assertStatus(200)
            ->assertSee('chatbot-plus-root')
            ->assertSee('Chatbot+');

        // 2. Teacher Dashboard
        $teacherRes = $this->actingAs($teacher)->get(route('teacher.dashboard'));
        $teacherRes->assertStatus(200)
            ->assertSee('chatbot-plus-root')
            ->assertSee('Chatbot+');

        // 3. Student Dashboard
        $studentRes = $this->actingAs($student)->get(route('student.dashboard'));
        $studentRes->assertStatus(200)
            ->assertSee('chatbot-plus-root')
            ->assertSee('Chatbot+');
    }

    /**
     * Test dynamic identity answers and question variations for Admin, Teacher, and Student.
     */
    public function test_dynamic_identity_for_all_roles_and_variations(): void
    {
        // 1. Admin: Budi Santoso
        $admin = User::factory()->create([
            'name' => 'Budi Santoso',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $adminQuestions = [
            'Siapa saya?',
            'nama saya siapa',
            'akun saya apa',
            'siapa nama saya',
        ];

        foreach ($adminQuestions as $q) {
            $res = $this->actingAs($admin)->postJson(route('chatbot.ask'), ['message' => $q]);
            $res->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'reply' => 'Kamu adalah Budi Santoso, administrator SmartAttendance.',
                    'intent' => 'identity',
                ]);
        }

        // 2. Teacher: Siti Rahma
        $teacher = User::factory()->create([
            'name' => 'Siti Rahma',
            'role' => 'teacher',
            'status' => 'active',
        ]);
        Teacher::create(['user_id' => $teacher->id, 'nip' => '19850101']);

        $resTeacher = $this->actingAs($teacher)->postJson(route('chatbot.ask'), ['message' => 'Siapa Saya?']);
        $resTeacher->assertStatus(200)
            ->assertJson([
                'success' => true,
                'reply' => 'Kamu adalah Siti Rahma, guru SmartAttendance.',
                'intent' => 'identity',
            ]);

        // 3. Student: Andi Pratama
        $student = User::factory()->create([
            'name' => 'Andi Pratama',
            'role' => 'student',
            'status' => 'active',
        ]);
        Student::create(['user_id' => $student->id, 'nis' => '8888', 'class' => 'XI-RPL']);

        $resStudent = $this->actingAs($student)->postJson(route('chatbot.ask'), ['message' => 'Siapa Saya?']);
        $resStudent->assertStatus(200)
            ->assertJson([
                'success' => true,
                'reply' => 'Kamu adalah Andi Pratama, siswa SmartAttendance.',
                'intent' => 'identity',
            ]);

        // 4. Fallback if user name is empty
        $adminNoName = User::factory()->create([
            'name' => '',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $resFallback = $this->actingAs($adminNoName)->postJson(route('chatbot.ask'), ['message' => 'Siapa saya?']);
        $resFallback->assertStatus(200)
            ->assertJson([
                'success' => true,
                'reply' => 'Kamu adalah administrator SmartAttendance.',
                'intent' => 'identity',
            ]);
    }

    /**
     * Test admin can query actual student, teacher, and admin lists and stats.
     */
    public function test_admin_can_query_actual_student_and_teacher_lists_and_stats(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin Utama',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $student1 = User::factory()->create(['name' => 'Ahmad Fathan', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $student1->id, 'nis' => '1001', 'class' => 'X-A']);

        $student2 = User::factory()->create(['name' => 'Bunga Citra', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $student2->id, 'nis' => '1002', 'class' => 'X-B']);

        $student3 = User::factory()->create(['name' => 'Chandra Wijaya', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $student3->id, 'nis' => '1003', 'class' => 'X-A']);

        $teacher1 = User::factory()->create(['name' => 'Dewi Sartika, M.Pd', 'role' => 'teacher', 'status' => 'active']);
        Teacher::create(['user_id' => $teacher1->id, 'nip' => '19810101']);

        $teacher2 = User::factory()->create(['name' => 'Eko Prasetyo, S.Kom', 'role' => 'teacher', 'status' => 'active']);
        Teacher::create(['user_id' => $teacher2->id, 'nip' => '19850202']);

        // 1. "Siapa saja nama siswa?"
        $resStudents = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Siapa saja nama siswa?',
        ]);
        $resStudents->assertStatus(200);
        $replyStudents = $resStudents->json('reply');
        $this->assertStringContainsString('Berikut daftar siswa yang terdaftar di SmartAttendance:', $replyStudents);
        $this->assertStringContainsString('Ahmad Fathan', $replyStudents);
        $this->assertStringContainsString('Bunga Citra', $replyStudents);
        $this->assertStringContainsString('Chandra Wijaya', $replyStudents);
        $this->assertStringContainsString('Total siswa terdaftar: 3 siswa.', $replyStudents);

        // 2. "Berapa total siswa?"
        $resTotalStudents = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Berapa total siswa?',
        ]);
        $resTotalStudents->assertStatus(200)
            ->assertJson([
                'success' => true,
                'reply' => 'Total siswa terdaftar saat ini adalah 3 siswa.',
            ]);

        // 3. "Siapa saja nama guru?"
        $resTeachers = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Siapa saja nama guru?',
        ]);
        $resTeachers->assertStatus(200);
        $replyTeachers = $resTeachers->json('reply');
        $this->assertStringContainsString('Berikut daftar guru yang terdaftar di SmartAttendance:', $replyTeachers);
        $this->assertStringContainsString('Dewi Sartika, M.Pd', $replyTeachers);
        $this->assertStringContainsString('Eko Prasetyo, S.Kom', $replyTeachers);
        $this->assertStringContainsString('Total guru terdaftar: 2 guru.', $replyTeachers);

        // 4. "Berapa total guru?"
        $resTotalTeachers = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Berapa total guru?',
        ]);
        $resTotalTeachers->assertStatus(200)
            ->assertJson([
                'success' => true,
                'reply' => 'Total guru terdaftar saat ini adalah 2 guru.',
            ]);

        // 5. "Siapa saja admin?"
        $resAdmins = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Siapa saja admin?',
        ]);
        $resAdmins->assertStatus(200);
        $replyAdmins = $resAdmins->json('reply');
        $this->assertStringContainsString('Admin Utama', $replyAdmins);
        $this->assertStringContainsString('Total admin terdaftar: 1 admin.', $replyAdmins);

        // 6. "Berapa total admin?"
        $resTotalAdmin = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Berapa total admin?',
        ]);
        $resTotalAdmin->assertStatus(200)
            ->assertJson([
                'success' => true,
                'reply' => 'Total admin terdaftar saat ini adalah 1 admin.',
            ]);

        // 7. "Apa peran akun saya?"
        $resRole = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Apa peran akun saya?',
        ]);
        $resRole->assertStatus(200)
            ->assertJson([
                'success' => true,
                'reply' => 'Peran akun kamu adalah Administrator pada sistem SmartAttendance.',
                'intent' => 'identity',
            ]);
    }

    /**
     * Test attendance queries and class breakdown.
     */
    public function test_attendance_and_class_queries(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);

        $today = Carbon::today()->toDateString();

        $student1 = User::factory()->create(['name' => 'Fajar Hadir', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $student1->id, 'nis' => '5001', 'class' => 'X-A']);

        $student2 = User::factory()->create(['name' => 'Gilang Telat', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $student2->id, 'nis' => '5002', 'class' => 'X-A']);

        $student3 = User::factory()->create(['name' => 'Hani Sakit', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $student3->id, 'nis' => '5003', 'class' => 'XI-B']);

        $student4 = User::factory()->create(['name' => 'Indra Belum Absen', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $student4->id, 'nis' => '5004', 'class' => 'XI-B']);

        $teacher1 = User::factory()->create(['name' => 'Guru Joko Hadir', 'role' => 'teacher', 'status' => 'active']);
        Teacher::create(['user_id' => $teacher1->id, 'nip' => '9901']);

        $teacher2 = User::factory()->create(['name' => 'Guru Kiki Belum Absen', 'role' => 'teacher', 'status' => 'active']);
        Teacher::create(['user_id' => $teacher2->id, 'nip' => '9902']);

        // Student 1: present
        Attendance::create([
            'user_id' => $student1->id,
            'attendance_date' => $today,
            'attendance_time' => '07:00:00',
            'status' => 'present',
            'verification_status' => 'verified',
        ]);

        // Student 2: late
        Attendance::create([
            'user_id' => $student2->id,
            'attendance_date' => $today,
            'attendance_time' => '07:20:00',
            'status' => 'late',
            'verification_status' => 'verified',
        ]);

        // Student 3: sick
        Attendance::create([
            'user_id' => $student3->id,
            'attendance_date' => $today,
            'status' => 'sick',
            'approval_status' => 'approved',
            'reason' => 'Demam flu',
        ]);

        // Teacher 1: present
        Attendance::create([
            'user_id' => $teacher1->id,
            'attendance_date' => $today,
            'attendance_time' => '06:55:00',
            'status' => 'present',
            'verification_status' => 'verified',
        ]);

        // 1. "Berapa siswa yang hadir hari ini?"
        $resPresentCount = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Berapa siswa yang hadir hari ini?',
        ]);
        $resPresentCount->assertStatus(200)
            ->assertJson([
                'success' => true,
                'reply' => 'Jumlah siswa yang hadir hari ini adalah 2 siswa.',
            ]);

        // 2. "Siapa siswa yang hadir hari ini?"
        $resPresentList = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Siapa siswa yang hadir hari ini?',
        ]);
        $resPresentList->assertStatus(200);
        $this->assertStringContainsString('Fajar Hadir', $resPresentList->json('reply'));
        $this->assertStringContainsString('Gilang Telat', $resPresentList->json('reply'));

        // 3. "Siapa siswa yang terlambat?"
        $resLateList = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Siapa siswa yang terlambat?',
        ]);
        $resLateList->assertStatus(200);
        $this->assertStringContainsString('Gilang Telat', $resLateList->json('reply'));

        // 4. "Siapa siswa yang belum absen?"
        $resUnattendedList = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Siapa siswa yang belum absen?',
        ]);
        $resUnattendedList->assertStatus(200);
        $this->assertStringContainsString('Indra Belum Absen', $resUnattendedList->json('reply'));

        // 5. "Siapa siswa yang izin atau sakit?"
        $resLeaveList = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Siapa siswa yang izin atau sakit?',
        ]);
        $resLeaveList->assertStatus(200);
        $this->assertStringContainsString('Hani Sakit', $resLeaveList->json('reply'));

        // 6. "Berapa jumlah siswa berdasarkan kelas?"
        $resClassStats = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Berapa jumlah siswa berdasarkan kelas?',
        ]);
        $resClassStats->assertStatus(200);
        $this->assertStringContainsString('Kelas X-A: 2 siswa', $resClassStats->json('reply'));
        $this->assertStringContainsString('Kelas XI-B: 2 siswa', $resClassStats->json('reply'));

        // 7. "Siapa saja siswa kelas X-A?"
        $resClassXA = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Siapa saja siswa kelas X-A?',
        ]);
        $resClassXA->assertStatus(200);
        $this->assertStringContainsString('Fajar Hadir', $resClassXA->json('reply'));
        $this->assertStringContainsString('Gilang Telat', $resClassXA->json('reply'));

        // 8. "Siapa guru yang hadir hari ini?"
        $resTeacherPresent = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Siapa guru yang hadir hari ini?',
        ]);
        $resTeacherPresent->assertStatus(200);
        $this->assertStringContainsString('Guru Joko Hadir', $resTeacherPresent->json('reply'));

        // 9. "Siapa guru yang belum absen?"
        $resTeacherUnattended = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Siapa guru yang belum absen?',
        ]);
        $resTeacherUnattended->assertStatus(200);
        $this->assertStringContainsString('Guru Kiki Belum Absen', $resTeacherUnattended->json('reply'));
    }

    /**
     * Test role based privacy protection.
     */
    public function test_role_based_privacy_protection(): void
    {
        $studentUser = User::factory()->create([
            'name' => 'Siswa Rahasia',
            'role' => 'student',
            'status' => 'active',
        ]);
        Student::create(['user_id' => $studentUser->id, 'nis' => '7001', 'class' => 'X-A']);

        $teacherUser = User::factory()->create([
            'name' => 'Guru Wali',
            'role' => 'teacher',
            'status' => 'active',
        ]);
        Teacher::create(['user_id' => $teacherUser->id, 'nip' => '7002']);

        // 1. Student asking for other students' list -> privacy restricted
        $resStudentQuery = $this->actingAs($studentUser)->postJson(route('chatbot.ask'), [
            'message' => 'Siapa saja nama siswa?',
        ]);
        $resStudentQuery->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'privacy_restricted',
            ]);
        $this->assertStringContainsString('Demi menjaga privasi', $resStudentQuery->json('reply'));

        // 2. Student asking for teacher list -> privacy restricted
        $resStudentTeacher = $this->actingAs($studentUser)->postJson(route('chatbot.ask'), [
            'message' => 'Siapa saja nama guru?',
        ]);
        $resStudentTeacher->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'privacy_restricted',
            ]);

        // 3. Student asking for admin list -> privacy restricted
        $resStudentAdmin = $this->actingAs($studentUser)->postJson(route('chatbot.ask'), [
            'message' => 'Siapa saja admin?',
        ]);
        $resStudentAdmin->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'privacy_restricted',
            ]);

        // 4. Teacher asking for admin list -> privacy restricted
        $resTeacherAdmin = $this->actingAs($teacherUser)->postJson(route('chatbot.ask'), [
            'message' => 'Siapa saja admin?',
        ]);
        $resTeacherAdmin->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'privacy_restricted',
            ]);
        $this->assertStringContainsString('Informasi mengenai akun administrator hanya dapat diakses oleh Administrator', $resTeacherAdmin->json('reply'));

        // 5. Teacher CAN query student list
        $resTeacherStudent = $this->actingAs($teacherUser)->postJson(route('chatbot.ask'), [
            'message' => 'Siapa saja nama siswa?',
        ]);
        $resTeacherStudent->assertStatus(200);
        $this->assertStringContainsString('Berikut daftar siswa yang terdaftar di SmartAttendance:', $resTeacherStudent->json('reply'));
    }

    /**
     * Test chatbot components do NOT have confirm() popup.
     */
    public function test_chatbot_components_have_no_confirm_popup(): void
    {
        $componentFiles = [
            resource_path('views/components/chatbot-plus.blade.php'),
            resource_path('views/components/smart-chatbot.blade.php'),
            resource_path('views/components/chatbot.blade.php'),
        ];

        foreach ($componentFiles as $file) {
            $content = file_get_contents($file);
            $this->assertStringNotContainsString('confirm(', $content, "File {$file} should not contain confirm() popup");
            $this->assertStringNotContainsString('window.confirm', $content, "File {$file} should not contain window.confirm");
        }
    }
}
