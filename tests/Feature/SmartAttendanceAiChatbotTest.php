<?php

namespace Tests\Feature;

use App\Models\AiSetting;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmartAttendanceAiChatbotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Start from a clean state: no provider is active and no model is configured.
        foreach (AiSetting::supportedProviders() as $provider) {
            AiSetting::updateOrCreate(['provider' => $provider], [
                'is_active' => false,
                'model' => null,
                'api_key' => null,
                'base_url' => AiSetting::defaultBaseUrl($provider),
                'temperature' => 0.70,
                'max_tokens' => 1024,
                'last_tested_at' => null,
                'last_test_status' => null,
                'last_test_message' => null,
            ]);
        }
    }

    /**
     * Test chatbot answers why student is sick using the actual database reason.
     */
    public function test_chatbot_answers_student_sick_reason_using_database_reason(): void
    {
        $teacher = User::factory()->create(['name' => 'Guru Pengajar', 'role' => 'teacher', 'status' => 'active']);
        Teacher::create(['user_id' => $teacher->id, 'nip' => '19880101']);

        $studentUser = User::factory()->create(['name' => 'Arya Pratama', 'role' => 'student', 'status' => 'active']);
        $student = Student::create(['user_id' => $studentUser->id, 'nis' => '1021', 'class' => 'XII-RPL 1']);

        // Create a sick attendance record with a specific reason
        $specificReason = 'Demam dan perlu istirahat 3 hari sesuai surat dokter';
        Attendance::create([
            'user_id' => $studentUser->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'attendance_time' => '07:00:00',
            'status' => 'sick',
            'reason' => $specificReason,
            'approval_status' => 'pending',
        ]);

        $response = $this->actingAs($teacher)->postJson(route('chatbot.ask'), [
            'message' => 'Arya sakit karena apa?',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $reply = $response->json('reply');

        // Must contain the actual database reason
        $this->assertStringContainsString($specificReason, $reply);
        $this->assertStringContainsString('Arya Pratama', $reply);
    }

    /**
     * Test chatbot does NOT mistake pending status for approved.
     */
    public function test_chatbot_distinguishes_pending_approval_status_from_approved(): void
    {
        $admin = User::factory()->create(['name' => 'Admin Sekolah', 'role' => 'admin', 'status' => 'active']);

        $studentUser = User::factory()->create(['name' => 'Arya Putra', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $studentUser->id, 'nis' => '1022', 'class' => 'XI-IPA']);

        Attendance::create([
            'user_id' => $studentUser->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'attendance_time' => '07:00:00',
            'status' => 'sick',
            'reason' => 'Radang tenggorokan',
            'approval_status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Apakah pengajuan sakit Arya sudah disetujui guru?',
        ]);

        $response->assertStatus(200);
        $reply = $response->json('reply');

        // Must explicitly state pending / menunggu persetujuan
        $this->assertMatchesRegularExpression('/(menunggu persetujuan|pending|belum disetujui)/i', $reply);
        $this->assertStringNotContainsString('telah disetujui', strtolower($reply));
    }

    /**
     * Test chatbot reports approved status and teacher name when approved.
     */
    public function test_chatbot_reports_approved_status_and_approver(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $teacherApprover = User::factory()->create(['name' => 'Pak Budi Hartono', 'role' => 'teacher', 'status' => 'active']);

        $studentUser = User::factory()->create(['name' => 'Arya Putra', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $studentUser->id, 'nis' => '1022', 'class' => 'XI-IPA']);

        Attendance::create([
            'user_id' => $studentUser->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'attendance_time' => '07:00:00',
            'status' => 'sick',
            'reason' => 'Flu dan pusing',
            'approval_status' => 'approved',
            'approved_by' => $teacherApprover->id,
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Apakah pengajuan sakit Arya sudah disetujui guru?',
        ]);

        $response->assertStatus(200);
        $reply = $response->json('reply');

        $this->assertMatchesRegularExpression('/(disetujui|telah disetujui)/i', $reply);
        $this->assertStringContainsString('Pak Budi Hartono', $reply);
    }

    /**
     * Test ambiguous student names generate clarification prompt with candidates.
     */
    public function test_chatbot_handles_ambiguous_student_names_with_clarification(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        // Create 2 students with same first name "Arya"
        $user1 = User::factory()->create(['name' => 'Arya Pratama', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $user1->id, 'nis' => '1021', 'class' => 'XII-RPL 1']);

        $user2 = User::factory()->create(['name' => 'Arya Wijaya', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $user2->id, 'nis' => '1045', 'class' => 'XI-TKJ 2']);

        $response = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Arya sakit karena apa?',
        ]);

        $response->assertStatus(200);
        $reply = $response->json('reply');

        // Clarification message with candidate details
        $this->assertStringContainsString('lebih dari satu siswa', $reply);
        $this->assertStringContainsString('Arya Pratama', $reply);
        $this->assertStringContainsString('XII-RPL 1', $reply);
        $this->assertStringContainsString('Arya Wijaya', $reply);
        $this->assertStringContainsString('XI-TKJ 2', $reply);
        $this->assertStringContainsString('sebutkan kelas atau NIS', $reply);
    }

    /**
     * Test ambiguous student name is automatically resolved if class is specified.
     */
    public function test_chatbot_resolves_ambiguous_student_when_class_specified(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $user1 = User::factory()->create(['name' => 'Arya Pratama', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $user1->id, 'nis' => '1021', 'class' => 'XII-RPL 1']);

        $user2 = User::factory()->create(['name' => 'Arya Wijaya', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $user2->id, 'nis' => '1045', 'class' => 'XI-TKJ 2']);

        Attendance::create([
            'user_id' => $user1->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'status' => 'sick',
            'reason' => 'Demam berdarah opname',
            'approval_status' => 'approved',
        ]);

        // User asks with class context
        $response = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Arya kelas XII-RPL 1 sakit karena apa?',
        ]);

        $response->assertStatus(200);
        $reply = $response->json('reply');

        $this->assertStringContainsString('Arya Pratama', $reply);
        $this->assertStringContainsString('Demam berdarah opname', $reply);
        $this->assertStringNotContainsString('Arya Wijaya', $reply);
    }

    /**
     * Test student without leave record does not produce fabricated answers.
     */
    public function test_chatbot_handles_student_without_leave_record_without_fabrication(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $studentUser = User::factory()->create(['name' => 'Reza Rahardian', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $studentUser->id, 'nis' => '1099', 'class' => 'X-A']);

        $response = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Kenapa Reza tidak masuk hari ini?',
        ]);

        $response->assertStatus(200);
        $reply = $response->json('reply');

        $this->assertStringContainsString('Tidak ada catatan absensi atau pengajuan', $reply);
        $this->assertStringContainsString('Reza Rahardian', $reply);
    }

    /**
     * Test student role cannot access other student private sick/leave reasons.
     */
    public function test_unauthorized_student_cannot_access_other_student_sick_reason(): void
    {
        $studentUserA = User::factory()->create(['name' => 'Siswa Pelapor', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $studentUserA->id, 'nis' => '5001', 'class' => 'X-A']);

        $studentUserB = User::factory()->create(['name' => 'Arya Pratama', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $studentUserB->id, 'nis' => '5002', 'class' => 'X-A']);

        Attendance::create([
            'user_id' => $studentUserB->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'status' => 'sick',
            'reason' => 'Operasi usus buntu',
            'approval_status' => 'approved',
        ]);

        // Student A queries Student B's sick reason
        $response = $this->actingAs($studentUserA)->postJson(route('chatbot.ask'), [
            'message' => 'Arya sakit karena apa?',
        ]);

        $response->assertStatus(200);
        $reply = $response->json('reply');

        // Must reject on privacy grounds without leaking reason
        $this->assertStringContainsString('privasi siswa', $reply);
        $this->assertStringNotContainsString('Operasi usus buntu', $reply);

        // Also test aggregate query attempt
        $resAggregate = $this->actingAs($studentUserA)->postJson(route('chatbot.ask'), [
            'message' => 'Siapa saja siswa yang sedang izin hari ini?',
        ]);
        $resAggregate->assertStatus(200);
        $this->assertStringContainsString('privasi siswa', $resAggregate->json('reply'));
    }

    /**
     * Test student can inquire about their own leave status.
     */
    public function test_student_can_ask_about_their_own_leave_status(): void
    {
        $studentUser = User::factory()->create(['name' => 'Dimas Anggara', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $studentUser->id, 'nis' => '5003', 'class' => 'XII-RPL']);

        Attendance::create([
            'user_id' => $studentUser->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'attendance_time' => '07:00:00',
            'status' => 'excused',
            'reason' => 'Menghadiri olimpiade matematika nasional',
            'approval_status' => 'pending',
        ]);

        $response = $this->actingAs($studentUser)->postJson(route('chatbot.ask'), [
            'message' => 'Apakah pengajuan izin saya sudah disetujui guru?',
        ]);

        $response->assertStatus(200);
        $reply = $response->json('reply');

        $this->assertStringContainsString('Menghadiri olimpiade matematika nasional', $reply);
        $this->assertMatchesRegularExpression('/(menunggu persetujuan|pending)/i', $reply);
    }

    /**
     * Test admin and teacher can query aggregate leaves today.
     */
    public function test_admin_and_teacher_can_query_aggregate_leaves_today(): void
    {
        $teacher = User::factory()->create(['name' => 'Guru Wali', 'role' => 'teacher', 'status' => 'active']);
        Teacher::create(['user_id' => $teacher->id, 'nip' => '19750505']);

        $u1 = User::factory()->create(['name' => 'Budi Santoso', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $u1->id, 'nis' => '7001', 'class' => 'XII-RPL 1']);

        $u2 = User::factory()->create(['name' => 'Citra Lestari', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $u2->id, 'nis' => '7002', 'class' => 'XII-RPL 1']);

        Attendance::create([
            'user_id' => $u1->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'status' => 'sick',
            'reason' => 'Demam tinggi',
            'approval_status' => 'pending',
        ]);

        Attendance::create([
            'user_id' => $u2->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'status' => 'excused',
            'reason' => 'Acara keluarga di luar kota',
            'approval_status' => 'approved',
        ]);

        // 1. "Siapa saja siswa yang sedang izin hari ini?"
        $resList = $this->actingAs($teacher)->postJson(route('chatbot.ask'), [
            'message' => 'Siapa saja siswa yang sedang izin hari ini?',
        ]);

        $resList->assertStatus(200);
        $replyList = $resList->json('reply');
        $this->assertStringContainsString('Budi Santoso', $replyList);
        $this->assertStringContainsString('Demam tinggi', $replyList);
        $this->assertStringContainsString('Citra Lestari', $replyList);
        $this->assertStringContainsString('Acara keluarga di luar kota', $replyList);

        // 2. "Berapa siswa yang izin hari ini?"
        $resCount = $this->actingAs($teacher)->postJson(route('chatbot.ask'), [
            'message' => 'Berapa siswa yang izin hari ini?',
        ]);

        $resCount->assertStatus(200);
        $this->assertStringContainsString('2 siswa', $resCount->json('reply'));
    }

    /**
     * Test admin can query leave reasons by class.
     */
    public function test_admin_can_query_class_leave_reasons(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $u1 = User::factory()->create(['name' => 'Dewi Persik', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $u1->id, 'nis' => '8001', 'class' => 'XII-RPL 1']);

        Attendance::create([
            'user_id' => $u1->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'status' => 'sick',
            'reason' => 'Sakit migrain berat',
            'approval_status' => 'approved',
        ]);

        $response = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Apa alasan izin siswa kelas XII-RPL 1?',
        ]);

        $response->assertStatus(200);
        $reply = $response->json('reply');
        $this->assertStringContainsString('Dewi Persik', $reply);
        $this->assertStringContainsString('Sakit migrain berat', $reply);
    }

    /**
     * Test chatbot sends grounded context to active AI provider and returns AI synthesis.
     */
    public function test_chatbot_synthesizes_with_active_ai_provider(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        // Configure OpenAI as active with API key
        AiSetting::where('provider', 'gemini')->update(['is_active' => false]);
        $openai = AiSetting::where('provider', 'openai')->first();
        $openai->update([
            'is_active' => true,
            'api_key' => 'sk-valid-mock-key-1234',
            'model' => 'gpt-6-luna',
        ]);

        $studentUser = User::factory()->create(['name' => 'Arya Pratama', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $studentUser->id, 'nis' => '1021', 'class' => 'XII-RPL 1']);

        Attendance::create([
            'user_id' => $studentUser->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'attendance_time' => '07:00:00',
            'status' => 'sick',
            'reason' => 'Demam berdarah dan harus dirawat di RS',
            'approval_status' => 'pending',
        ]);

        // Mock OpenAI API response
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Berdasarkan catatan presensi, Arya Pratama izin sakit karena demam berdarah dan harus dirawat di RS. Status pengajuannya saat ini masih menunggu persetujuan guru.',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Arya sakit karena apa?',
        ]);

        $response->assertStatus(200);
        $reply = $response->json('reply');

        $this->assertStringContainsString('Arya Pratama', $reply);
        $this->assertStringContainsString('demam berdarah', $reply);
        $this->assertStringContainsString('menunggu persetujuan guru', $reply);

        // Verify that OpenAI API was indeed called
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.openai.com')
                && str_contains($request->body(), 'Demam berdarah');
        });
    }

    /**
     * Test chatbot handles provider failure gracefully with reliable fallback.
     */
    public function test_chatbot_handles_provider_failure_gracefully_with_fallback(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $studentUser = User::factory()->create(['name' => 'Arya Pratama', 'role' => 'student', 'status' => 'active']);
        Student::create(['user_id' => $studentUser->id, 'nis' => '1021', 'class' => 'XII-RPL 1']);

        $realReason = 'Cedera engkel saat latihan olahraga';
        Attendance::create([
            'user_id' => $studentUser->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'attendance_time' => '07:00:00',
            'status' => 'sick',
            'reason' => $realReason,
            'approval_status' => 'pending',
        ]);

        // Configure Gemini with key, but mock an HTTP 500 server error
        $gemini = AiSetting::where('provider', 'gemini')->first();
        $gemini->update([
            'is_active' => true,
            'api_key' => 'fake-gemini-key',
            'model' => 'gemini-3.6-flash',
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'error' => ['message' => 'Internal Server Error'],
            ], 500),
        ]);

        $response = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Arya sakit karena apa?',
        ]);

        // Must still succeed (200) and return the deterministic database fact without hallucinating
        $response->assertStatus(200);
        $reply = $response->json('reply');

        $this->assertStringContainsString($realReason, $reply);
        $this->assertStringContainsString('Arya Pratama', $reply);
        $this->assertStringContainsString('menunggu persetujuan guru', $reply);
        $this->assertStringContainsString('layanan AI tidak dapat digunakan', $reply);
        $this->assertStringContainsString('Google Gemini', $reply);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'generativelanguage.googleapis.com'));
    }

    /**
     * Test chatbot reports a clear error instead of silently switching providers when no AI is configured.
     */
    public function test_chatbot_reports_clear_error_when_no_provider_is_configured(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        Http::fake();

        $response = $this->actingAs($admin)->postJson(route('chatbot.ask'), [
            'message' => 'Jelaskan manfaat fitur pengumuman sekolah di SmartAttendance',
        ]);

        $response->assertStatus(200)->assertJson([
            'success' => false,
            'intent' => 'ai_error',
        ]);

        $this->assertStringContainsString('Belum ada provider AI aktif', $response->json('reply'));

        Http::assertNothingSent();
    }
}
