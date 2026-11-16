<?php

namespace App\Services;

use App\Models\AiSetting;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Ai\AiException;
use App\Services\Ai\AiManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ChatbotPlusService
{
    /**
     * Create a new ChatbotPlusService instance.
     */
    public function __construct(
        protected ?AiManager $aiManager = null,
        protected ?StudentLeaveQueryService $leaveQueryService = null
    ) {
        $this->aiManager ??= new AiManager;
        $this->leaveQueryService ??= new StudentLeaveQueryService;
    }

    /**
     * Standard rejection response for off-topic or unsupported queries.
     */
    public const OFF_TOPIC_REJECTION = 'Maaf, Chatbot+ hanya dapat membantu pertanyaan seputar SmartAttendance, identitas akun, statistik dashboard, absensi, izin/sakit, dan panduan penggunaan aplikasi.';

    /**
     * Handle incoming question from authenticated user.
     *
     * @return array{success: bool, reply: string, intent: string}
     */
    public function handle(User $user, string $rawMessage): array
    {
        $message = trim($rawMessage);
        if ($message === '') {
            return [
                'success' => false,
                'reply' => 'Silakan ketik pertanyaan terlebih dahulu.',
                'intent' => 'empty',
            ];
        }

        $normalized = $this->normalizeText($message);

        // 1. Detect off-topic questions (coding, general knowledge, recipes, etc.)
        if ($this->isOffTopic($message) || $this->isOffTopic($normalized)) {
            return [
                'success' => true,
                'reply' => self::OFF_TOPIC_REJECTION,
                'intent' => 'off_topic',
            ];
        }

        // 2. Greetings
        if ($this->isGreeting($normalized)) {
            return [
                'success' => true,
                'reply' => $this->handleGreeting($user),
                'intent' => 'greeting',
            ];
        }

        // 3. User Identity Intent (All roles)
        if ($this->isIdentityQuery($normalized)) {
            return [
                'success' => true,
                'reply' => $this->handleIdentity($user, $normalized),
                'intent' => 'identity',
            ];
        }

        // 4. AI Model Information Intent (All roles)
        if ($this->isAiModelQuery($normalized) || $this->isAiModelQuery($message)) {
            return $this->handleAiModelInfoQuery();
        }

        // 5. Universal Attendance Database Queries (All roles with strict RBAC)
        $dbResult = $this->handleAttendanceDatabaseIntents($normalized, $message, $user);
        if ($dbResult !== null) {
            return $dbResult;
        }

        // 6. Role-specific handlers
        $role = $user->role;

        if ($role === 'admin') {
            $adminResult = $this->handleAdminQueries($normalized, $user);
            if ($adminResult !== null) {
                return $adminResult;
            }
        } elseif ($role === 'teacher') {
            $teacherResult = $this->handleTeacherQueries($normalized, $user);
            if ($teacherResult !== null) {
                return $teacherResult;
            }
        } elseif ($role === 'student') {
            $studentResult = $this->handleStudentQueries($normalized, $user);
            if ($studentResult !== null) {
                return $studentResult;
            }
        }

        // 5. Smart Student Leave & Absence Inquiries (Database-backed + Multi-Provider AI synthesis)
        if ($this->leaveQueryService->isLeaveOrAbsenceQuery($message) || $this->leaveQueryService->isLeaveOrAbsenceQuery($normalized)) {
            $leaveResult = $this->handleStudentLeaveInquiries($user, $message, $normalized);
            if ($leaveResult !== null) {
                return $leaveResult;
            }
        }

        // 6. General SmartAttendance system knowledge & guides (Camera, Face Scanner, Hours, Attendance Flow)
        $systemResult = $this->handleGeneralSystemQueries($normalized, $role);
        if ($systemResult !== null) {
            return $systemResult;
        }

        // 7. Smart AI Assistant for general SmartAttendance questions
        try {
            $smartAiReply = $this->handleSmartAssistantFallback($message, $user);
        } catch (AiException $e) {
            return [
                'success' => false,
                'reply' => $e->getMessage(),
                'intent' => 'ai_error',
            ];
        }

        if ($smartAiReply !== null) {
            return [
                'success' => true,
                'reply' => $smartAiReply,
                'intent' => 'smart_ai_assistant',
            ];
        }

        // 8. Fallback: query unsupported or not recognized - Friendly clarification
        return [
            'success' => true,
            'reply' => $this->buildClarificationReply($user),
            'intent' => 'clarification',
        ];
    }

    /**
     * Normalize text for flexible Indonesian matching.
     */
    protected function normalizeText(string $text): string
    {
        $lowered = mb_strtolower($text, 'UTF-8');
        // Remove common punctuation except letters and digits
        $cleaned = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $lowered) ?? $lowered;
        // Collapse multiple spaces
        $collapsed = preg_replace('/\s+/', ' ', $cleaned) ?? $cleaned;

        return trim($collapsed);
    }

    /**
     * Check if query is off-topic (coding, programming, cooking, entertainment, general homework, etc.).
     */
    protected function isOffTopic(string $text): bool
    {
        $patterns = [
            // Coding & Programming
            '/\b(buatkan|tuliskan|bikin|contoh)\s+(kode|script|koding|coding|program|skrip|fungsi|function|algoritma)\b/i',
            '/\b(kode|script|program|koding|coding|code)\s+(python|javascript|php|java|c\+\+|c\#|golang|rust|ruby|html|css|sql|react|vue|laravel)\b/i',
            '/\b(python|javascript|php|java|c\+\+|c\#|golang|html|css|sql)\s+(script|code|program|function)\b/i',
            '/\b(write|generate|create)\s+(code|script|program)\b/i',
            '/\b(buatkan|bikin)\s+(web|aplikasi|database)\s+(baru|lain)\b/i',
            '/\b(syntax|sintaks|debug|compile|compiler)\b/i',
            // Recipes & cooking
            '/\b(resep|masak|memasak|bumbu|kue|kuliner)\b/i',
            // Entertainment, poems, songs
            '/\b(puisi|pantun|cerpen|dongeng|lelucon|jokes|stand up|lirik lagu|chord)\b/i',
            '/\b(rekomendasi film|sinopsis film|jadwal bioskop)\b/i',
            '/\b(ramalan zodiak|horoskop)\b/i',
            // Non-system school subjects / math / science homework
            '/\b(tugas matematika|hitung kalkulus|rumus fisika|pr kimia|tugas biologi|aljabar)\b/i',
            '/\b(hitung|kalkulasi)?\s*\d+\s*[\+\-\*\/]\s*\d+\b/i',
            '/\b(hitung\s+\d+)\b/i',
            '/\b(terjemahkan teks|translate ini)\b/i',
            '/\b(buatkan esai|tulis artikel|buat cerita|tuliskan cerita)\b/i',
            '/\b(presiden|pemilu|politik|berita hari ini|cuaca hari ini)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if text is a greeting.
     */
    protected function isGreeting(string $text): bool
    {
        return (bool) preg_match('/^(hai|halo|hello|hi|hey|pagi|siang|sore|malam|selamat pagi|selamat siang|selamat sore|selamat malam|assalamu ?alaikum|ping|p)$/i', $text);
    }

    /**
     * Handle greeting based on user role and name.
     */
    protected function handleGreeting(User $user): string
    {
        return match ($user->role) {
            'admin' => 'Halo Administrator! Saya Chatbot+, asisten virtual SmartAttendance. Saya siap membantu menampilkan statistik kehadiran terkini atau panduan menu administrator.',
            'teacher' => "Halo Bapak/Ibu {$user->name}! Saya Chatbot+, asisten guru SmartAttendance. Saya dapat membantu memeriksa kehadiran siswa, pengajuan izin/sakit, atau panduan portal guru.",
            'student' => "Halo {$user->name}! Saya Chatbot+, asisten presensi kamu. Ada yang ingin kamu tanyakan mengenai status absensi hari ini, kehadiran, atau izin?",
            default => "Halo {$user->name}! Saya Chatbot+ SmartAttendance. Ada yang bisa saya bantu terkait sistem absensi sekolah?",
        };
    }

    /**
     * Check if query is asking for user identity or role.
     */
    protected function isIdentityQuery(string $text): bool
    {
        $patterns = [
            '/\b(siapa saya|siapa aku|siapakah saya|siapakah aku|aku ini siapa|saya ini siapa|saya siapa|aku siapa)\b/i',
            '/\b(nama saya siapa|nama aku siapa|siapa nama saya|siapa nama aku|nama saya|nama aku|namaku siapa|siapa namaku)\b/i',
            '/\b(akun saya apa|akun aku apa|akun saya|akun aku|user saya|username saya)\b/i',
            '/\b(identitas saya|identitas aku|data saya|profil saya)\b/i',
            '/\b(role saya|peran saya|status akun saya|login sebagai apa|posisi saya)\b/i',
            '/\b(apa peran akun saya|apa peran saya|peran akun saya|role akun saya)\b/i',
            '/\b(siapa yang sedang login|siapa yang login|user login)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle identity query strictly based on authenticated user session.
     */
    protected function handleIdentity(User $user, string $text = ''): string
    {
        $name = trim((string) ($user->name ?? ''));
        $role = $user->role ?? '';

        $roleLabel = match ($role) {
            'admin' => 'administrator',
            'teacher' => 'guru',
            'student' => 'siswa',
            default => 'pengguna',
        };

        // If asking specifically about role/peran
        if (preg_match('/\b(peran|role)\b/i', $text)) {
            $roleTitle = match ($role) {
                'admin' => 'Administrator',
                'teacher' => 'Guru',
                'student' => 'Siswa',
                default => 'Pengguna',
            };

            return "Peran akun kamu adalah {$roleTitle} pada sistem SmartAttendance.";
        }

        if ($name !== '') {
            return "Kamu adalah {$name}, {$roleLabel} SmartAttendance.";
        }

        return "Kamu adalah {$roleLabel} SmartAttendance.";
    }

    /**
     * Check if query is asking about the AI model, provider, or AI identity.
     */
    protected function isAiModelQuery(string $text): bool
    {
        $patterns = [
            '/\b(model|provider|mesin|versi)\s+(ai|chatbot|kecerdasan buatan)\b/iu',
            '/\b(ai|chatbot)\s+(apa|yang mana)\s+(yang\s+)?(kamu\s+)?(gunakan|pakai|dukung)\b/iu',
            '/\b(apa\s+model\s+ai|apa\s+nama\s+model\s+ai)\b/iu',
            '/\bkamu\s+(menggunakan|pake|pakai)\s+(ai|model|gemini|openai|nvidia)\b/iu',
            '/\bkamu\s+(ai|chatbot)\s+apa\b/iu',
            '/\bai\s+apa\s+yang\s+(mendukung|dipakai|digunakan)\b/iu',
            '/\b(menggunakan|pake|pakai)\s+(gemini|openai|nvidia)\b/iu',
            '/\b(apakah\s+kamu|kamu)\s+(gemini|openai|chatgpt)\b/iu',
            '/\b(siapa|apa)\s+ai\s+kamu\b/iu',
            '/\bkamu\s+dibuat\s+pakai\s+(model|ai)\s+apa\b/iu',
            '/\bmodel\s+apa\s+(yang\s+)?(digunakan|dipakai)\b/iu',
            '/\b(model|provider)\s+apa\b/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle AI model info query by dynamically reading from active backend settings.
     *
     * @return array{success: bool, reply: string, intent: string}
     */
    protected function handleAiModelInfoQuery(): array
    {
        $active = AiSetting::getActive();

        if ($active && $active->provider) {
            $providerLabel = AiSetting::providerLabel($active->provider);
            $modelName = $active->model ?: AiSetting::defaultModel($active->provider);

            if ($modelName !== '') {
                $reply = "Saya menggunakan {$providerLabel} (model {$modelName}) sebagai model AI yang mendukung Chatbot+ SmartAttendance.";
            } else {
                $reply = "Saya menggunakan {$providerLabel} sebagai model AI yang mendukung Chatbot+ SmartAttendance.";
            }
        } else {
            $reply = 'Saya menggunakan model AI backend SmartAttendance yang terhubung melalui konfigurasi server aplikasi.';
        }

        return [
            'success' => true,
            'reply' => $reply,
            'intent' => 'ai_model_info',
        ];
    }

    /**
     * Handle high-priority universal attendance database queries with role-based access control.
     *
     * @return array{success: bool, reply: string, intent: string}|null
     */
    protected function handleAttendanceDatabaseIntents(string $normalized, string $rawMessage, User $user): ?array
    {
        $today = Carbon::today()->toDateString();
        $isStudent = ($user->role === 'student');

        // 1. Keterlambatan Siswa (List & Count)
        $isLateListQuery = (bool) (
            preg_match('/\b(siapa|daftar|list|tampilkan|nama)\s+.*(terlambat|telat|kesiangan)\b/iu', $normalized)
            || preg_match('/\b(terlambat|telat|kesiangan)\s+(hari ini\s+)?(siapa|ada siapa|daftar)\b/iu', $normalized)
            || preg_match('/\b(siapa\s+(saja|aja)\s+(yang\s+)?(terlambat|telat|kesiangan))\b/iu', $normalized)
            || preg_match('/\b(ada\s+(siswa|murid|orang)\s+(yang\s+)?(terlambat|telat))\b/iu', $normalized)
            || preg_match('/\b(daftar|list)\s+keterlambatan\b/iu', $normalized)
        );

        $isLateCountQuery = (bool) (
            preg_match('/\b(berapa|jumlah|total)\s+(total\s+)?(jumlah\s+)?(siswa|murid|orang)?\s*(yang\s+)?(terlambat|telat|kesiangan)\b/iu', $normalized)
            || preg_match('/\b(total|jumlah)\s+(siswa|murid|orang)?\s*(terlambat|telat)\b/iu', $normalized)
        );

        if ($isLateListQuery && ! $isLateCountQuery) {
            if ($isStudent) {
                return [
                    'success' => true,
                    'reply' => 'Demi menjaga privasi siswa, daftar keterlambatan siswa lain tidak dapat ditampilkan. Kamu dapat memeriksa status absensi atau riwayat keterlambatan kamu sendiri.',
                    'intent' => 'privacy_restricted',
                ];
            }

            return [
                'success' => true,
                'reply' => $this->buildLateStudentsReply($today),
                'intent' => 'attendance_late_students_list',
            ];
        }

        if ($isLateCountQuery) {
            if ($isStudent) {
                $myLateCount = Attendance::where('user_id', $user->id)->where('status', 'late')->count();

                return [
                    'success' => true,
                    'reply' => "Demi privasi data sekolah, statistik seluruh siswa hanya dapat diakses oleh Administrator dan Guru. Jumlah keterlambatan kamu sendiri adalah {$myLateCount} kali.",
                    'intent' => 'privacy_restricted',
                ];
            }

            $count = Attendance::whereDate('attendance_date', $today)
                ->where('status', 'late')
                ->whereHas('user', fn ($q) => $q->where('role', 'student'))
                ->count();

            return [
                'success' => true,
                'reply' => "Total siswa yang terlambat hari ini adalah {$count} orang.",
                'intent' => 'attendance_late_students_count',
            ];
        }

        // 2. Kehadiran Siswa Hari Ini (List & Count)
        $isPresentListQuery = (bool) (
            preg_match('/\b(siapa|daftar|list|tampilkan|nama)\s+.*(hadir|masuk)\b/iu', $normalized)
            || preg_match('/\b(hadir|masuk)\s+(hari ini\s+)?(siapa|ada siapa|daftar)\b/iu', $normalized)
            || preg_match('/\b(siapa\s+(saja|aja)\s+(yang\s+)?(sudah\s+)?(hadir|masuk|absen))\b/iu', $normalized)
            || preg_match('/\b(siapa\s+yang\s+sudah\s+hadir)\b/iu', $normalized)
        );

        $isPresentCountQuery = (bool) (
            preg_match('/\b(berapa|jumlah|total)\s+(total\s+)?(jumlah\s+)?(siswa|murid|orang)?\s*(yang\s+)?(sudah\s+)?(hadir|masuk)\b/iu', $normalized)
        );

        if ($isPresentListQuery && ! $isPresentCountQuery && ! preg_match('/\bguru\b/iu', $normalized)) {
            if ($isStudent) {
                return [
                    'success' => true,
                    'reply' => 'Demi menjaga privasi siswa, data kehadiran siswa lain tidak dapat ditampilkan. Kamu dapat memeriksa status kehadiran kamu sendiri.',
                    'intent' => 'privacy_restricted',
                ];
            }

            return [
                'success' => true,
                'reply' => $this->buildPresentStudentsReply($today),
                'intent' => 'attendance_present_students_list',
            ];
        }

        if ($isPresentCountQuery && ! preg_match('/\bguru\b/iu', $normalized)) {
            if ($isStudent) {
                $myPresentCount = Attendance::where('user_id', $user->id)->whereIn('status', ['present', 'late'])->count();

                return [
                    'success' => true,
                    'reply' => "Demi menjaga privasi sekolah, total kehadiran seluruh siswa hanya dapat diakses oleh Administrator dan Guru. Jumlah kehadiran kamu sendiri adalah {$myPresentCount} kali.",
                    'intent' => 'privacy_restricted',
                ];
            }

            $count = Attendance::whereDate('attendance_date', $today)
                ->whereIn('status', ['present', 'late'])
                ->whereHas('user', fn ($q) => $q->where('role', 'student'))
                ->count();

            return [
                'success' => true,
                'reply' => "Total siswa yang sudah hadir hari ini adalah {$count} orang.",
                'intent' => 'attendance_present_students_count',
            ];
        }

        // 3. Siswa yang Belum Absen Hari Ini (List & Count)
        $isUnattendedListQuery = (bool) (
            preg_match('/\b(siapa|daftar|list|tampilkan|nama)\s+.*belum\s+(absen|presensi|masuk|hadir)\b/iu', $normalized)
            || preg_match('/\bbelum\s+(absen|presensi|masuk|hadir)\s+(hari ini\s+)?(siapa|ada siapa|daftar)\b/iu', $normalized)
            || preg_match('/\b(siapa\s+(saja|aja)\s+(yang\s+)?belum\s+(absen|presensi|hadir))\b/iu', $normalized)
            || preg_match('/\bsiswa\s+mana\s+(yang\s+)?belum\s+(absen|presensi)\b/iu', $normalized)
        );

        $isUnattendedCountQuery = (bool) (
            preg_match('/\b(berapa|jumlah|total)\s+(total\s+)?(jumlah\s+)?(siswa|murid|orang)?\s*(yang\s+)?belum\s+(absen|presensi|masuk|hadir)\b/iu', $normalized)
        );

        if ($isUnattendedListQuery && ! $isUnattendedCountQuery && ! preg_match('/\bguru\b/iu', $normalized)) {
            if ($isStudent) {
                return [
                    'success' => true,
                    'reply' => 'Demi menjaga privasi siswa, daftar siswa yang belum absen hanya dapat diakses oleh Administrator dan Guru. Kamu dapat memeriksa status absensi kamu sendiri.',
                    'intent' => 'privacy_restricted',
                ];
            }

            return [
                'success' => true,
                'reply' => $this->buildUnattendedStudentsReply($today),
                'intent' => 'attendance_unattended_students_list',
            ];
        }

        if ($isUnattendedCountQuery && ! preg_match('/\bguru\b/iu', $normalized)) {
            if ($isStudent) {
                return [
                    'success' => true,
                    'reply' => 'Informasi siswa yang belum absen hanya dapat diakses oleh Administrator dan Guru. Kamu dapat memeriksa status absensi kamu sendiri.',
                    'intent' => 'privacy_restricted',
                ];
            }

            $attendedStudentUserIds = Attendance::whereDate('attendance_date', $today)
                ->whereHas('user', fn ($q) => $q->where('role', 'student'))
                ->pluck('user_id');

            $count = Student::whereHas('user', fn ($q) => $q->where('status', 'active'))
                ->whereNotIn('user_id', $attendedStudentUserIds)
                ->count();

            return [
                'success' => true,
                'reply' => "Jumlah siswa yang belum melakukan absensi hari ini adalah {$count} orang.",
                'intent' => 'attendance_unattended_students_count',
            ];
        }

        // 4. Siswa yang Izin dan Sakit Hari Ini (List & Count)
        if (preg_match('/\b(siapa|daftar|list|tampilkan|nama)\s+.*(izin|ijin|sakit)\b/iu', $normalized)
            || preg_match('/\b(siapa\s+(saja|aja)\s+(yang\s+)?(izin|ijin|sakit))\b/iu', $normalized)
            || preg_match('/\b(siapa\s+(saja|aja)\s+(yang\s+)?tidak\s+masuk)\b/iu', $normalized)) {
            if ($isStudent) {
                return [
                    'success' => true,
                    'reply' => 'Demi menjaga privasi siswa, informasi izin dan sakit siswa lain tidak dapat diakses. Kamu hanya dapat melihat data kehadiran dan izin kamu sendiri.',
                    'intent' => 'privacy_restricted',
                ];
            }

            return [
                'success' => true,
                'reply' => $this->buildLeavesTodayReply($today),
                'intent' => 'attendance_leaves_today_list',
            ];
        }

        // 5. Siswa Hadir Tepat Waktu
        if (preg_match('/\b(siapa|daftar|list)\s+.*(tepat waktu|on time)\b/iu', $normalized)
            || preg_match('/\b(siapa\s+(saja|aja)\s+(yang\s+)?(hadir\s+)?(tepat waktu|on time))\b/iu', $normalized)) {
            if ($isStudent) {
                $myOntime = Attendance::where('user_id', $user->id)->where('status', 'present')->count();

                return [
                    'success' => true,
                    'reply' => "Jumlah kehadiran tepat waktu kamu adalah {$myOntime} kali.",
                    'intent' => 'student_total_present',
                ];
            }

            return [
                'success' => true,
                'reply' => $this->buildOntimeStudentsReply($today),
                'intent' => 'attendance_ontime_students_list',
            ];
        }

        // 6. Siswa yang Datang Paling Awal
        if (preg_match('/\b(siapa\s+(yang\s+)?(datang|hadir|absen)\s+(paling\s+awal|paling\s+pagi|pertama|terawal))\b/iu', $normalized)
            || preg_match('/\b(siapa\s+paling\s+(awal|pagi)\s+(datang|hadir|absen))\b/iu', $normalized)
            || preg_match('/\b(siapa\s+yang\s+paling\s+awal|siapa\s+yang\s+paling\s+pagi)\b/iu', $normalized)) {
            if ($isStudent) {
                return [
                    'success' => true,
                    'reply' => 'Informasi waktu presensi seluruh siswa hanya dapat diakses oleh Administrator dan Guru. Kamu dapat melihat jam presensi kamu sendiri di dashboard.',
                    'intent' => 'privacy_restricted',
                ];
            }

            return [
                'success' => true,
                'reply' => $this->buildEarliestStudentReply($today),
                'intent' => 'attendance_earliest_student',
            ];
        }

        // 7. Total Siswa & Total Guru
        if (preg_match('/\b(berapa\s+total\s+siswa|total\s+siswa|jumlah\s+siswa|banyak\s+siswa|jumlah\s+murid|total\s+murid)\b/iu', $normalized)
            && ! preg_match('/\bterlambat|telat|hadir|absen|izin|sakit\b/iu', $normalized)) {
            $totalStudents = Student::count();

            return [
                'success' => true,
                'reply' => "Total siswa terdaftar saat ini adalah {$totalStudents} siswa.",
                'intent' => 'admin_total_students',
            ];
        }

        if (preg_match('/\b(berapa\s+total\s+guru|total\s+guru|jumlah\s+guru|banyak\s+guru|total\s+pengajar|jumlah\s+pengajar)\b/iu', $normalized)
            && ! preg_match('/\bterlambat|telat|hadir|absen|izin|sakit\b/iu', $normalized)) {
            $totalTeachers = Teacher::count();

            return [
                'success' => true,
                'reply' => "Total guru terdaftar saat ini adalah {$totalTeachers} guru.",
                'intent' => 'admin_total_teachers',
            ];
        }

        // 8. Tingkat Kehadiran Hari Ini
        if (preg_match('/\b(tingkat|persen|persentase)\s+kehadiran(\s+hari ini)?\b/iu', $normalized)) {
            if ($isStudent) {
                $totalPresent = Attendance::where('user_id', $user->id)->whereIn('status', ['present', 'late'])->count();
                $all = Attendance::where('user_id', $user->id)->count();
                $rate = $all > 0 ? round(($totalPresent / $all) * 100, 1) : 100.0;

                return [
                    'success' => true,
                    'reply' => "Tingkat kehadiran kamu saat ini adalah {$rate}%.",
                    'intent' => 'student_attendance_rate',
                ];
            }

            $totalActive = Student::whereHas('user', fn ($q) => $q->where('status', 'active'))->count();
            $presentCount = Attendance::whereDate('attendance_date', $today)
                ->whereIn('status', ['present', 'late'])
                ->whereHas('user', fn ($q) => $q->where('role', 'student'))
                ->count();

            $rate = $totalActive > 0 ? round(($presentCount / $totalActive) * 100, 1) : 0.0;

            return [
                'success' => true,
                'reply' => "Tingkat kehadiran siswa hari ini adalah {$rate}% ({$presentCount} dari {$totalActive} siswa aktif hadir).",
                'intent' => 'attendance_rate_today',
            ];
        }

        // 9. Pertanyaan Mengenai Siswa Tertentu
        $studentSearch = $this->handleSpecificStudentSearch($normalized, $rawMessage, $user, $today);
        if ($studentSearch !== null) {
            return $studentSearch;
        }

        // 10. Guru Queries
        if (preg_match('/\bguru\b/iu', $normalized)) {
            if (preg_match('/\b(siapa|daftar|list)\s+.*(hadir|masuk)\b/iu', $normalized) || preg_match('/\bguru\s+hadir\b/iu', $normalized)) {
                if ($isStudent) {
                    return [
                        'success' => true,
                        'reply' => 'Data presensi guru hanya dapat diakses oleh Administrator dan Guru.',
                        'intent' => 'privacy_restricted',
                    ];
                }

                return [
                    'success' => true,
                    'reply' => $this->buildTeachersPresentReply($today),
                    'intent' => 'teachers_present_today',
                ];
            }

            if (preg_match('/\b(terlambat|telat)\b/iu', $normalized)) {
                if ($isStudent) {
                    return [
                        'success' => true,
                        'reply' => 'Data presensi guru hanya dapat diakses oleh Administrator dan Guru.',
                        'intent' => 'privacy_restricted',
                    ];
                }

                return [
                    'success' => true,
                    'reply' => $this->buildTeachersLateReply($today),
                    'intent' => 'teachers_late_today',
                ];
            }

            if (preg_match('/\bbelum\s+(absen|presensi|masuk|hadir)\b/iu', $normalized)) {
                if ($isStudent) {
                    return [
                        'success' => true,
                        'reply' => 'Data presensi guru hanya dapat diakses oleh Administrator dan Guru.',
                        'intent' => 'privacy_restricted',
                    ];
                }

                return [
                    'success' => true,
                    'reply' => $this->buildTeachersUnattendedReply($today),
                    'intent' => 'teachers_unattended_today',
                ];
            }
        }

        return null;
    }

    /**
     * Search specific student by name and query their status today.
     *
     * @return array{success: bool, reply: string, intent: string}|null
     */
    protected function handleSpecificStudentSearch(string $normalized, string $rawMessage, User $user, string $today): ?array
    {
        $targetName = null;
        $intentType = null;

        if (preg_match('/\bapakah\s+(.+?)\s+(sudah\s+)?(hadir|absen|masuk)\b/iu', $rawMessage, $m)) {
            $targetName = trim($m[1]);
            $intentType = 'present_check';
        } elseif (preg_match('/\bapakah\s+(.+?)\s+(terlambat|telat)\b/iu', $rawMessage, $m)) {
            $targetName = trim($m[1]);
            $intentType = 'late_check';
        } elseif (preg_match('/\bjam\s+berapa\s+(.+?)\s+(melakukan\s+)?(absensi|absen|presensi)\b/iu', $rawMessage, $m)) {
            $targetName = trim($m[1]);
            $intentType = 'time_check';
        } elseif (preg_match('/\b(bagaimana\s+)?riwayat\s+kehadiran\s+(.+?)\b/iu', $rawMessage, $m)) {
            $targetName = trim($m[2]);
            $intentType = 'history_check';
        }

        if ($targetName === null) {
            return null;
        }

        // Clean honorifics / generic words
        $cleanName = trim(preg_replace('/^(siswa|murid|saudara|ananda|pak|ibu|bapak|bu)\s+/i', '', $targetName));
        if ($cleanName === '' || in_array(mb_strtolower($cleanName, 'UTF-8'), ['saya', 'aku', 'kami', 'anda', 'kamu', 'semua', 'guru', 'siswa'], true)) {
            return null;
        }

        // Look for student user
        $targetUser = User::where('role', 'student')
            ->where(function ($q) use ($cleanName) {
                $q->where('name', 'like', "%{$cleanName}%");
            })
            ->first();

        // If not found, try searching by first word
        if (! $targetUser && str_contains($cleanName, ' ')) {
            $firstWord = explode(' ', $cleanName)[0];
            if (strlen($firstWord) >= 3) {
                $targetUser = User::where('role', 'student')
                    ->where('name', 'like', "%{$firstWord}%")
                    ->first();
            }
        }

        // If not student, check teacher
        if (! $targetUser) {
            $targetTeacher = User::where('role', 'teacher')
                ->where('name', 'like', "%{$cleanName}%")
                ->first();

            if ($targetTeacher) {
                if ($user->role === 'student') {
                    return [
                        'success' => true,
                        'reply' => 'Informasi presensi guru hanya dapat diakses oleh Administrator dan Guru.',
                        'intent' => 'privacy_restricted',
                    ];
                }

                $teacherAtt = Attendance::where('user_id', $targetTeacher->id)->whereDate('attendance_date', $today)->first();
                $nipStr = $targetTeacher->teacher?->nip ? " (NIP: {$targetTeacher->teacher->nip})" : '';
                if ($intentType === 'present_check' || $intentType === 'late_check' || $intentType === 'time_check') {
                    if ($teacherAtt) {
                        $time = $teacherAtt->attendance_time ? Carbon::parse($teacherAtt->attendance_time)->format('H.i').' WIB' : '';
                        $statusLabel = match ($teacherAtt->status) {
                            'present' => 'Hadir Tepat Waktu',
                            'late' => 'Hadir Terlambat',
                            'excused' => "Izin ({$teacherAtt->reason})",
                            'sick' => "Sakit ({$teacherAtt->reason})",
                            default => $teacherAtt->status,
                        };

                        return [
                            'success' => true,
                            'reply' => "Guru {$targetTeacher->name}{$nipStr} tercatat {$statusLabel} hari ini pada pukul {$time}.",
                            'intent' => 'teacher_status_check',
                        ];
                    }

                    return [
                        'success' => true,
                        'reply' => "Guru {$targetTeacher->name}{$nipStr} belum melakukan absensi hari ini.",
                        'intent' => 'teacher_status_check',
                    ];
                }
            }

            return [
                'success' => true,
                'reply' => "Siswa atau guru dengan nama '{$cleanName}' tidak ditemukan di database SmartAttendance. Pastikan ejaan nama sudah benar.",
                'intent' => 'student_not_found',
            ];
        }

        // Privacy check for student role
        if ($user->role === 'student' && $targetUser->id !== $user->id) {
            return [
                'success' => true,
                'reply' => 'Demi menjaga privasi siswa, kamu tidak dapat memeriksa status kehadiran siswa lain. Kamu hanya dapat memeriksa status absensi kamu sendiri.',
                'intent' => 'privacy_restricted',
            ];
        }

        $class = $targetUser->student?->class ? "Kelas {$targetUser->student->class}" : 'Kelas -';
        $att = Attendance::where('user_id', $targetUser->id)
            ->whereDate('attendance_date', $today)
            ->first();

        if ($intentType === 'present_check') {
            if ($att && in_array($att->status, ['present', 'late'])) {
                $time = $att->attendance_time ? Carbon::parse($att->attendance_time)->format('H.i').' WIB' : '';
                $statusLabel = $att->status === 'late' ? 'status Terlambat' : 'tepat waktu';

                return [
                    'success' => true,
                    'reply' => "Ya, {$targetUser->name} ({$class}) sudah hadir hari ini pada pukul {$time} dengan {$statusLabel}.",
                    'intent' => 'specific_student_present',
                ];
            } elseif ($att && in_array($att->status, ['excused', 'sick'])) {
                $statusLabel = $att->status === 'sick' ? 'Sakit' : 'Izin';

                return [
                    'success' => true,
                    'reply' => "Siswa {$targetUser->name} ({$class}) tidak hadir hari ini karena tercatat {$statusLabel} ({$att->reason}).",
                    'intent' => 'specific_student_present',
                ];
            }

            return [
                'success' => true,
                'reply' => "Siswa {$targetUser->name} ({$class}) belum melakukan absensi hari ini.",
                'intent' => 'specific_student_present',
            ];
        }

        if ($intentType === 'late_check') {
            if ($att && $att->status === 'late') {
                $time = $att->attendance_time ? Carbon::parse($att->attendance_time)->format('H.i').' WIB' : '';

                return [
                    'success' => true,
                    'reply' => "Ya, {$targetUser->name} ({$class}) tercatat terlambat hari ini pada pukul {$time}.",
                    'intent' => 'specific_student_late',
                ];
            } elseif ($att && $att->status === 'present') {
                $time = $att->attendance_time ? Carbon::parse($att->attendance_time)->format('H.i').' WIB' : '';

                return [
                    'success' => true,
                    'reply' => "Tidak, {$targetUser->name} ({$class}) hadir tepat waktu hari ini pada pukul {$time}.",
                    'intent' => 'specific_student_late',
                ];
            }

            return [
                'success' => true,
                'reply' => "Siswa {$targetUser->name} ({$class}) belum melakukan absensi hari ini.",
                'intent' => 'specific_student_late',
            ];
        }

        if ($intentType === 'time_check') {
            if ($att && $att->attendance_time) {
                $time = Carbon::parse($att->attendance_time)->format('H.i').' WIB';
                $statusLabel = $att->status === 'late' ? 'Terlambat' : ($att->status === 'present' ? 'Tepat Waktu' : $att->status);

                return [
                    'success' => true,
                    'reply' => "{$targetUser->name} melakukan absensi hari ini pada pukul {$time} dengan status {$statusLabel}.",
                    'intent' => 'specific_student_time',
                ];
            }

            return [
                'success' => true,
                'reply' => "Siswa {$targetUser->name} ({$class}) belum melakukan absensi hari ini, sehingga belum ada catatan jam kehadiran.",
                'intent' => 'specific_student_time',
            ];
        }

        if ($intentType === 'history_check') {
            $pCount = Attendance::where('user_id', $targetUser->id)->where('status', 'present')->count();
            $lCount = Attendance::where('user_id', $targetUser->id)->where('status', 'late')->count();
            $eCount = Attendance::where('user_id', $targetUser->id)->where('status', 'excused')->count();
            $sCount = Attendance::where('user_id', $targetUser->id)->where('status', 'sick')->count();

            return [
                'success' => true,
                'reply' => "Riwayat kehadiran {$targetUser->name} ({$class}): Hadir Tepat Waktu {$pCount} kali, Terlambat {$lCount} kali, Izin {$eCount} kali, dan Sakit {$sCount} kali.",
                'intent' => 'specific_student_history',
            ];
        }

        return null;
    }

    /**
     * Build formatted list of students who were late today.
     */
    protected function buildLateStudentsReply(string $today): string
    {
        $late = Attendance::with('user.student')
            ->whereDate('attendance_date', $today)
            ->where('status', 'late')
            ->whereHas('user', fn ($q) => $q->where('role', 'student'))
            ->orderBy('attendance_time', 'asc')
            ->get();

        if ($late->isEmpty()) {
            return 'Tidak ada siswa yang terlambat hari ini. Semua siswa yang hadir datang tepat waktu.';
        }

        $lines = ['Berikut daftar siswa yang terlambat hari ini:', ''];
        $idx = 1;
        foreach ($late as $att) {
            $name = $att->user?->name ?? 'Siswa';
            $time = $att->attendance_time ? Carbon::parse($att->attendance_time)->format('H.i').' WIB' : 'Waktu tidak tercatat';
            $lines[] = "{$idx}. {$name} — {$time}";
            $idx++;
        }

        $lines[] = '';
        $lines[] = 'Total siswa terlambat: '.$late->count().' orang.';

        return implode("\n", $lines);
    }

    /**
     * Build formatted list of students who are present today.
     */
    protected function buildPresentStudentsReply(string $today): string
    {
        $present = Attendance::with('user.student')
            ->whereDate('attendance_date', $today)
            ->whereIn('status', ['present', 'late'])
            ->whereHas('user', fn ($q) => $q->where('role', 'student'))
            ->orderBy('attendance_time', 'asc')
            ->get();

        if ($present->isEmpty()) {
            return 'Belum ada siswa yang tercatat hadir hari ini.';
        }

        $lines = ['Berikut daftar siswa yang sudah hadir hari ini:', ''];
        $idx = 1;
        foreach ($present as $att) {
            $name = $att->user?->name ?? 'Siswa';
            $time = $att->attendance_time ? Carbon::parse($att->attendance_time)->format('H.i').' WIB' : '';
            $statusLabel = $att->status === 'late' ? 'Terlambat' : 'Tepat Waktu';
            $timeInfo = $time ? " — {$time} ({$statusLabel})" : " ({$statusLabel})";
            $lines[] = "{$idx}. {$name}{$timeInfo}";
            $idx++;
        }

        $lines[] = '';
        $lines[] = 'Total siswa hadir: '.$present->count().' orang.';

        return implode("\n", $lines);
    }

    /**
     * Build formatted list of active students who haven't attended today.
     */
    protected function buildUnattendedStudentsReply(string $today): string
    {
        $attendedStudentUserIds = Attendance::whereDate('attendance_date', $today)
            ->whereHas('user', fn ($q) => $q->where('role', 'student'))
            ->pluck('user_id');

        $unattended = Student::with('user')
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->whereNotIn('user_id', $attendedStudentUserIds)
            ->get()
            ->sortBy(fn ($s) => mb_strtolower($s->user?->name ?? '', 'UTF-8'));

        if ($unattended->isEmpty()) {
            return 'Semua siswa aktif telah melakukan absensi hari ini. Tidak ada siswa yang belum absen.';
        }

        $lines = ['Berikut daftar siswa yang belum melakukan absensi hari ini:', ''];
        $idx = 1;
        foreach ($unattended as $s) {
            $name = $s->user?->name ?? 'Siswa';
            $class = $s->class ? "Kelas {$s->class}" : 'Kelas -';
            $lines[] = "{$idx}. {$name} ({$class})";
            $idx++;
        }

        $lines[] = '';
        $lines[] = 'Total siswa belum absen: '.$unattended->count().' orang.';

        return implode("\n", $lines);
    }

    /**
     * Build formatted list of students who have leave/sick records today.
     */
    protected function buildLeavesTodayReply(string $today): string
    {
        $leaves = Attendance::with('user.student')
            ->whereDate('attendance_date', $today)
            ->whereIn('status', ['excused', 'sick'])
            ->whereHas('user', fn ($q) => $q->where('role', 'student'))
            ->get();

        if ($leaves->isEmpty()) {
            return 'Tidak ada siswa yang tercatat izin atau sakit hari ini.';
        }

        $lines = ['Daftar Siswa yang Izin atau Sakit Hari Ini:', ''];
        $idx = 1;
        foreach ($leaves as $item) {
            $name = $item->user?->name ?? 'Siswa';
            $class = $item->user?->student?->class ? "Kelas {$item->user->student->class}" : '';
            $classStr = $class ? " ({$class})" : '';
            $type = $item->status === 'sick' ? 'Sakit' : 'Izin';
            $reason = $item->reason ? "\"{$item->reason}\"" : 'Keterangan tidak dicantumkan';
            $approval = $item->getApprovalStatusLabel();
            $lines[] = "{$idx}. {$name}{$classStr} - {$type} ({$reason}, Status: {$approval})";
            $idx++;
        }

        $lines[] = '';
        $lines[] = 'Total siswa izin atau sakit hari ini: '.$leaves->count().' orang.';

        return implode("\n", $lines);
    }

    /**
     * Build formatted list of students who attended on time today.
     */
    protected function buildOntimeStudentsReply(string $today): string
    {
        $ontime = Attendance::with('user.student')
            ->whereDate('attendance_date', $today)
            ->where('status', 'present')
            ->whereHas('user', fn ($q) => $q->where('role', 'student'))
            ->orderBy('attendance_time', 'asc')
            ->get();

        if ($ontime->isEmpty()) {
            return 'Belum ada siswa yang tercatat hadir tepat waktu hari ini.';
        }

        $lines = ['Daftar Siswa Hadir Tepat Waktu Hari Ini:', ''];
        $idx = 1;
        foreach ($ontime as $att) {
            $name = $att->user?->name ?? 'Siswa';
            $time = $att->attendance_time ? Carbon::parse($att->attendance_time)->format('H.i').' WIB' : '';
            $lines[] = "{$idx}. {$name} — {$time}";
            $idx++;
        }

        $lines[] = '';
        $lines[] = 'Total siswa hadir tepat waktu: '.$ontime->count().' orang.';

        return implode("\n", $lines);
    }

    /**
     * Build reply identifying the student who arrived earliest today.
     */
    protected function buildEarliestStudentReply(string $today): string
    {
        $earliest = Attendance::with('user.student')
            ->whereDate('attendance_date', $today)
            ->whereIn('status', ['present', 'late'])
            ->whereHas('user', fn ($q) => $q->where('role', 'student'))
            ->whereNotNull('attendance_time')
            ->orderBy('attendance_time', 'asc')
            ->first();

        if (! $earliest) {
            return 'Belum ada siswa yang melakukan absensi hari ini.';
        }

        $name = $earliest->user?->name ?? 'Siswa';
        $class = $earliest->user?->student?->class ? "Kelas {$earliest->user->student->class}" : 'Kelas -';
        $time = Carbon::parse($earliest->attendance_time)->format('H.i').' WIB';
        $statusLabel = $earliest->status === 'late' ? 'Terlambat' : 'Tepat Waktu';

        return "Siswa yang datang paling awal hari ini adalah {$name} ({$class}) pada pukul {$time} (status: {$statusLabel}).";
    }

    /**
     * Build formatted list of teachers who attended today.
     */
    protected function buildTeachersPresentReply(string $today): string
    {
        $teachers = Attendance::with('user.teacher')
            ->whereDate('attendance_date', $today)
            ->whereIn('status', ['present', 'late'])
            ->whereHas('user', fn ($q) => $q->where('role', 'teacher'))
            ->orderBy('attendance_time', 'asc')
            ->get();

        if ($teachers->isEmpty()) {
            return 'Belum ada guru yang tercatat hadir hari ini.';
        }

        $lines = ['Daftar Guru yang Hadir Hari Ini:', ''];
        $idx = 1;
        foreach ($teachers as $att) {
            $name = $att->user?->name ?? 'Guru';
            $nip = $att->user?->teacher?->nip ? " (NIP: {$att->user->teacher->nip})" : '';
            $time = $att->attendance_time ? Carbon::parse($att->attendance_time)->format('H.i').' WIB' : '';
            $statusLabel = $att->status === 'late' ? 'Terlambat' : 'Tepat Waktu';
            $lines[] = "{$idx}. {$name}{$nip} — {$time} ({$statusLabel})";
            $idx++;
        }

        $lines[] = '';
        $lines[] = 'Total guru hadir: '.$teachers->count().' guru.';

        return implode("\n", $lines);
    }

    /**
     * Build formatted list of teachers who are late today.
     */
    protected function buildTeachersLateReply(string $today): string
    {
        $late = Attendance::with('user.teacher')
            ->whereDate('attendance_date', $today)
            ->where('status', 'late')
            ->whereHas('user', fn ($q) => $q->where('role', 'teacher'))
            ->orderBy('attendance_time', 'asc')
            ->get();

        if ($late->isEmpty()) {
            return 'Tidak ada guru yang terlambat hari ini. Semua guru yang hadir datang tepat waktu.';
        }

        $lines = ['Daftar Guru yang Terlambat Hari Ini:', ''];
        $idx = 1;
        foreach ($late as $att) {
            $name = $att->user?->name ?? 'Guru';
            $time = $att->attendance_time ? Carbon::parse($att->attendance_time)->format('H.i').' WIB' : '';
            $lines[] = "{$idx}. {$name} — {$time}";
            $idx++;
        }

        $lines[] = '';
        $lines[] = 'Total guru terlambat: '.$late->count().' guru.';

        return implode("\n", $lines);
    }

    /**
     * Build formatted list of active teachers who haven't attended today.
     */
    protected function buildTeachersUnattendedReply(string $today): string
    {
        $attendedTeacherUserIds = Attendance::whereDate('attendance_date', $today)
            ->whereHas('user', fn ($q) => $q->where('role', 'teacher'))
            ->pluck('user_id');

        $unattended = Teacher::with('user')
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->whereNotIn('user_id', $attendedTeacherUserIds)
            ->get()
            ->sortBy(fn ($t) => mb_strtolower($t->user?->name ?? '', 'UTF-8'));

        if ($unattended->isEmpty()) {
            return 'Semua guru aktif telah melakukan absensi hari ini. Tidak ada guru yang belum absen.';
        }

        $lines = ['Daftar Guru yang Belum Melakukan Absensi Hari Ini:', ''];
        $idx = 1;
        foreach ($unattended as $t) {
            $name = $t->user?->name ?? 'Guru';
            $nip = $t->nip ? "NIP: {$t->nip}" : 'NIP: -';
            $lines[] = "{$idx}. {$name} ({$nip})";
            $idx++;
        }

        $lines[] = '';
        $lines[] = 'Total guru belum absen: '.$unattended->count().' guru.';

        return implode("\n", $lines);
    }

    /**
     * Handle queries specific to Administrator.
     *
     * @return array{success: bool, reply: string, intent: string}|null
     */
    protected function handleAdminQueries(string $text, User $user): ?array
    {
        $today = Carbon::today()->toDateString();

        // 1. Siswa Berdasarkan Kelas (Breakdown atau Kelas Tertentu)
        if (preg_match('/\b(jumlah|berapa|total|banyak|statistik)\s+siswa\s+(berdasarkan|per|tiap)\s+kelas\b/iu', $text)
            || preg_match('/\bsiswa\s+berdasarkan\s+kelas\b/iu', $text)) {
            return [
                'success' => true,
                'reply' => $this->buildStudentsByClassReply(),
                'intent' => 'admin_students_by_class',
            ];
        }

        $isLeaveOrReasonQuery = $this->leaveQueryService->isLeaveOrAbsenceQuery($text)
            || preg_match('/\b(alasan|karena|kenapa|kenapakah|mengapa|sakit\s+apa|izin\s+apa|surat\s+dokter)\b/iu', $text);

        if (! $isLeaveOrReasonQuery) {
            $specificClass = $this->extractClassFromQuery($text);
            if ($specificClass !== null && (preg_match('/\b(siapa|daftar|list|tampilkan|nama|ada siapa|berapa|jumlah)\b/iu', $text) || preg_match('/\bsiswa\s+kelas\b/iu', $text))) {
                return [
                    'success' => true,
                    'reply' => $this->buildStudentListReply($specificClass),
                    'intent' => 'admin_students_specific_class',
                ];
            }
        }

        // 2. Siswa yang Baru Terdaftar
        if (preg_match('/\b(siswa|murid)\s+(yang\s+)?(baru\s+terdaftar|terbaru|baru)\b/iu', $text)
            || preg_match('/\bsiswa\s+baru\b/iu', $text)) {
            return [
                'success' => true,
                'reply' => $this->buildRecentStudentsReply(),
                'intent' => 'admin_recent_students',
            ];
        }

        // 3. Siswa yang Paling Sering Terlambat
        if (preg_match('/\b(paling\s+sering|sering\s+banget|paling\s+banyak)\s+(terlambat|telat)\b/iu', $text)
            || preg_match('/\b(terlambat|telat)\s+paling\s+sering\b/iu', $text)
            || preg_match('/\bsiswa\s+.*(sering\s+terlambat|sering\s+telat)\b/iu', $text)) {
            return [
                'success' => true,
                'reply' => $this->buildStudentsMostFrequentlyLateReply(),
                'intent' => 'admin_students_frequently_late',
            ];
        }

        // 4. Siswa / Akun yang Belum Disetujui (Pending Approval)
        if (preg_match('/\b(siswa|murid|akun).*belum\s+(disetujui|diapprove|di-acc|acc)\b/iu', $text)
            || preg_match('/\b(akun|pengajuan).*menunggu\s+persetujuan\b/iu', $text)
            || preg_match('/\b(berapa|jumlah|total).*siswa.*(belum\s+disetujui|pending)\b/iu', $text)) {
            return [
                'success' => true,
                'reply' => $this->buildStudentsPendingApprovalReply(),
                'intent' => 'admin_students_pending_approval',
            ];
        }

        // 5. Siswa yang Izin atau Sakit Hari Ini
        if (preg_match('/\b(siapa|daftar|list|tampilkan|nama).*(siswa|murid).*(izin|ijin|sakit)\b/iu', $text)
            || preg_match('/\b(siswa|murid).*(izin|ijin|sakit)\s+(hari ini|siapa|ada siapa)\b/iu', $text)
            || preg_match('/\b(siapa\s+(saja|aja)\s+siswa\s+(yang\s+)?(izin|ijin|sakit))\b/iu', $text)) {
            return [
                'success' => true,
                'reply' => $this->buildStudentsLeaveTodayReply($today),
                'intent' => 'admin_students_leave_today',
            ];
        }

        // 6. Siswa yang Hadir Hari Ini (Hitung & Daftar Nama)
        if (preg_match('/\b(berapa|jumlah|total)\s+(jumlah\s+)?(siswa|murid)\s+(yang\s+)?(hadir|masuk)\b/iu', $text)) {
            $count = Attendance::whereDate('attendance_date', $today)
                ->whereIn('status', ['present', 'late'])
                ->whereHas('user', fn ($q) => $q->where('role', 'student'))
                ->count();

            return [
                'success' => true,
                'reply' => "Jumlah siswa yang hadir hari ini adalah {$count} siswa.",
                'intent' => 'admin_students_present_count',
            ];
        }

        if (preg_match('/\b(siapa|daftar|list|tampilkan|nama)\s+.*(siswa|murid)\s+.*(hadir|masuk)\b/iu', $text)
            || preg_match('/\b(siswa|murid)\s+(yang\s+)?(hadir|masuk)\s+hari ini\s+(siapa|ada siapa|daftar)\b/iu', $text)
            || preg_match('/\b(siapa\s+(saja|aja)\s+siswa\s+(yang\s+)?hadir)\b/iu', $text)) {
            return [
                'success' => true,
                'reply' => $this->buildStudentsPresentTodayReply($today),
                'intent' => 'admin_students_present_list',
            ];
        }

        // 7. Siswa yang Terlambat Hari Ini (Hitung & Daftar Nama)
        if (preg_match('/\b(berapa|jumlah|total)\s+(jumlah\s+)?(siswa|murid)\s+(yang\s+)?(terlambat|telat)\b/iu', $text)) {
            $count = Attendance::whereDate('attendance_date', $today)
                ->where('status', 'late')
                ->whereHas('user', fn ($q) => $q->where('role', 'student'))
                ->count();

            return [
                'success' => true,
                'reply' => "Jumlah siswa yang terlambat hari ini adalah {$count} siswa.",
                'intent' => 'admin_students_late_count',
            ];
        }

        if (preg_match('/\b(siapa|daftar|list|tampilkan|nama)\s+.*(siswa|murid)\s+.*(terlambat|telat)\b/iu', $text)
            || preg_match('/\b(siswa|murid)\s+(yang\s+)?(terlambat|telat)\s+(hari ini\s+)?(siapa|ada siapa|daftar)\b/iu', $text)
            || preg_match('/\b(siapa\s+(saja|aja)\s+siswa\s+(yang\s+)?(terlambat|telat))\b/iu', $text)) {
            return [
                'success' => true,
                'reply' => $this->buildStudentsLateTodayReply($today),
                'intent' => 'admin_students_late_list',
            ];
        }

        // 8. Siswa yang Belum Melakukan Absensi Hari Ini (Hitung & Daftar Nama)
        if (preg_match('/\b(berapa|jumlah|total)\s+(jumlah\s+)?(siswa|murid)\s+(yang\s+)?belum\s+(absen|presensi|masuk)\b/iu', $text)) {
            $attendedStudentUserIds = Attendance::whereDate('attendance_date', $today)
                ->whereHas('user', fn ($q) => $q->where('role', 'student'))
                ->pluck('user_id');

            $count = Student::whereHas('user', fn ($q) => $q->where('status', 'active'))
                ->whereNotIn('user_id', $attendedStudentUserIds)
                ->count();

            return [
                'success' => true,
                'reply' => "Jumlah siswa yang belum melakukan absensi hari ini adalah {$count} siswa.",
                'intent' => 'admin_students_unattended_count',
            ];
        }

        if (preg_match('/\b(siapa|daftar|list|tampilkan|nama)\s+.*(siswa|murid)\s+.*belum\s+(absen|presensi|masuk)\b/iu', $text)
            || preg_match('/\b(siswa|murid)\s+(yang\s+)?belum\s+(absen|presensi|masuk)\s+(hari ini\s+)?(siapa|ada siapa|daftar)\b/iu', $text)
            || preg_match('/\b(siapa\s+(saja|aja)\s+siswa\s+(yang\s+)?belum\s+(absen|presensi))\b/iu', $text)) {
            return [
                'success' => true,
                'reply' => $this->buildStudentsUnattendedTodayReply($today),
                'intent' => 'admin_students_unattended_list',
            ];
        }

        // 9. Daftar Nama Seluruh Siswa
        if (preg_match('/\b(siapa\s+(saja|aja)\s+nama\s+(siswanya|siswa|muridnya|murid))\b/iu', $text)
            || preg_match('/\b(siapa\s+(saja|aja)\s+(siswanya|muridnya))\b/iu', $text)
            || preg_match('/\b(siapa\s+(saja|aja)\s+siswa\s+(yang\s+)?(terdaftar|ada))\b/iu', $text)
            || preg_match('/\b(nama\s+(siswa|murid)\s+(yang\s+ada\s+)?siapa\s+(aja|saja))\b/iu', $text)
            || preg_match('/\b(daftar|list|tampilkan|sebutkan)\s+(semua\s+)?(nama\s+)?(siswa|murid)\b/iu', $text)
            || preg_match('/\blist\s+siswa(\s+dong)?\b/iu', $text)
            || preg_match('/\bdaftar\s+nama\s+siswa\b/iu', $text)
            || preg_match('/\bdaftar\s+siswa\b/iu', $text)
            || preg_match('/\btampilkan\s+daftar\s+siswa\b/iu', $text)
            || preg_match('/\btampilkan\s+semua\s+(murid|siswa)\b/iu', $text)) {
            return [
                'success' => true,
                'reply' => $this->buildStudentListReply(),
                'intent' => 'admin_student_list',
            ];
        }

        // 10. Total Siswa Terdaftar
        if ($this->matchAny($text, ['total siswa', 'jumlah siswa', 'total murid', 'jumlah murid', 'banyak siswa', 'banyak murid'])
            || preg_match('/\b(ada\s+berapa\s+(anak|siswa|murid)\s+(yang\s+)?terdaftar|berapa\s+(anak|siswa|murid)\s+terdaftar)\b/iu', $text)
            || preg_match('/\bberapa\s+(anak|siswa|murid)\s+yang\s+ada\b/iu', $text)) {
            $totalStudents = Student::count();

            return [
                'success' => true,
                'reply' => "Total siswa terdaftar saat ini adalah {$totalStudents} siswa.",
                'intent' => 'admin_total_students',
            ];
        }

        // 11. Guru Hadir Hari Ini (Hitung & Daftar)
        if (preg_match('/\b(berapa|jumlah|total)\s+(jumlah\s+)?guru\s+(yang\s+)?(hadir|masuk)\b/iu', $text)) {
            $count = Attendance::whereDate('attendance_date', $today)
                ->whereIn('status', ['present', 'late'])
                ->whereHas('user', fn ($q) => $q->where('role', 'teacher'))
                ->count();

            return [
                'success' => true,
                'reply' => "Jumlah guru yang hadir hari ini adalah {$count} guru.",
                'intent' => 'admin_teachers_present_count',
            ];
        }

        if (preg_match('/\b(siapa|daftar|list|tampilkan|nama)\s+.*guru.*(hadir|masuk)\b/iu', $text)
            || preg_match('/\bguru\s+(yang\s+)?(hadir|masuk)\s+hari ini\s+(siapa|ada siapa|daftar)\b/iu', $text)) {
            return [
                'success' => true,
                'reply' => $this->buildTeachersPresentTodayReply($today),
                'intent' => 'admin_teachers_present_list',
            ];
        }

        // 12. Guru Terlambat Hari Ini
        if (preg_match('/\b(siapa|berapa|jumlah|daftar|list)\s+.*guru.*(terlambat|telat)\b/iu', $text)
            || preg_match('/\bguru\s+(yang\s+)?(terlambat|telat)\b/iu', $text)) {
            return [
                'success' => true,
                'reply' => $this->buildTeachersLateTodayReply($today),
                'intent' => 'admin_teachers_late_list',
            ];
        }

        // 13. Guru Belum Absen Hari Ini (Hitung & Daftar)
        if (preg_match('/\b(berapa|jumlah|total)\s+(jumlah\s+)?guru.*belum\s+(absen|presensi|masuk)\b/iu', $text)) {
            $attendedTeacherUserIds = Attendance::whereDate('attendance_date', $today)
                ->whereHas('user', fn ($q) => $q->where('role', 'teacher'))
                ->pluck('user_id');

            $count = Teacher::whereHas('user', fn ($q) => $q->where('status', 'active'))
                ->whereNotIn('user_id', $attendedTeacherUserIds)
                ->count();

            return [
                'success' => true,
                'reply' => "Jumlah guru yang belum melakukan absensi hari ini adalah {$count} guru.",
                'intent' => 'admin_teachers_unattended_count',
            ];
        }

        if (preg_match('/\b(siapa|daftar|list|tampilkan|nama)\s+.*guru.*belum\s+(absen|presensi|masuk)\b/iu', $text)
            || preg_match('/\bguru\s+(yang\s+)?belum\s+(absen|presensi|masuk)\s+(hari ini\s+)?(siapa|ada siapa|daftar)\b/iu', $text)) {
            return [
                'success' => true,
                'reply' => $this->buildTeachersUnattendedTodayReply($today),
                'intent' => 'admin_teachers_unattended_list',
            ];
        }

        // 14. Daftar Nama Guru
        if (preg_match('/\b(siapa\s+(saja|aja)\s+nama\s+(gurunya|guru|pengajarnya|pengajar))\b/iu', $text)
            || preg_match('/\b(nama\s+guru\s+(yang\s+ada\s+)?siapa\s+(aja|saja))\b/iu', $text)
            || preg_match('/\b(siapa\s+(saja|aja)\s+(gurunya|pengajarnya|guru))\b/iu', $text)
            || preg_match('/\b(daftar|list|tampilkan|sebutkan)\s+(semua\s+)?(nama\s+)?(guru|pengajar)\b/iu', $text)
            || preg_match('/\blist\s+guru(\s+dong)?\b/iu', $text)
            || preg_match('/\bdaftar\s+guru\b/iu', $text)
            || preg_match('/\btampilkan\s+daftar\s+guru\b/iu', $text)
            || preg_match('/\btampilkan\s+semua\s+guru\b/iu', $text)) {
            return [
                'success' => true,
                'reply' => $this->buildTeacherListReply(),
                'intent' => 'admin_teacher_list',
            ];
        }

        // 15. Total Guru Terdaftar
        if ($this->matchAny($text, ['total guru', 'jumlah guru', 'total pengajar', 'jumlah pengajar', 'banyak guru', 'berapa guru'])
            || preg_match('/\b(ada\s+berapa\s+guru\s+(yang\s+)?terdaftar|berapa\s+guru\s+terdaftar|berapa\s+jumlah\s+guru\s+yang\s+terdaftar)\b/iu', $text)) {
            $totalTeachers = Teacher::count();

            return [
                'success' => true,
                'reply' => "Total guru terdaftar saat ini adalah {$totalTeachers} guru.",
                'intent' => 'admin_total_teachers',
            ];
        }

        // 16. Data Administrator (Total & Daftar)
        if (preg_match('/\b(berapa\s+total\s+(akun\s+)?admin|total\s+(akun\s+)?admin|jumlah\s+(akun\s+)?admin|total\s+administrator|berapa\s+admin\s+terdaftar)\b/iu', $text)) {
            $totalAdmins = User::where('role', 'admin')->count();

            return [
                'success' => true,
                'reply' => "Total admin terdaftar saat ini adalah {$totalAdmins} admin.",
                'intent' => 'admin_total_admins',
            ];
        }

        if (preg_match('/\b(siapa\s+(saja|aja)\s+(administrator|admin)\s+(yang\s+)?terdaftar)\b/iu', $text)
            || preg_match('/\b(siapa\s+(saja|aja)\s+(admin|administrator))\b/iu', $text)
            || preg_match('/\b(daftar|list|tampilkan)\s+(semua\s+)?(admin|administrator)\b/iu', $text)) {
            return [
                'success' => true,
                'reply' => $this->buildAdminListReply(),
                'intent' => 'admin_list',
            ];
        }

        // 17. Statistik Dashboard Keseluruhan
        if (preg_match('/\b(total\s+akun\s+yang\s+disetujui\s+hari\s+ini|disetujui\s+hari\s+ini)\b/iu', $text)) {
            $approvedToday = Attendance::whereIn('status', ['excused', 'sick'])
                ->where('approval_status', 'approved')
                ->whereDate('attendance_date', $today)
                ->count();

            return [
                'success' => true,
                'reply' => "Jumlah pengajuan izin dan sakit yang disetujui hari ini adalah {$approvedToday} pengajuan.",
                'intent' => 'admin_approved_today',
            ];
        }

        if (preg_match('/\b(total|jumlah)\s+izin\s+dan\s+sakit\b/iu', $text)) {
            $totalLeaves = Attendance::whereIn('status', ['excused', 'sick'])->count();

            return [
                'success' => true,
                'reply' => "Total riwayat pengajuan izin dan sakit tercatat saat ini adalah {$totalLeaves} data.",
                'intent' => 'admin_total_leaves',
            ];
        }

        if (preg_match('/\b(tingkat\s+kehadiran|persentase\s+kehadiran|persen\s+kehadiran)\b/iu', $text)) {
            $totalPresent = Attendance::where('status', 'present')->count();
            $totalLate = Attendance::where('status', 'late')->count();
            $allCount = Attendance::count();

            $rate = $allCount > 0 ? round((($totalPresent + $totalLate) / $allCount) * 100, 1) : 100.0;

            return [
                'success' => true,
                'reply' => "Tingkat kehadiran sekolah saat ini tercatat sebesar {$rate}%.",
                'intent' => 'admin_attendance_rate',
            ];
        }

        if (preg_match('/\b(ringkasan|statistik)\s+dashboard\b/iu', $text)
            || preg_match('/\bstatistik\s+sekolah\b/iu', $text)) {
            return [
                'success' => true,
                'reply' => $this->buildOverallDashboardStatsReply($today),
                'intent' => 'admin_dashboard_summary',
            ];
        }

        // 18. Hadir Hari Ini (Umum)
        if ($this->matchAny($text, ['hadir hari ini', 'jumlah hadir', 'berapa yang hadir', 'yang hadir hari ini', 'total hadir'])) {
            $todayPresent = Attendance::whereDate('attendance_date', $today)
                ->where('status', 'present')
                ->count();

            return [
                'success' => true,
                'reply' => "Jumlah kehadiran hari ini adalah {$todayPresent} orang.",
                'intent' => 'admin_today_present',
            ];
        }

        // 19. Terlambat Hari Ini (Umum)
        if ($this->matchAny($text, ['terlambat hari ini', 'berapa yang terlambat', 'jumlah terlambat', 'yang terlambat hari ini', 'total terlambat', 'yang telat hari ini', 'berapa yang telat', 'jumlah telat'])) {
            $todayLate = Attendance::whereDate('attendance_date', $today)
                ->where('status', 'late')
                ->count();

            return [
                'success' => true,
                'reply' => "Jumlah yang terlambat hari ini adalah {$todayLate} orang.",
                'intent' => 'admin_today_late',
            ];
        }

        // 20. Belum Absen Hari Ini (Umum)
        if ($this->matchAny($text, ['belum absen', 'belum presensi', 'siapa yang belum absen', 'siapa belum absen', 'belum masuk'])) {
            $totalActivePersonnel = User::whereIn('role', ['student', 'teacher'])
                ->where('status', 'active')
                ->count();

            $attendedUserIds = Attendance::whereDate('attendance_date', $today)
                ->distinct('user_id')
                ->pluck('user_id');

            $todayBelumAbsenCount = max(0, $totalActivePersonnel - $attendedUserIds->count());

            if ($todayBelumAbsenCount === 0) {
                return [
                    'success' => true,
                    'reply' => 'Semua guru dan siswa aktif telah melakukan absensi hari ini. Tidak ada yang belum absen.',
                    'intent' => 'admin_today_unattended',
                ];
            }

            $unattendedUsers = User::whereIn('role', ['student', 'teacher'])
                ->where('status', 'active')
                ->whereNotIn('id', $attendedUserIds)
                ->select('name', 'role')
                ->take(8)
                ->get();

            $namesFormatted = $unattendedUsers->map(function ($u) {
                $roleLabel = $u->role === 'teacher' ? 'Guru' : 'Siswa';

                return "{$u->name} ({$roleLabel})";
            })->implode(', ');

            $reply = "Jumlah yang belum absen hari ini adalah {$todayBelumAbsenCount} orang.";
            if (! empty($namesFormatted)) {
                $reply .= " Beberapa di antaranya: {$namesFormatted}.";
            }

            return [
                'success' => true,
                'reply' => $reply,
                'intent' => 'admin_today_unattended',
            ];
        }

        // 21. Admin Menu & Usage Guidance
        if ($this->matchAny($text, ['panduan admin', 'menu admin', 'cara menggunakan admin', 'fitur admin', 'bantuan admin', 'panduan dashboard'])) {
            return [
                'success' => true,
                'reply' => "Panduan Menu Administrator SmartAttendance:\n"
                    ."1. Dashboard (/admin/dashboard): Pantau statistik total siswa, guru, kehadiran hari ini, keterlambatan, dan tabel presensi dengan filter tanggal, status, atau kelas.\n"
                    ."2. Manajemen Siswa (/admin/students): Tambah, edit, aktifkan/nonaktifkan akun siswa, serta daftarkan sampel biometrik wajah siswa.\n"
                    ."3. Manajemen Guru (/admin/teachers): Kelola data pengajar dan daftarkan sampel biometrik wajah guru.\n"
                    .'4. Scanner Kiosk (/attendance): Akses scanner presensi mandiri berbasis kamera dan face recognition.',
                'intent' => 'admin_menu_guide',
            ];
        }

        return null;
    }

    /**
     * Handle queries specific to Teacher.
     *
     * @return array{success: bool, reply: string, intent: string}|null
     */
    protected function handleTeacherQueries(string $text, User $user): ?array
    {
        $today = Carbon::today()->toDateString();

        // 1. Role Privacy Guard: Teacher cannot query Admin credentials/list
        if (preg_match('/\b(admin|administrator)\b/iu', $text)
            && preg_match('/\b(siapa|daftar|list|tampilkan|total|jumlah|berapa|akun)\b/iu', $text)) {
            return [
                'success' => true,
                'reply' => 'Informasi mengenai akun administrator hanya dapat diakses oleh Administrator sekolah.',
                'intent' => 'privacy_restricted',
            ];
        }

        // 2. Siswa Berdasarkan Kelas (Breakdown atau Kelas Tertentu)
        if (preg_match('/\b(jumlah|berapa|total|banyak|statistik)\s+siswa\s+(berdasarkan|per|tiap)\s+kelas\b/iu', $text)
            || preg_match('/\bsiswa\s+berdasarkan\s+kelas\b/iu', $text)) {
            return [
                'success' => true,
                'reply' => $this->buildStudentsByClassReply(),
                'intent' => 'teacher_students_by_class',
            ];
        }

        $isLeaveOrReasonQuery = $this->leaveQueryService->isLeaveOrAbsenceQuery($text)
            || preg_match('/\b(alasan|karena|kenapa|kenapakah|mengapa|sakit\s+apa|izin\s+apa|surat\s+dokter)\b/iu', $text);

        if (! $isLeaveOrReasonQuery) {
            $specificClass = $this->extractClassFromQuery($text);
            if ($specificClass !== null && (preg_match('/\b(siapa|daftar|list|tampilkan|nama|ada siapa|berapa|jumlah)\b/iu', $text) || preg_match('/\bsiswa\s+kelas\b/iu', $text))) {
                return [
                    'success' => true,
                    'reply' => $this->buildStudentListReply($specificClass),
                    'intent' => 'teacher_students_specific_class',
                ];
            }
        }

        // 3. Siswa yang Baru Terdaftar
        if (preg_match('/\b(siswa|murid)\s+(yang\s+)?(baru\s+terdaftar|terbaru|baru)\b/iu', $text)
            || preg_match('/\bsiswa\s+baru\b/iu', $text)) {
            return [
                'success' => true,
                'reply' => $this->buildRecentStudentsReply(),
                'intent' => 'teacher_recent_students',
            ];
        }

        // 4. Siswa yang Paling Sering Terlambat
        if (preg_match('/\b(paling\s+sering|sering\s+banget|paling\s+banyak)\s+(terlambat|telat)\b/iu', $text)
            || preg_match('/\b(terlambat|telat)\s+paling\s+sering\b/iu', $text)
            || preg_match('/\bsiswa\s+.*(sering\s+terlambat|sering\s+telat)\b/iu', $text)) {
            return [
                'success' => true,
                'reply' => $this->buildStudentsMostFrequentlyLateReply(),
                'intent' => 'teacher_students_frequently_late',
            ];
        }

        // 5. Pengajuan Izin dan Sakit yang Menunggu Persetujuan
        if ($this->matchAny($text, ['menunggu persetujuan', 'izin menunggu', 'pending izin', 'pengajuan izin', 'pengajuan sakit', 'izin yang belum disetujui', 'permohonan izin', 'belum disetujui'])) {
            $pendingLeaves = Attendance::whereIn('status', ['excused', 'sick'])
                ->where('approval_status', 'pending')
                ->count();

            return [
                'success' => true,
                'reply' => "Pengajuan izin dan sakit yang menunggu persetujuan saat ini adalah {$pendingLeaves} pengajuan.",
                'intent' => 'teacher_pending_leaves',
            ];
        }

        // 6. Jumlah Pengajuan yang Disetujui Hari Ini
        if ($this->matchAny($text, ['disetujui hari ini', 'izin disetujui', 'pengajuan disetujui', 'izin yang disetujui'])) {
            $todayApprovedLeaves = Attendance::whereIn('status', ['excused', 'sick'])
                ->where('approval_status', 'approved')
                ->whereDate('attendance_date', $today)
                ->count();

            return [
                'success' => true,
                'reply' => "Jumlah pengajuan izin dan sakit yang disetujui hari ini adalah {$todayApprovedLeaves} pengajuan.",
                'intent' => 'teacher_approved_leaves_today',
            ];
        }

        // 7. Siswa yang Izin atau Sakit Hari Ini
        if (preg_match('/\b(siapa|daftar|list|tampilkan|nama).*(siswa|murid).*(izin|ijin|sakit)\b/iu', $text)
            || preg_match('/\b(siswa|murid).*(izin|ijin|sakit)\s+(hari ini|siapa|ada siapa)\b/iu', $text)
            || preg_match('/\b(siapa\s+(saja|aja)\s+siswa\s+(yang\s+)?(izin|ijin|sakit))\b/iu', $text)) {
            return [
                'success' => true,
                'reply' => $this->buildStudentsLeaveTodayReply($today),
                'intent' => 'teacher_students_leave_today',
            ];
        }

        // 8. Siswa Hadir Hari Ini (Hitung & Daftar)
        if (preg_match('/\b(berapa|jumlah|total)\s+(jumlah\s+)?(siswa|murid)\s+(yang\s+)?(hadir|masuk)\b/iu', $text)) {
            $count = Attendance::whereDate('attendance_date', $today)
                ->whereIn('status', ['present', 'late'])
                ->whereHas('user', fn ($q) => $q->where('role', 'student'))
                ->count();

            return [
                'success' => true,
                'reply' => "Jumlah siswa yang hadir hari ini adalah {$count} siswa.",
                'intent' => 'teacher_students_present_count',
            ];
        }

        if (preg_match('/\b(siapa|daftar|list|tampilkan|nama)\s+.*(siswa|murid)\s+.*(hadir|masuk)\b/iu', $text)
            || preg_match('/\b(siswa|murid)\s+(yang\s+)?(hadir|masuk)\s+hari ini\s+(siapa|ada siapa|daftar)\b/iu', $text)
            || preg_match('/\b(siapa\s+(saja|aja)\s+siswa\s+(yang\s+)?hadir)\b/iu', $text)) {
            return [
                'success' => true,
                'reply' => $this->buildStudentsPresentTodayReply($today),
                'intent' => 'teacher_students_present_list',
            ];
        }

        // 9. Siswa Terlambat Hari Ini (Hitung & Daftar)
        if (preg_match('/\b(berapa|jumlah|total)\s+(jumlah\s+)?(siswa|murid)\s+(yang\s+)?(terlambat|telat)\b/iu', $text)) {
            $count = Attendance::whereDate('attendance_date', $today)
                ->where('status', 'late')
                ->whereHas('user', fn ($q) => $q->where('role', 'student'))
                ->count();

            return [
                'success' => true,
                'reply' => "Jumlah siswa yang terlambat hari ini adalah {$count} siswa.",
                'intent' => 'teacher_students_late_count',
            ];
        }

        if (preg_match('/\b(siapa|daftar|list|tampilkan|nama)\s+.*(siswa|murid)\s+.*(terlambat|telat)\b/iu', $text)
            || preg_match('/\b(siswa|murid)\s+(yang\s+)?(terlambat|telat)\s+(hari ini\s+)?(siapa|ada siapa|daftar)\b/iu', $text)
            || preg_match('/\b(siapa\s+(saja|aja)\s+siswa\s+(yang\s+)?(terlambat|telat))\b/iu', $text)) {
            return [
                'success' => true,
                'reply' => $this->buildStudentsLateTodayReply($today),
                'intent' => 'teacher_students_late_list',
            ];
        }

        // 10. Siswa Belum Absen Hari Ini (Hitung & Daftar)
        if (preg_match('/\b(berapa|jumlah|total)\s+(jumlah\s+)?(siswa|murid)\s+(yang\s+)?belum\s+(absen|presensi|masuk)\b/iu', $text)) {
            $attendedStudentUserIds = Attendance::whereDate('attendance_date', $today)
                ->whereHas('user', fn ($q) => $q->where('role', 'student'))
                ->pluck('user_id');

            $count = Student::whereHas('user', fn ($q) => $q->where('status', 'active'))
                ->whereNotIn('user_id', $attendedStudentUserIds)
                ->count();

            return [
                'success' => true,
                'reply' => "Jumlah siswa yang belum melakukan absensi hari ini adalah {$count} siswa.",
                'intent' => 'teacher_students_unattended_count',
            ];
        }

        if (preg_match('/\b(siapa|daftar|list|tampilkan|nama)\s+.*(siswa|murid)\s+.*belum\s+(absen|presensi|masuk)\b/iu', $text)
            || preg_match('/\b(siswa|murid)\s+(yang\s+)?belum\s+(absen|presensi|masuk)\s+(hari ini\s+)?(siapa|ada siapa|daftar)\b/iu', $text)
            || preg_match('/\b(siapa\s+(saja|aja)\s+siswa\s+(yang\s+)?belum\s+(absen|presensi))\b/iu', $text)) {
            return [
                'success' => true,
                'reply' => $this->buildStudentsUnattendedTodayReply($today),
                'intent' => 'teacher_students_unattended_list',
            ];
        }

        // 11. Daftar Nama Siswa
        if (preg_match('/\b(siapa\s+(saja|aja)\s+nama\s+(siswanya|siswa|muridnya|murid))\b/iu', $text)
            || preg_match('/\b(siapa\s+(saja|aja)\s+(siswanya|muridnya))\b/iu', $text)
            || preg_match('/\b(siapa\s+(saja|aja)\s+siswa\s+(yang\s+)?(terdaftar|ada))\b/iu', $text)
            || preg_match('/\b(nama\s+(siswa|murid)\s+(yang\s+ada\s+)?siapa\s+(aja|saja))\b/iu', $text)
            || preg_match('/\b(daftar|list|tampilkan|sebutkan)\s+(semua\s+)?(nama\s+)?(siswa|murid)\b/iu', $text)
            || preg_match('/\blist\s+siswa(\s+dong)?\b/iu', $text)
            || preg_match('/\bdaftar\s+nama\s+siswa\b/iu', $text)
            || preg_match('/\bdaftar\s+siswa\b/iu', $text)
            || preg_match('/\btampilkan\s+daftar\s+siswa\b/iu', $text)
            || preg_match('/\btampilkan\s+semua\s+(murid|siswa)\b/iu', $text)) {
            return [
                'success' => true,
                'reply' => $this->buildStudentListReply(),
                'intent' => 'teacher_student_list',
            ];
        }

        // 12. Total Siswa Terdaftar
        if ($this->matchAny($text, ['total siswa', 'jumlah siswa', 'total murid', 'jumlah murid', 'banyak siswa', 'berapa siswa'])
            || preg_match('/\b(ada\s+berapa\s+(anak|siswa|murid)\s+(yang\s+)?terdaftar|berapa\s+(anak|siswa|murid)\s+terdaftar)\b/iu', $text)) {
            $totalStudents = Student::count();

            return [
                'success' => true,
                'reply' => "Total siswa terdaftar saat ini adalah {$totalStudents} siswa.",
                'intent' => 'teacher_total_students',
            ];
        }

        // 13. Guru Hadir Hari Ini
        if (preg_match('/\b(siapa|daftar|list|tampilkan|nama)\s+.*guru.*(hadir|masuk)\b/iu', $text)
            || preg_match('/\bguru\s+(yang\s+)?(hadir|masuk)\s+hari ini\s+(siapa|ada siapa|daftar)\b/iu', $text)) {
            return [
                'success' => true,
                'reply' => $this->buildTeachersPresentTodayReply($today),
                'intent' => 'teacher_teachers_present_list',
            ];
        }

        // 14. Guru Belum Absen Hari Ini
        if (preg_match('/\b(siapa|daftar|list|tampilkan|nama)\s+.*guru.*belum\s+(absen|presensi|masuk)\b/iu', $text)
            || preg_match('/\bguru\s+(yang\s+)?belum\s+(absen|presensi|masuk)\s+(hari ini\s+)?(siapa|ada siapa|daftar)\b/iu', $text)) {
            return [
                'success' => true,
                'reply' => $this->buildTeachersUnattendedTodayReply($today),
                'intent' => 'teacher_teachers_unattended_list',
            ];
        }

        // 15. Guru Terlambat Hari Ini
        if (preg_match('/\b(siapa|berapa|jumlah|daftar|list)\s+.*guru.*(terlambat|telat)\b/iu', $text)
            || preg_match('/\bguru\s+(yang\s+)?(terlambat|telat)\b/iu', $text)) {
            return [
                'success' => true,
                'reply' => $this->buildTeachersLateTodayReply($today),
                'intent' => 'teacher_teachers_late_list',
            ];
        }

        // 16. Daftar Nama Guru
        if (preg_match('/\b(siapa\s+(saja|aja)\s+nama\s+(gurunya|guru|pengajarnya|pengajar))\b/iu', $text)
            || preg_match('/\b(nama\s+guru\s+(yang\s+ada\s+)?siapa\s+(aja|saja))\b/iu', $text)
            || preg_match('/\b(siapa\s+(saja|aja)\s+(gurunya|pengajarnya|guru))\b/iu', $text)
            || preg_match('/\b(daftar|list|tampilkan|sebutkan)\s+(semua\s+)?(nama\s+)?(guru|pengajar)\b/iu', $text)
            || preg_match('/\blist\s+guru(\s+dong)?\b/iu', $text)
            || preg_match('/\bdaftar\s+guru\b/iu', $text)
            || preg_match('/\btampilkan\s+daftar\s+guru\b/iu', $text)) {
            return [
                'success' => true,
                'reply' => $this->buildTeacherListReply(),
                'intent' => 'teacher_teacher_list',
            ];
        }

        // 17. Total Guru Terdaftar
        if ($this->matchAny($text, ['total guru', 'jumlah guru', 'total pengajar', 'jumlah pengajar', 'banyak guru', 'berapa guru'])
            || preg_match('/\b(ada\s+berapa\s+guru\s+(yang\s+)?terdaftar|berapa\s+guru\s+terdaftar)\b/iu', $text)) {
            $totalTeachers = Teacher::count();

            return [
                'success' => true,
                'reply' => "Total guru terdaftar saat ini adalah {$totalTeachers} guru.",
                'intent' => 'teacher_total_teachers',
            ];
        }

        // 18. Jumlah Kehadiran Hari Ini (Umum)
        if ($this->matchAny($text, ['hadir hari ini', 'jumlah hadir', 'berapa yang hadir', 'kehadiran hari ini', 'total hadir'])) {
            $todayPresent = Attendance::whereDate('attendance_date', $today)
                ->whereIn('status', ['present', 'late'])
                ->count();

            return [
                'success' => true,
                'reply' => "Jumlah kehadiran hari ini adalah {$todayPresent} orang.",
                'intent' => 'teacher_today_present',
            ];
        }

        // 19. Panduan Penggunaan Dashboard Guru
        if ($this->matchAny($text, ['panduan guru', 'menu guru', 'cara menggunakan portal guru', 'fitur guru', 'bantuan guru', 'panduan dashboard'])) {
            return [
                'success' => true,
                'reply' => "Panduan Portal Guru SmartAttendance:\n"
                    ."1. Dashboard (/teacher/dashboard): Menampilkan kartu ringkasan siswa, pengajuan izin yang menunggu persetujuan, izin disetujui hari ini, dan kehadiran siswa.\n"
                    ."2. Tinjau Izin / Sakit (/teacher/leave-requests): Tinjau berkas surat permohonan siswa, beri catatan, dan lakukan persetujuan (Approve) atau penolakan (Reject).\n"
                    .'3. Scanner Presensi (/attendance): Buka scanner kamera mandiri untuk presensi siswa atau guru di sekolah.',
                'intent' => 'teacher_menu_guide',
            ];
        }

        return null;
    }

    /**
     * Handle queries specific to Student.
     *
     * @return array{success: bool, reply: string, intent: string}|null
     */
    protected function handleStudentQueries(string $text, User $user): ?array
    {
        $today = Carbon::today()->toDateString();
        $student = $user->student;

        // 1. Role Privacy Guard: Students CANNOT view other students' personal data, all students list, teacher data, or admin data
        if (preg_match('/\b(daftar|list|tampilkan|sebutkan)\s+.*(siswa|murid|guru|admin|pengajar)\b/iu', $text)
            || preg_match('/\b(siapa\s+(saja|aja)\s+nama\s+(siswanya|siswa|muridnya|murid|gurunya|guru|admin))\b/iu', $text)
            || preg_match('/\b(nama\s+(siswa|murid|guru)\s+(yang\s+ada\s+)?siapa\s+(aja|saja))\b/iu', $text)
            || preg_match('/\b(siapa\s+(saja|aja)\s+(siswanya|muridnya|gurunya|admin))\b/iu', $text)
            || preg_match('/\b(total|jumlah|banyak|berapa)\s+(siswa|murid|guru|admin|pengajar)\b/iu', $text)
            || preg_match('/\b(siapa\s+(siswa|guru)\s+(yang\s+)?(hadir|terlambat|telat|belum\s+absen|izin|sakit))\b/iu', $text)
            || preg_match('/\b(siswa|guru)\s+.*(sering\s+terlambat|paling\s+sering)\b/iu', $text)
            || preg_match('/\blist\s+(siswa|guru|admin)\b/iu', $text)) {
            return [
                'success' => true,
                'reply' => 'Demi menjaga privasi dan keamanan data sekolah, daftar nama dan data absensi seluruh siswa/guru hanya dapat diakses oleh Administrator dan Guru. Kamu dapat menanyakan status absensi, riwayat kehadiran, atau profil akun kamu sendiri.',
                'intent' => 'privacy_restricted',
            ];
        }

        // 2. Hadir Tepat Waktu
        if ($this->matchAny($text, ['tepat waktu', 'hadir tepat waktu', 'berapa kali tepat waktu', 'jumlah tepat waktu'])) {
            $totalPresent = Attendance::where('user_id', $user->id)
                ->where('status', 'present')
                ->count();

            return [
                'success' => true,
                'reply' => "Jumlah kehadiran tepat waktu kamu adalah {$totalPresent} kali.",
                'intent' => 'student_total_present',
            ];
        }

        // 3. Jumlah Keterlambatan
        if ($this->matchAny($text, ['keterlambatan', 'terlambat', 'telat', 'berapa kali telat', 'jumlah terlambat', 'berapa kali terlambat'])) {
            $totalLate = Attendance::where('user_id', $user->id)
                ->where('status', 'late')
                ->count();

            return [
                'success' => true,
                'reply' => "Jumlah keterlambatan kamu adalah {$totalLate} kali.",
                'intent' => 'student_total_late',
            ];
        }

        // 4. Jumlah Izin dan Sakit
        if ($this->matchAny($text, ['izin dan sakit', 'jumlah izin', 'jumlah sakit', 'total izin', 'total sakit', 'berapa kali izin', 'berapa kali sakit'])) {
            $totalExcused = Attendance::where('user_id', $user->id)
                ->where('status', 'excused')
                ->where('approval_status', 'approved')
                ->count();

            $totalSick = Attendance::where('user_id', $user->id)
                ->where('status', 'sick')
                ->where('approval_status', 'approved')
                ->count();

            $pending = Attendance::where('user_id', $user->id)
                ->whereIn('status', ['excused', 'sick'])
                ->where('approval_status', 'pending')
                ->count();

            $reply = "Jumlah izin yang disetujui adalah {$totalExcused} kali dan sakit {$totalSick} kali.";
            if ($pending > 0) {
                $reply .= " Saat ini ada {$pending} pengajuan yang masih menunggu persetujuan guru.";
            }

            return [
                'success' => true,
                'reply' => $reply,
                'intent' => 'student_leaves',
            ];
        }

        // 5. Tingkat Kehadiran / Persentase Kehadiran
        if ($this->matchAny($text, ['tingkat kehadiran', 'persen kehadiran', 'persentase kehadiran', 'kehadiran saya', 'berapa persen'])) {
            $totalPresent = Attendance::where('user_id', $user->id)->where('status', 'present')->count();
            $totalLate = Attendance::where('user_id', $user->id)->where('status', 'late')->count();
            $totalExcused = Attendance::where('user_id', $user->id)->where('status', 'excused')->where('approval_status', 'approved')->count();
            $totalSick = Attendance::where('user_id', $user->id)->where('status', 'sick')->where('approval_status', 'approved')->count();

            $allRecordsCount = $totalPresent + $totalLate + $totalExcused + $totalSick;
            $percentage = $allRecordsCount > 0
                ? round((($totalPresent + $totalLate) / $allRecordsCount) * 100, 1)
                : 100.0;

            return [
                'success' => true,
                'reply' => "Tingkat kehadiran kamu saat ini adalah {$percentage}%.",
                'intent' => 'student_attendance_rate',
            ];
        }

        // 6. Status Absensi Hari Ini
        if ($this->matchAny($text, ['status absensi hari ini', 'status absen hari ini', 'sudah absen belum', 'apakah saya sudah absen', 'absen hari ini', 'kehadiran hari ini'])) {
            $todayAttendance = Attendance::where('user_id', $user->id)
                ->whereDate('attendance_date', $today)
                ->first();

            if (! $todayAttendance) {
                return [
                    'success' => true,
                    'reply' => 'Kamu belum melakukan absensi hari ini. Silakan lakukan presensi mandiri di scanner sekolah sebelum batas toleransi pukul 07:15 WIB.',
                    'intent' => 'student_today_status',
                ];
            }

            $timeStr = $todayAttendance->attendance_time ? " pada pukul {$todayAttendance->attendance_time} WIB" : '';

            $reply = match ($todayAttendance->status) {
                'present' => "Status absensi kamu hari ini adalah Hadir (Tepat Waktu){$timeStr}.",
                'late' => "Status absensi kamu hari ini adalah Hadir (Terlambat){$timeStr}.",
                'sick' => "Status kamu hari ini tercatat Sakit ({$todayAttendance->getApprovalStatusLabel()}).",
                'excused' => "Status kamu hari ini tercatat Izin ({$todayAttendance->getApprovalStatusLabel()}).",
                default => "Status absensi kamu hari ini tercatat: {$todayAttendance->status}.",
            };

            return [
                'success' => true,
                'reply' => $reply,
                'intent' => 'student_today_status',
            ];
        }

        // 7. Identitas Siswa & Kelas
        if ($this->matchAny($text, ['kelas saya', 'nis saya', 'data kelas', 'profil siswa'])) {
            $class = $student?->class ?? 'belum ditentukan';
            $nis = $student?->nis ?? 'belum ditentukan';

            return [
                'success' => true,
                'reply' => "Nama: {$user->name}, Kelas: {$class}, NIS: {$nis}.",
                'intent' => 'student_profile',
            ];
        }

        // 8. Panduan Penggunaan Dashboard Siswa
        if ($this->matchAny($text, ['panduan siswa', 'menu siswa', 'cara menggunakan portal siswa', 'fitur siswa', 'bantuan siswa', 'panduan dashboard'])) {
            return [
                'success' => true,
                'reply' => "Panduan Portal Siswa SmartAttendance:\n"
                    ."1. Dashboard (/student/dashboard): Lihat status absensi hari ini, kartu statistik kehadiran tepat waktu, keterlambatan, izin/sakit, dan riwayat presensi terbaru.\n"
                    ."2. Riwayat Presensi (/student/history): Lihat riwayat lengkap kehadiran bulanan beserta status verifikasi wajah.\n"
                    ."3. Ajukan Izin / Sakit (/student/leave-requests): Formulir permohonan izin atau sakit dengan unggah bukti surat dokter/keterangan.\n"
                    .'4. Scanner Presensi (/attendance): Lakukan presensi mandiri menggunakan verifikasi kamera sekolah.',
                'intent' => 'student_menu_guide',
            ];
        }

        return null;
    }

    /**
     * Build formatted list of all registered students from actual database.
     */
    protected function buildStudentListReply(?string $classFilter = null, int $limit = 40): string
    {
        $query = Student::with('user');

        if ($classFilter !== null && trim($classFilter) !== '') {
            $cleanFilter = trim($classFilter);
            $dashVariant = str_replace(' ', '-', $cleanFilter);
            $spaceVariant = str_replace('-', ' ', $cleanFilter);
            $nospaceVariant = str_replace(['-', ' '], '', $cleanFilter);

            $query->where(function ($q) use ($cleanFilter, $dashVariant, $spaceVariant, $nospaceVariant) {
                $q->where('class', 'like', "%{$cleanFilter}%")
                    ->orWhere('class', 'like', "%{$dashVariant}%")
                    ->orWhere('class', 'like', "%{$spaceVariant}%")
                    ->orWhereRaw("REPLACE(REPLACE(class, '-', ''), ' ', '') LIKE ?", ["%{$nospaceVariant}%"]);
            });
        }

        $students = $query->get()->sortBy(function ($s) {
            return mb_strtolower($s->user?->name ?? '', 'UTF-8');
        });

        $totalCount = $students->count();
        if ($totalCount === 0) {
            if ($classFilter !== null) {
                return "Tidak ditemukan siswa yang terdaftar di kelas '{$classFilter}'. Silakan periksa kembali penulisan nama kelas.";
            }

            return 'Belum ada siswa yang terdaftar di database.';
        }

        $header = $classFilter !== null
            ? "Berikut daftar siswa kelas {$classFilter} yang terdaftar di SmartAttendance:"
            : 'Berikut daftar siswa yang terdaftar di SmartAttendance:';

        $lines = [$header, ''];
        $index = 1;
        foreach ($students->take($limit) as $student) {
            $name = $student->user?->name ?? 'Tanpa Nama';
            $class = $student->class ? "Kelas {$student->class}" : 'Kelas belum diatur';
            $nis = $student->nis ? "NIS: {$student->nis}" : 'NIS: -';
            $lines[] = "{$index}. {$name} ({$class} - {$nis})";
            $index++;
        }

        if ($totalCount > $limit) {
            $remaining = $totalCount - $limit;
            $lines[] = '';
            $lines[] = "... dan {$remaining} siswa lainnya. Daftar lengkap dapat dilihat pada menu Manajemen Siswa (/admin/students).";
        }

        $lines[] = '';
        $lines[] = "Total siswa terdaftar: {$totalCount} siswa.";

        return implode("\n", $lines);
    }

    /**
     * Build formatted summary of students grouped by class.
     */
    protected function buildStudentsByClassReply(): string
    {
        $classes = Student::select('class', DB::raw('count(*) as total'))
            ->groupBy('class')
            ->orderBy('class')
            ->get();

        if ($classes->isEmpty()) {
            return 'Belum ada data siswa atau kelas yang terdaftar dalam sistem.';
        }

        $totalAll = Student::count();
        $lines = ['Jumlah Siswa Berdasarkan Kelas:'];
        foreach ($classes as $item) {
            $className = $item->class ? "Kelas {$item->class}" : 'Belum Ditentukan';
            $lines[] = "- {$className}: {$item->total} siswa";
        }

        $lines[] = '';
        $lines[] = "Total: {$totalAll} siswa.";

        return implode("\n", $lines);
    }

    /**
     * Build formatted list of recently registered students.
     */
    protected function buildRecentStudentsReply(int $limit = 5): string
    {
        $recent = Student::with('user')
            ->latest()
            ->take($limit)
            ->get();

        if ($recent->isEmpty()) {
            return 'Belum ada data siswa yang terdaftar.';
        }

        $lines = ['Daftar Siswa yang Baru Terdaftar:'];
        $index = 1;
        foreach ($recent as $item) {
            $name = $item->user?->name ?? 'Siswa';
            $class = $item->class ? "Kelas {$item->class}" : 'Kelas -';
            $date = $item->created_at ? $item->created_at->format('d/m/Y') : '-';
            $lines[] = "{$index}. {$name} ({$class}) - Terdaftar pada {$date}";
            $index++;
        }

        return implode("\n", $lines);
    }

    /**
     * Build formatted list of students who attended today.
     */
    protected function buildStudentsPresentTodayReply(string $today, int $limit = 40): string
    {
        $attendances = Attendance::with('user.student')
            ->whereDate('attendance_date', $today)
            ->whereIn('status', ['present', 'late'])
            ->whereHas('user', fn ($q) => $q->where('role', 'student'))
            ->orderBy('attendance_time', 'asc')
            ->get();

        $totalCount = $attendances->count();
        if ($totalCount === 0) {
            return 'Belum ada siswa yang tercatat hadir hari ini.';
        }

        $lines = ['Berikut daftar siswa yang sudah hadir hari ini:', ''];
        $index = 1;
        foreach ($attendances->take($limit) as $att) {
            $name = $att->user?->name ?? 'Siswa';
            $timeStr = $att->attendance_time ? Carbon::parse($att->attendance_time)->format('H.i').' WIB' : '';
            $statusLabel = $att->status === 'late' ? 'Terlambat' : 'Tepat Waktu';
            $timeInfo = $timeStr ? " — {$timeStr} ({$statusLabel})" : " ({$statusLabel})";
            $lines[] = "{$index}. {$name}{$timeInfo}";
            $index++;
        }

        if ($totalCount > $limit) {
            $remaining = $totalCount - $limit;
            $lines[] = '';
            $lines[] = "... dan {$remaining} siswa lainnya.";
        }

        $lines[] = '';
        $lines[] = "Total siswa hadir: {$totalCount} orang.";

        return implode("\n", $lines);
    }

    /**
     * Build formatted list of students who were late today.
     */
    protected function buildStudentsLateTodayReply(string $today, int $limit = 40): string
    {
        $attendances = Attendance::with('user.student')
            ->whereDate('attendance_date', $today)
            ->where('status', 'late')
            ->whereHas('user', fn ($q) => $q->where('role', 'student'))
            ->orderBy('attendance_time', 'asc')
            ->get();

        $totalCount = $attendances->count();
        if ($totalCount === 0) {
            return 'Tidak ada siswa yang terlambat hari ini. Semua siswa yang hadir datang tepat waktu.';
        }

        $lines = ['Berikut daftar siswa yang terlambat hari ini:', ''];
        $index = 1;
        foreach ($attendances->take($limit) as $att) {
            $name = $att->user?->name ?? 'Siswa';
            $timeStr = $att->attendance_time ? Carbon::parse($att->attendance_time)->format('H.i').' WIB' : 'Waktu tidak tercatat';
            $lines[] = "{$index}. {$name} — {$timeStr}";
            $index++;
        }

        if ($totalCount > $limit) {
            $remaining = $totalCount - $limit;
            $lines[] = '';
            $lines[] = "... dan {$remaining} siswa lainnya.";
        }

        $lines[] = '';
        $lines[] = "Total siswa terlambat: {$totalCount} orang.";

        return implode("\n", $lines);
    }

    /**
     * Build formatted list of active students who haven't attended today.
     */
    protected function buildStudentsUnattendedTodayReply(string $today, int $limit = 40): string
    {
        $attendedStudentUserIds = Attendance::whereDate('attendance_date', $today)
            ->whereHas('user', fn ($q) => $q->where('role', 'student'))
            ->pluck('user_id');

        $unattendedStudents = Student::with('user')
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->whereNotIn('user_id', $attendedStudentUserIds)
            ->get()
            ->sortBy(fn ($s) => mb_strtolower($s->user?->name ?? '', 'UTF-8'));

        $totalCount = $unattendedStudents->count();
        if ($totalCount === 0) {
            return 'Semua siswa aktif telah melakukan absensi hari ini. Tidak ada siswa yang belum absen.';
        }

        $lines = ['Berikut daftar siswa yang belum melakukan absensi hari ini:', ''];
        $index = 1;
        foreach ($unattendedStudents->take($limit) as $student) {
            $name = $student->user?->name ?? 'Siswa';
            $class = $student->class ? "Kelas {$student->class}" : 'Kelas -';
            $lines[] = "{$index}. {$name} ({$class})";
            $index++;
        }

        if ($totalCount > $limit) {
            $remaining = $totalCount - $limit;
            $lines[] = '';
            $lines[] = "... dan {$remaining} siswa lainnya.";
        }

        $lines[] = '';
        $lines[] = "Total siswa belum absen: {$totalCount} orang.";

        return implode("\n", $lines);
    }

    /**
     * Build formatted list of students who have leave/sick records today.
     */
    protected function buildStudentsLeaveTodayReply(string $today, int $limit = 40): string
    {
        $leaves = Attendance::with('user.student')
            ->whereDate('attendance_date', $today)
            ->whereIn('status', ['excused', 'sick'])
            ->whereHas('user', fn ($q) => $q->where('role', 'student'))
            ->get();

        $totalCount = $leaves->count();
        if ($totalCount === 0) {
            return 'Tidak ada siswa yang tercatat izin atau sakit hari ini.';
        }

        $lines = ['Daftar Siswa yang Izin atau Sakit Hari Ini:'];
        $index = 1;
        foreach ($leaves->take($limit) as $item) {
            $name = $item->user?->name ?? 'Siswa';
            $class = $item->user?->student?->class ? "Kelas {$item->user->student->class}" : '';
            $classStr = $class ? " ({$class})" : '';
            $type = $item->status === 'sick' ? 'Sakit' : 'Izin';
            $reason = $item->reason ? "\"{$item->reason}\"" : 'Keterangan tidak dicantumkan';
            $approval = $item->getApprovalStatusLabel();
            $lines[] = "{$index}. {$name}{$classStr} - {$type} ({$reason}, Status: {$approval})";
            $index++;
        }

        if ($totalCount > $limit) {
            $remaining = $totalCount - $limit;
            $lines[] = "... dan {$remaining} siswa lainnya.";
        }

        $lines[] = '';
        $lines[] = "Total siswa izin atau sakit hari ini: {$totalCount} siswa.";

        return implode("\n", $lines);
    }

    /**
     * Build formatted list of students who are most frequently late.
     */
    protected function buildStudentsMostFrequentlyLateReply(int $limit = 5): string
    {
        $records = Attendance::select('user_id', DB::raw('count(*) as late_count'))
            ->where('status', 'late')
            ->whereHas('user', fn ($q) => $q->where('role', 'student'))
            ->groupBy('user_id')
            ->orderByDesc('late_count')
            ->take($limit)
            ->with('user.student')
            ->get();

        if ($records->isEmpty()) {
            return 'Belum ada data siswa yang tercatat terlambat dalam sistem.';
        }

        $lines = ['Siswa yang Paling Sering Terlambat:'];
        $index = 1;
        foreach ($records as $item) {
            $name = $item->user?->name ?? 'Siswa';
            $class = $item->user?->student?->class ? "Kelas {$item->user->student->class}" : '';
            $classStr = $class ? " ({$class})" : '';
            $lines[] = "{$index}. {$name}{$classStr} - {$item->late_count} kali terlambat";
            $index++;
        }

        return implode("\n", $lines);
    }

    /**
     * Build reply for students pending approval (leave requests or inactive accounts).
     */
    protected function buildStudentsPendingApprovalReply(): string
    {
        $pendingLeaves = Attendance::whereHas('user', fn ($q) => $q->where('role', 'student'))
            ->whereIn('status', ['excused', 'sick'])
            ->where('approval_status', 'pending')
            ->count();

        $inactiveStudents = Student::whereHas('user', fn ($q) => $q->where('status', 'inactive'))->count();

        $reply = "Jumlah pengajuan izin dan sakit siswa yang belum disetujui (menunggu persetujuan guru) saat ini adalah {$pendingLeaves} pengajuan.";
        if ($inactiveStudents > 0) {
            $reply .= " Selain itu, terdapat {$inactiveStudents} akun siswa yang berstatus non-aktif.";
        }

        return $reply;
    }

    /**
     * Build formatted list of all registered teachers from database.
     */
    protected function buildTeacherListReply(int $limit = 30): string
    {
        $teachers = Teacher::with('user')->get()->sortBy(function ($t) {
            return mb_strtolower($t->user?->name ?? '', 'UTF-8');
        });

        $totalCount = $teachers->count();
        if ($totalCount === 0) {
            return 'Saat ini belum ada data guru yang terdaftar dalam sistem.';
        }

        $lines = ['Berikut daftar guru yang terdaftar di SmartAttendance:', ''];
        $index = 1;
        foreach ($teachers->take($limit) as $teacher) {
            $name = $teacher->user?->name ?? 'Tanpa Nama';
            $nip = $teacher->nip ? "NIP: {$teacher->nip}" : 'NIP: -';
            $lines[] = "{$index}. {$name} ({$nip})";
            $index++;
        }

        if ($totalCount > $limit) {
            $remaining = $totalCount - $limit;
            $lines[] = '';
            $lines[] = "... dan {$remaining} guru lainnya.";
        }

        $lines[] = '';
        $lines[] = "Total guru terdaftar: {$totalCount} guru.";

        return implode("\n", $lines);
    }

    /**
     * Build formatted list of teachers who attended today.
     */
    protected function buildTeachersPresentTodayReply(string $today, int $limit = 30): string
    {
        $attendances = Attendance::with('user.teacher')
            ->whereDate('attendance_date', $today)
            ->whereIn('status', ['present', 'late'])
            ->whereHas('user', fn ($q) => $q->where('role', 'teacher'))
            ->orderBy('attendance_time', 'asc')
            ->get();

        $totalCount = $attendances->count();
        if ($totalCount === 0) {
            return 'Belum ada guru yang tercatat hadir hari ini.';
        }

        $dateFormatted = Carbon::parse($today)->locale('id')->translatedFormat('d F Y');
        $lines = ["Daftar Guru yang Hadir Hari Ini ({$dateFormatted}):"];
        $index = 1;
        foreach ($attendances->take($limit) as $att) {
            $name = $att->user?->name ?? 'Guru';
            $nip = $att->user?->teacher?->nip ? "NIP: {$att->user->teacher->nip}" : '';
            $nipStr = $nip ? " ({$nip})" : '';
            $timeStr = $att->attendance_time ? "pukul {$att->attendance_time} WIB" : '';
            $statusLabel = $att->status === 'late' ? 'Terlambat' : 'Tepat Waktu';
            $lines[] = "{$index}. {$name}{$nipStr} - Hadir {$timeStr} ({$statusLabel})";
            $index++;
        }

        $lines[] = '';
        $lines[] = "Total guru hadir hari ini: {$totalCount} guru.";

        return implode("\n", $lines);
    }

    /**
     * Build formatted list of teachers who are late today.
     */
    protected function buildTeachersLateTodayReply(string $today, int $limit = 30): string
    {
        $attendances = Attendance::with('user.teacher')
            ->whereDate('attendance_date', $today)
            ->where('status', 'late')
            ->whereHas('user', fn ($q) => $q->where('role', 'teacher'))
            ->orderBy('attendance_time', 'asc')
            ->get();

        $totalCount = $attendances->count();
        if ($totalCount === 0) {
            return 'Tidak ada guru yang terlambat hari ini. Semua guru yang hadir datang tepat waktu.';
        }

        $dateFormatted = Carbon::parse($today)->locale('id')->translatedFormat('d F Y');
        $lines = ["Daftar Guru yang Terlambat Hari Ini ({$dateFormatted}):"];
        $index = 1;
        foreach ($attendances->take($limit) as $att) {
            $name = $att->user?->name ?? 'Guru';
            $nip = $att->user?->teacher?->nip ? "NIP: {$att->user->teacher->nip}" : '';
            $nipStr = $nip ? " ({$nip})" : '';
            $timeStr = $att->attendance_time ? "pukul {$att->attendance_time} WIB" : '';
            $lines[] = "{$index}. {$name}{$nipStr} - Hadir {$timeStr}";
            $index++;
        }

        $lines[] = '';
        $lines[] = "Total guru terlambat hari ini: {$totalCount} guru.";

        return implode("\n", $lines);
    }

    /**
     * Build formatted list of active teachers who haven't attended today.
     */
    protected function buildTeachersUnattendedTodayReply(string $today, int $limit = 30): string
    {
        $attendedTeacherUserIds = Attendance::whereDate('attendance_date', $today)
            ->whereHas('user', fn ($q) => $q->where('role', 'teacher'))
            ->pluck('user_id');

        $unattendedTeachers = Teacher::with('user')
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->whereNotIn('user_id', $attendedTeacherUserIds)
            ->get()
            ->sortBy(fn ($t) => mb_strtolower($t->user?->name ?? '', 'UTF-8'));

        $totalCount = $unattendedTeachers->count();
        if ($totalCount === 0) {
            return 'Semua guru aktif telah melakukan absensi hari ini. Tidak ada guru yang belum absen.';
        }

        $lines = ['Daftar Guru yang Belum Melakukan Absensi Hari Ini:'];
        $index = 1;
        foreach ($unattendedTeachers->take($limit) as $teacher) {
            $name = $teacher->user?->name ?? 'Guru';
            $nip = $teacher->nip ? "NIP: {$teacher->nip}" : 'NIP: -';
            $lines[] = "{$index}. {$name} ({$nip})";
            $index++;
        }

        $lines[] = '';
        $lines[] = "Total guru belum absen hari ini: {$totalCount} guru.";

        return implode("\n", $lines);
    }

    /**
     * Build formatted list of all administrators.
     */
    protected function buildAdminListReply(): string
    {
        $admins = User::where('role', 'admin')->orderBy('name')->get();
        $totalCount = $admins->count();

        if ($totalCount === 0) {
            return 'Belum ada administrator yang terdaftar.';
        }

        $lines = ['Berikut daftar administrator yang terdaftar di SmartAttendance:', ''];
        $index = 1;
        foreach ($admins as $admin) {
            $name = $admin->name ?: 'Administrator';
            $email = $admin->email ? "Email: {$admin->email}" : '';
            $emailStr = $email ? " ({$email})" : '';
            $lines[] = "{$index}. {$name}{$emailStr}";
            $index++;
        }

        $lines[] = '';
        $lines[] = "Total admin terdaftar: {$totalCount} admin.";

        return implode("\n", $lines);
    }

    /**
     * Build comprehensive dashboard statistics summary.
     */
    protected function buildOverallDashboardStatsReply(string $today): string
    {
        $totalStudents = Student::count();
        $totalTeachers = Teacher::count();
        $totalAdmins = User::where('role', 'admin')->count();

        $todayPresent = Attendance::whereDate('attendance_date', $today)->where('status', 'present')->count();
        $todayLate = Attendance::whereDate('attendance_date', $today)->where('status', 'late')->count();

        $totalActivePersonnel = User::whereIn('role', ['student', 'teacher'])->where('status', 'active')->count();
        $attendedUserIds = Attendance::whereDate('attendance_date', $today)->distinct('user_id')->pluck('user_id');
        $unattendedCount = max(0, $totalActivePersonnel - $attendedUserIds->count());

        $leavesToday = Attendance::whereDate('attendance_date', $today)->whereIn('status', ['excused', 'sick'])->count();
        $pendingLeaves = Attendance::whereIn('status', ['excused', 'sick'])->where('approval_status', 'pending')->count();
        $approvedToday = Attendance::whereIn('status', ['excused', 'sick'])->where('approval_status', 'approved')->whereDate('attendance_date', $today)->count();

        $allAttendancesCount = Attendance::count();
        $overallPresentCount = Attendance::whereIn('status', ['present', 'late'])->count();
        $overallRate = $allAttendancesCount > 0 ? round(($overallPresentCount / $allAttendancesCount) * 100, 1) : 100.0;

        return "Ringkasan Statistik Dashboard SmartAttendance:\n"
            ."- Total Siswa Terdaftar: {$totalStudents} siswa\n"
            ."- Total Guru Terdaftar: {$totalTeachers} guru\n"
            ."- Total Administrator: {$totalAdmins} admin\n"
            ."- Hadir Tepat Waktu Hari Ini: {$todayPresent} orang\n"
            ."- Terlambat Hari Ini: {$todayLate} orang\n"
            ."- Belum Absen Hari Ini: {$unattendedCount} orang\n"
            ."- Izin / Sakit Hari Ini: {$leavesToday} orang\n"
            ."- Pengajuan Menunggu Persetujuan: {$pendingLeaves} pengajuan\n"
            ."- Pengajuan Disetujui Hari Ini: {$approvedToday} pengajuan\n"
            ."- Tingkat Kehadiran Keseluruhan: {$overallRate}%";
    }

    /**
     * Extract class identifier from user query if present.
     */
    protected function extractClassFromQuery(string $text): ?string
    {
        $stopWords = 'yang|sakit|izin|ijin|alasan|karena|kenapa|mengapa|ada|siapa|berapa|jumlah|hadir|terlambat|telat|belum|terdaftar';
        if (preg_match('/\bkelas\s+([a-zA-Z0-9\-\.\s]+?)(?:\s+(?:'.$stopWords.')|$|\?)/iu', $text, $matches)) {
            $extracted = trim($matches[1]);
            // Avoid extracting generic words
            if (! in_array(mb_strtolower($extracted, 'UTF-8'), ['saya', 'kamu', 'anda', 'dia', 'tersebut', 'ini', 'itu', 'berapa', 'siapa', 'apa'], true) && strlen($extracted) >= 2) {
                return $extracted;
            }
        }

        return null;
    }

    /**
     * Build friendly clarification reply tailored to the authenticated user's role.
     */
    protected function buildClarificationReply(User $user): string
    {
        return match ($user->role) {
            'admin' => "Maaf, saya belum memahami pertanyaan Anda. Sebagai Administrator, Anda dapat menanyakan:\n"
                ."- \"Berapa total siswa?\" atau \"Siapa saja nama siswa?\"\n"
                ."- \"Siapa siswa yang hadir hari ini?\" atau \"Siapa siswa yang terlambat?\"\n"
                ."- \"Siapa siswa yang belum absen?\" atau \"Siapa siswa yang izin atau sakit?\"\n"
                ."- \"Berapa jumlah siswa berdasarkan kelas?\" atau \"Siapa saja siswa kelas X-A?\"\n"
                ."- \"Siapa saja nama guru?\" atau \"Berapa total guru?\"\n"
                ."- \"Siapa saja admin?\" atau \"Berapa total admin?\"\n"
                .'- "Statistik dashboard" atau "Siapa saya?"',
            'teacher' => "Maaf, saya belum memahami pertanyaan Bapak/Ibu. Sebagai Guru, Anda dapat menanyakan:\n"
                ."- \"Siapa saja nama siswa?\" atau \"Berapa total siswa?\"\n"
                ."- \"Siapa siswa yang hadir hari ini?\" atau \"Siapa siswa yang belum absen?\"\n"
                ."- \"Siapa siswa yang terlambat?\" atau \"Siapa siswa yang izin atau sakit?\"\n"
                ."- \"Pengajuan izin yang menunggu persetujuan\"\n"
                ."- \"Siapa saja nama guru?\" atau \"Siapa guru yang hadir hari ini?\"\n"
                .'- "Siapa saya?" atau "Apa peran akun saya?"',
            'student' => "Maaf, saya belum memahami pertanyaan kamu. Sebagai Siswa, kamu dapat menanyakan:\n"
                ."- \"Apakah saya sudah absen hari ini?\"\n"
                ."- \"Berapa kali saya hadir tepat waktu?\"\n"
                ."- \"Berapa kali saya terlambat?\"\n"
                ."- \"Berapa persentase kehadiran saya?\"\n"
                ."- \"Siapa saya?\" atau \"Apa peran akun saya?\"\n"
                .'- "Cara pengajuan izin/sakit" atau "Cara absensi mandiri"',
            default => 'Maaf, saya belum memahami pertanyaan tersebut. Silakan ketik pertanyaan seputar statistik kehadiran, identitas akun, atau panduan penggunaan sistem SmartAttendance.',
        };
    }

    /**
     * Handle general system queries (Camera, Scanner, Hours, Rules).
     *
     * @return array{success: bool, reply: string, intent: string}|null
     */
    protected function handleGeneralSystemQueries(string $text, string $role): ?array
    {
        // Jam Kehadiran & Toleransi
        if ($this->matchAny($text, ['jam masuk', 'jam absensi', 'jam berapa', 'batas terlambat', 'toleransi keterlambatan', 'aturan jam'])) {
            $open = config('attendance.opening_time', '06:30');
            $late = config('attendance.late_after', '07:15');

            return [
                'success' => true,
                'reply' => "Ketentuan Jam Kehadiran SmartAttendance:\n"
                    ."- Presensi dibuka mulai pukul {$open} WIB.\n"
                    ."- Batas toleransi kehadiran tepat waktu adalah pukul {$late} WIB.\n"
                    ."- Presensi setelah pukul {$late} WIB akan otomatis tercatat sebagai Terlambat.",
                'intent' => 'system_hours',
            ];
        }

        // Cara Absensi Mandiri / Scanner Wajah
        if ($this->matchAny($text, ['cara absensi', 'cara absen', 'cara presensi', 'cara scan wajah', 'cara scanner'])) {
            return [
                'success' => true,
                'reply' => "Cara Melakukan Presensi Mandiri di SmartAttendance:\n"
                    ."1. Buka menu Scanner Presensi (/attendance).\n"
                    ."2. Izinkan akses kamera browser saat diminta.\n"
                    ."3. Posisikan wajah Anda tegak lurus di dalam bingkai oval panduan.\n"
                    ."4. Tunggu sistem melakukan deteksi liveness dan mencocokkan biometrik wajah.\n"
                    .'5. Notifikasi berhasil akan muncul beserta jam kehadiran Anda.',
                'intent' => 'system_attendance_guide',
            ];
        }

        // Kendala Kamera Tidak Aktif
        if ($this->matchAny($text, ['kamera tidak aktif', 'kamera tidak bisa', 'izin kamera', 'kamera error', 'kamera mati', 'tidak bisa buka kamera'])
            || (str_contains($text, 'kamera') && (str_contains($text, 'tidak') || str_contains($text, 'error') || str_contains($text, 'mati') || str_contains($text, 'izin') || str_contains($text, 'rusak')))) {
            return [
                'success' => true,
                'reply' => "Solusi Kamera Tidak Aktif:\n"
                    ."1. Periksa ikon gembok/izin di address bar browser dan pastikan izin Kamera diatur ke 'Allow'.\n"
                    ."2. Pastikan tidak ada aplikasi lain yang sedang menggunakan webcam (Zoom, Google Meet, Teams).\n"
                    ."3. Muat ulang (refresh) halaman browser.\n"
                    .'4. Jika menggunakan HP, gunakan browser Chrome atau Safari versi terbaru.',
                'intent' => 'system_camera_troubleshoot',
            ];
        }

        // Kendala Verifikasi Wajah Gagal
        if ($this->matchAny($text, ['verifikasi gagal', 'wajah tidak terdeteksi', 'gagal verifikasi', 'muka tidak cocok', 'scan gagal'])
            || ((str_contains($text, 'wajah') || str_contains($text, 'muka') || str_contains($text, 'verifikasi')) && (str_contains($text, 'gagal') || str_contains($text, 'tidak') || str_contains($text, 'susah')))) {
            return [
                'success' => true,
                'reply' => "Solusi Verifikasi Wajah Gagal:\n"
                    ."1. Pastikan pencahayaan ruangan cukup terang dan tidak membelakangi cahaya (backlight).\n"
                    ."2. Lepas kacamata hitam, topi, atau masker yang menutupi area wajah.\n"
                    ."3. Dekatkan wajah agar mengisi bingkai panduan oval secara simetris.\n"
                    .'4. Pastikan sampel wajah Anda telah didaftarkan oleh Administrator sekolah di sistem.',
                'intent' => 'system_face_troubleshoot',
            ];
        }

        // Cara Pengajuan Izin / Sakit
        if ($this->matchAny($text, ['cara izin', 'cara sakit', 'alur izin', 'alur sakit', 'ajukan izin', 'ajukan sakit', 'pengajuan izin sakit'])) {
            return [
                'success' => true,
                'reply' => "Alur Pengajuan Izin atau Sakit:\n"
                    ."1. Masuk ke menu 'Ajukan Izin / Sakit' pada portal siswa.\n"
                    ."2. Pilih jenis permohonan (Izin atau Sakit), masukkan tanggal dan keterangan alasan.\n"
                    ."3. Unggah bukti pendukung (surat dokter atau surat keterangan orang tua).\n"
                    .'4. Kirim permohonan dan tunggu peninjauan persetujuan dari Bapak/Ibu Guru.',
                'intent' => 'system_leave_guide',
            ];
        }

        return null;
    }

    /**
     * Helper to check if string contains any of the given keywords or phrases.
     *
     * @param  list<string>  $phrases
     */
    protected function matchAny(string $haystack, array $phrases): bool
    {
        foreach ($phrases as $phrase) {
            if (str_contains($haystack, mb_strtolower($phrase, 'UTF-8'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle student leave, absence, and sickness inquiries using controlled database facts.
     *
     * @return array{success: bool, reply: string, intent: string}|null
     */
    protected function handleStudentLeaveInquiries(User $user, string $rawMessage, string $normalized): ?array
    {
        // 1. Role-based privacy protection:
        // A student is NOT allowed to view other students' private reasons or class/aggregate lists
        if ($user->isStudent()) {
            if ($this->leaveQueryService->isAggregateQuery($rawMessage) || $this->leaveQueryService->isAggregateQuery($normalized)) {
                return [
                    'success' => true,
                    'reply' => 'Demi menjaga privasi siswa, informasi alasan izin/sakit siswa lain tidak dapat diakses. Anda hanya dapat melihat data kehadiran Anda sendiri.',
                    'intent' => 'privacy_restricted',
                ];
            }

            $resolved = $this->leaveQueryService->resolveTargetStudent($rawMessage, $user);
            if ($resolved['student'] && $resolved['student']->user_id !== $user->id) {
                return [
                    'success' => true,
                    'reply' => 'Demi menjaga privasi siswa, informasi alasan izin/sakit siswa lain tidak dapat diakses. Anda hanya dapat melihat data kehadiran Anda sendiri.',
                    'intent' => 'privacy_restricted',
                ];
            }
        }

        // 2. Aggregate queries (e.g. "Siapa saja siswa yang sedang izin hari ini?", "Berapa siswa yang izin hari ini?", "Apa alasan izin siswa kelas XII-RPL 1?")
        if ($this->leaveQueryService->isAggregateQuery($rawMessage) || $this->leaveQueryService->isAggregateQuery($normalized)) {
            $aggregate = $this->leaveQueryService->handleAggregateQuery($rawMessage, $user);
            if ($aggregate !== null) {
                $systemPrompt = "Anda adalah asisten cerdas SmartAttendance School.\n"
                    ."Jawab pertanyaan pengguna secara akurat, sopan, dan jelas dalam Bahasa Indonesia berdasarkan FAKTA DATABASE berikut.\n"
                    ."ATURAN WAJIB:\n"
                    ."1. Jawab HANYA berdasarkan fakta database di bawah ini. Jangan mengarang data atau jumlah yang tidak tercatat.\n"
                    ."2. Jangan membuat diagnosis medis.\n"
                    ."3. Sebutkan status persetujuan izin secara akurat (pending, approved, rejected).\n\n"
                    .$aggregate['facts'];

                try {
                    $aiReply = $this->aiManager->generateReply($systemPrompt, $rawMessage);
                } catch (AiException $e) {
                    return [
                        'success' => true,
                        'reply' => $aggregate['fallbackReply']."\n\nCatatan: Jawaban di atas disusun langsung dari data database karena layanan AI tidak dapat digunakan. ".$e->getMessage(),
                        'intent' => 'attendance_aggregate_leaves',
                    ];
                }

                return [
                    'success' => true,
                    'reply' => $aiReply,
                    'intent' => 'attendance_aggregate_leaves',
                ];
            }
        }

        // 3. Specific student query (e.g. "Arya sakit karena apa?", "Kenapa Arya tidak masuk hari ini?", "Apakah pengajuan sakit Arya sudah disetujui guru?", "Tampilkan riwayat izin Arya")
        $resolved = $this->leaveQueryService->resolveTargetStudent($rawMessage, $user);

        if ($resolved['ambiguous']) {
            $extractedName = $this->extractStudentNameQuick($rawMessage) ?: 'tersebut';
            $clarification = $this->leaveQueryService->buildClarificationReply($extractedName, $resolved['candidates']);

            return [
                'success' => true,
                'reply' => $clarification,
                'intent' => 'student_name_ambiguous',
            ];
        }

        if ($resolved['notFound']) {
            // Only intercept when the query was actually targeting a specific person.
            // $resolved['extractedName'] is set only when extractStudentName() found a candidate.
            // Also intercept when the query has an explicit personal-leave inquiry pattern
            // (name mentioned + leave verb, or first-word-name sentence pattern).
            $hasExtractedName = $resolved['extractedName'] !== null;

            // Explicit personal inquiry: "Kenapa [Name] tidak masuk", "[Name] izin kenapa", etc.
            $hasPersonalLeavePattern = $hasExtractedName
                || (bool) preg_match('/(?:kenapa|mengapa)\s+\S+/i', $rawMessage)
                || (bool) preg_match('/^\s*[a-zA-Z]{3,}[\s\-\'\.][a-zA-Z\s\.\'\-]+\s+(?:izin|ijin|sakit|tidak masuk|tidak hadir|absen)/iu', $rawMessage);

            // Don't intercept general system how-to guides (e.g. "cara izin", "cara sakit", "jam berapa", "batas terlambat")
            $isGeneralGuide = (bool) preg_match('/\b(cara|alur|panduan|bagaimana|aturan|jam|batas|toleransi)\s+(izin|sakit|absen|presensi|masuk|terlambat|keterlambatan)\b/i', $rawMessage);

            if ($hasPersonalLeavePattern && ! $isGeneralGuide) {
                return [
                    'success' => true,
                    'reply' => 'Saya belum menemukan catatan izin yang sesuai untuk siswa tersebut. Silakan periksa nama atau tanggal yang dimaksud.',
                    'intent' => 'student_not_found',
                ];
            }

            return null;
        }

        $student = $resolved['student'];
        if (! $student) {
            return null;
        }

        // Guard against cross-student access for student role
        if ($user->isStudent() && $student->user_id !== $user->id) {
            return [
                'success' => true,
                'reply' => 'Demi menjaga privasi siswa, informasi alasan izin/sakit siswa lain tidak dapat diakses. Anda hanya dapat melihat data kehadiran Anda sendiri.',
                'intent' => 'privacy_restricted',
            ];
        }

        $factsData = $this->leaveQueryService->getStudentLeaveFacts($student, $rawMessage);

        if (! $factsData['hasData']) {
            return [
                'success' => true,
                'reply' => $factsData['fallbackReply'],
                'intent' => 'student_leave_no_data',
            ];
        }

        // Multi-Provider AI synthesis grounded strictly on verified database facts
        $systemPrompt = "Anda adalah asisten cerdas SmartAttendance School.\n"
            ."Jawab pertanyaan pengguna secara akurat, sopan, dan jelas dalam Bahasa Indonesia berdasarkan FAKTA DATABASE SMARTATTENDANCE berikut.\n"
            ."ATURAN WAJIB:\n"
            ."1. Jawab HANYA berdasarkan fakta di bawah ini. Jangan mengarang alasan, tanggal, atau data yang tidak tercatat.\n"
            ."2. Jangan membuat diagnosis medis; sebutkan alasan sakit persis seperti keterangan yang tercatat di database.\n"
            ."3. Bedakan dengan tegas antara status 'pending' (masih menunggu persetujuan guru), 'approved' (disetujui), dan 'rejected' (ditolak). Jangan menganggap pengajuan pending sebagai sudah disetujui!\n"
            ."4. Sebutkan nama siswa, tanggal, kelas, dan status persetujuan dengan jelas untuk menghindari ambiguitas.\n"
            ."5. Format jawaban alasan izin siswa:\n"
            ."   - Jika data memiliki keterangan izin: '[Nama] tercatat izin tidak masuk pada [tanggal]. Keterangan yang tersimpan adalah [alasan].'\n"
            ."   - Jika data izin tanpa keterangan alasan: '[Nama] tercatat izin tidak masuk pada [tanggal], tetapi alasan izin belum dicantumkan dalam data.'\n\n"
            .$factsData['facts'];

        try {
            $aiReply = $this->aiManager->generateReply($systemPrompt, $rawMessage);
        } catch (AiException $e) {
            return [
                'success' => true,
                'reply' => $factsData['fallbackReply']."\n\nCatatan: Jawaban di atas disusun langsung dari data database karena layanan AI tidak dapat digunakan. ".$e->getMessage(),
                'intent' => 'student_leave_inquiry',
            ];
        }

        return [
            'success' => true,
            'reply' => $aiReply,
            'intent' => 'student_leave_inquiry',
        ];
    }

    /**
     * Fallback to active AI assistant for unrecognized questions about SmartAttendance.
     */
    protected function handleSmartAssistantFallback(string $message, User $user): ?string
    {
        $roleLabel = match ($user->role) {
            'admin' => 'Administrator',
            'teacher' => 'Guru',
            'student' => 'Siswa',
            default => 'Pengguna',
        };

        $today = Carbon::today()->toDateString();
        $todayFormatted = Carbon::today()->locale('id')->translatedFormat('d F Y');
        $activeAi = AiSetting::getActive();
        $aiLabel = $activeAi && $activeAi->provider ? AiSetting::providerLabel($activeAi->provider).' (model '.($activeAi->model ?: AiSetting::defaultModel($activeAi->provider)).')' : 'SmartAssistant AI';

        $totalStudents = Student::count();
        $totalTeachers = Teacher::count();
        $presentToday = Attendance::whereDate('attendance_date', $today)->whereIn('status', ['present', 'late'])->whereHas('user', fn ($q) => $q->where('role', 'student'))->count();
        $lateToday = Attendance::whereDate('attendance_date', $today)->where('status', 'late')->whereHas('user', fn ($q) => $q->where('role', 'student'))->count();

        $systemPrompt = "Anda adalah asisten cerdas SmartAttendance School.\n"
            ."Bantu jawab pertanyaan {$roleLabel} ({$user->name}) seputar panduan penggunaan SmartAttendance (presensi scanner wajah, portal siswa/guru/admin, alur izin/sakit, jam operasional) dalam Bahasa Indonesia yang ramah, sopan, dan profesional.\n\n"
            ."FAKTA SISTEM & DATABASE SMARTATTENDANCE SAAT INI (REAL-TIME):\n"
            ."- Model AI Aktif: {$aiLabel}\n"
            ."- Tanggal: {$todayFormatted}\n"
            ."- Total Siswa Terdaftar: {$totalStudents} siswa\n"
            ."- Total Guru Terdaftar: {$totalTeachers} guru\n"
            ."- Siswa Hadir Hari Ini: {$presentToday} (Terlambat: {$lateToday})\n\n"
            ."PENTING:\n"
            ."1. Jawab HANYA berdasarkan fakta di atas. Jangan mengarang data absensi fiktif atau angka palsu.\n"
            ."2. Jika pengguna menanyakan model AI yang digunakan, sebutkan bahwa Anda menggunakan {$aiLabel}.\n"
            ."3. Jika pertanyaan di luar lingkup SmartAttendance dan sekolah, tolak secara sopan dengan kalimat: '".self::OFF_TOPIC_REJECTION."'.";

        return $this->aiManager->generateReply($systemPrompt, $message);
    }

    /**
     * Quick helper to extract student name for clarification prompt.
     */
    protected function extractStudentNameQuick(string $text): ?string
    {
        return $this->leaveQueryService->extractStudentName($text);
    }
}
