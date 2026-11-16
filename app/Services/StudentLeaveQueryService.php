<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class StudentLeaveQueryService
{
    /**
     * Determine if query is related to student absence/leave/sickness.
     */
    public function isLeaveOrAbsenceQuery(string $text): bool
    {
        $patterns = [
            '/\b(sakit|izin|ijin|alpha|alpa|tidak masuk|tidak hadir|absen|kehadiran|terlambat|telat|keterlambatan)\b/i',
            '/\b(kenapa|mengapa)\s+.*?\s+(tidak masuk|tidak hadir|sakit|izin|absen|terlambat|telat)\b/i',
            '/\b(alasan|penyebab|keterangan)\s+(sakit|izin|ijin|tidak hadir|tidak masuk)\b/i',
            '/\b(pengajuan|permohonan)\s+(sakit|izin|ijin)\b/i',
            '/\b(siapa|siapakah)\s+(saja\s+)?(siswa\s+)?(yang\s+)?(sedang\s+)?(izin|ijin|sakit)\b/i',
            '/\b(berapa\s+(jumlah\s+)?(siswa\s+)?(yang\s+)?(izin|ijin|sakit|terlambat))\b/i',
            '/\briwayat\s+(izin|ijin|sakit|keterlambatan|kehadiran)\b/i',
            '/\b(disetujui|menunggu persetujuan|status izin|status sakit)\b/i',
            '/\b(sering terlambat|sering telat)\b/i',
            '/\b(rekap\s+(?:absensi|kehadiran)|status\s+kehadiran)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if query is an aggregate list question (who is on leave, count of leaves, class reasons, attendance recap).
     */
    public function isAggregateQuery(string $text): bool
    {
        return (bool) preg_match('/\b(siapa saja|siapa yang|berapa siswa|berapa murid|alasan izin siswa|alasan izin murid|alasan sakit siswa|alasan ketidakhadiran siswa|keterangan sakit siswa|siswa kelas|rekap absensi|rekap kehadiran|rekap status kehadiran)\b/i', $text);
    }

    /**
     * Handle aggregate queries (e.g., "Siapa saja siswa yang sedang izin hari ini?", "Siapa saja siswa yang izin sakit minggu ini?", "Berapa siswa yang izin hari ini?", "Tampilkan rekap absensi siswa berdasarkan status kehadiran").
     *
     * @return array{facts: string, fallbackReply: string}|null
     */
    public function handleAggregateQuery(string $text, User $user): ?array
    {
        $today = Carbon::today()->toDateString();
        $todayFormatted = Carbon::today()->translatedFormat('d F Y');
        $isWeek = (bool) preg_match('/\b(minggu ini|pekan ini)\b/i', $text);
        $targetClass = $this->extractClass($text);

        // 1. Rekap absensi siswa berdasarkan status kehadiran
        if (preg_match('/\b(rekap\s+(?:absensi|kehadiran)|status\s+kehadiran)\b/i', $text)) {
            $studentQuery = Student::query();
            if ($targetClass !== null) {
                $studentQuery->where('class', 'like', "%{$targetClass}%");
            }
            $totalStudents = $studentQuery->count();

            $attQuery = Attendance::query();
            if ($targetClass !== null) {
                $attQuery->whereHas('user.student', fn ($q) => $q->where('class', 'like', "%{$targetClass}%"));
            }

            if ($isWeek) {
                $startDate = Carbon::now()->startOfWeek()->toDateString();
                $endDate = Carbon::now()->endOfWeek()->toDateString();
                $attQuery->whereBetween('attendance_date', [$startDate, $endDate]);
                $periodLabel = 'Minggu Ini ('.Carbon::now()->startOfWeek()->translatedFormat('d M').' - '.Carbon::now()->endOfWeek()->translatedFormat('d M Y').')';
            } else {
                $attQuery->whereDate('attendance_date', $today);
                $periodLabel = "Hari Ini ({$todayFormatted})";
            }

            $attendances = $attQuery->get();
            $presentCount = $attendances->where('status', 'present')->count();
            $lateCount = $attendances->where('status', 'late')->count();
            $sickCount = $attendances->where('status', 'sick')->count();
            $excusedCount = $attendances->where('status', 'excused')->count();
            $totalRecorded = $presentCount + $lateCount + $sickCount + $excusedCount;
            $unrecordedCount = max(0, $totalStudents - $totalRecorded);

            $scopeLabel = $targetClass ? "Kelas {$targetClass} - {$periodLabel}" : $periodLabel;

            $facts = "Rekap Absensi Siswa ({$scopeLabel}):\n"
                ."- Total Siswa: {$totalStudents}\n"
                ."- Hadir Tepat Waktu: {$presentCount}\n"
                ."- Hadir Terlambat: {$lateCount}\n"
                ."- Sakit: {$sickCount}\n"
                ."- Izin: {$excusedCount}\n"
                ."- Belum Absen / Tanpa Keterangan: {$unrecordedCount}";

            $fallbackReply = "Rekap Absensi Siswa ({$scopeLabel}):\n"
                ."• Hadir Tepat Waktu: {$presentCount} siswa\n"
                ."• Terlambat: {$lateCount} siswa\n"
                ."• Sakit: {$sickCount} siswa\n"
                ."• Izin: {$excusedCount} siswa\n"
                ."• Belum Absen / Alfa: {$unrecordedCount} siswa\n"
                ."(Total siswa terdaftar: {$totalStudents} siswa)";

            return ['facts' => $facts, 'fallbackReply' => $fallbackReply];
        }

        // 2. "Berapa siswa yang izin hari ini / minggu ini?"
        if (preg_match('/\b(berapa\s+(jumlah\s+)?(siswa|murid)?\s*(yang)?\s*(sedang\s+)?(izin|ijin|sakit|tidak masuk|tidak hadir))\b/i', $text)) {
            $query = Attendance::with(['user.student'])
                ->whereIn('status', ['excused', 'sick']);

            if ($targetClass !== null) {
                $query->whereHas('user.student', fn ($q) => $q->where('class', 'like', "%{$targetClass}%"));
            }

            if ($isWeek) {
                $startDate = Carbon::now()->startOfWeek()->toDateString();
                $endDate = Carbon::now()->endOfWeek()->toDateString();
                $query->whereBetween('attendance_date', [$startDate, $endDate]);
                $periodText = 'minggu ini';
            } else {
                $query->whereDate('attendance_date', $today);
                $periodText = "hari ini ({$todayFormatted})";
            }

            $leaves = $query->get();
            $sickCount = $leaves->where('status', 'sick')->count();
            $excusedCount = $leaves->where('status', 'excused')->count();
            $totalCount = $leaves->count();

            $classText = $targetClass ? "di kelas {$targetClass} {$periodText}" : "{$periodText}";

            if ($totalCount === 0) {
                $facts = "Data Database: Tidak ada siswa yang tercatat izin atau sakit {$classText}.";
                $fallbackReply = "Berdasarkan data absensi, tidak ada siswa yang sedang izin maupun sakit {$classText}.";

                return ['facts' => $facts, 'fallbackReply' => $fallbackReply];
            }

            $facts = "Data Database:\n"
                ."Jumlah siswa tidak masuk {$classText}: {$totalCount} siswa\n"
                ."- Izin: {$excusedCount} siswa\n"
                ."- Sakit: {$sickCount} siswa";

            $fallbackReply = "Berdasarkan data SmartAttendance, terdapat {$totalCount} siswa yang tidak hadir {$classText} (Sakit: {$sickCount} siswa, Izin: {$excusedCount} siswa).";

            return ['facts' => $facts, 'fallbackReply' => $fallbackReply];
        }

        // 3. "Siapa saja siswa yang sedang izin hari ini / minggu ini?" / "Apa alasan izin siswa kelas ..."
        $query = Attendance::with(['user.student', 'approver'])
            ->whereIn('status', ['excused', 'sick']);

        if ($targetClass !== null) {
            $query->whereHas('user.student', fn ($q) => $q->where('class', 'like', "%{$targetClass}%"));
        }

        if ($isWeek) {
            $startDate = Carbon::now()->startOfWeek()->toDateString();
            $endDate = Carbon::now()->endOfWeek()->toDateString();
            $query->whereBetween('attendance_date', [$startDate, $endDate]);
            $periodText = 'minggu ini';
        } else {
            $query->whereDate('attendance_date', $today);
            $periodText = "hari ini ({$todayFormatted})";
        }

        $records = $query->orderBy('attendance_date', 'desc')->take(15)->get();
        $scopeText = $targetClass ? "kelas {$targetClass} {$periodText}" : "{$periodText}";

        if ($records->isEmpty()) {
            $facts = "Data Database: Tidak ditemukan catatan siswa izin atau sakit untuk {$scopeText}.";
            $fallbackReply = "Tidak ada siswa yang tercatat sedang izin atau sakit untuk {$scopeText}.";

            return ['facts' => $facts, 'fallbackReply' => $fallbackReply];
        }

        $lines = [];
        $itemNumber = 1;
        foreach ($records as $rec) {
            $sName = $rec->user?->name ?? 'Siswa';
            $sClass = $rec->user?->student?->class ?? '-';
            $sType = $rec->status === 'sick' ? 'Sakit' : 'Izin';
            $sReason = $rec->reason ?: 'Tidak ada keterangan alasan tambahan';
            $sApproval = $rec->getApprovalStatusLabel();
            $sDate = $rec->attendance_date?->translatedFormat('d M Y') ?? $todayFormatted;

            $lines[] = "{$itemNumber}. {$sName} ({$sClass}) - {$sType}: \"{$sReason}\" [Status: {$sApproval}, Tanggal: {$sDate}]";
            $itemNumber++;
        }

        $facts = "Daftar Siswa Izin/Sakit ({$scopeText}):\n".implode("\n", $lines);

        $fallbackReply = "Berikut daftar siswa yang sedang izin atau sakit untuk {$scopeText}:\n"
            .implode("\n", array_map(fn ($l) => "- {$l}", $lines));

        return ['facts' => $facts, 'fallbackReply' => $fallbackReply];
    }

    /**
     * Resolve target student from question text.
     *
     * @return array{student: ?Student, candidates: Collection<int, Student>, ambiguous: bool, notFound: bool}
     */
    public function resolveTargetStudent(string $text, User $currentUser): array
    {
        // If current user is a student asking about themselves (e.g. "saya", "aku")
        if ($currentUser->isStudent() && $this->isSelfReferential($text)) {
            $student = $currentUser->student;

            return [
                'student' => $student,
                'candidates' => new Collection($student ? [$student] : []),
                'ambiguous' => false,
                'notFound' => ($student === null),
            ];
        }

        $extractedName = $this->extractStudentName($text);
        if ($extractedName === null || trim($extractedName) === '') {
            return [
                'student' => null,
                'candidates' => new Collection,
                'ambiguous' => false,
                'notFound' => true,
                'extractedName' => null,
            ];
        }

        $cleanName = trim($extractedName);
        $targetClass = $this->extractClass($text);

        if ($targetClass !== null) {
            $cleanName = preg_replace('/\b'.preg_quote($targetClass, '/').'\b/i', '', $cleanName);
            $cleanName = trim(preg_replace('/\s+/', ' ', $cleanName));
        }

        // Search by name
        $students = Student::with('user')
            ->whereHas('user', function ($q) use ($cleanName) {
                $q->where('role', 'student')
                    ->where('name', 'like', "%{$cleanName}%");
            })
            ->get();

        // Fallback: search by NIS
        if ($students->isEmpty() && is_numeric($cleanName)) {
            $students = Student::with('user')->where('nis', $cleanName)->get();
        }

        // Fallback: if multi-word, try search by first word if specific match was not found
        if ($students->isEmpty() && str_contains($cleanName, ' ')) {
            $firstWord = explode(' ', $cleanName)[0];
            if (mb_strlen($firstWord) >= 3) {
                $students = Student::with('user')
                    ->whereHas('user', function ($q) use ($firstWord) {
                        $q->where('role', 'student')
                            ->where('name', 'like', "%{$firstWord}%");
                    })
                    ->get();
            }
        }

        if ($students->isEmpty()) {
            return [
                'student' => null,
                'candidates' => new Collection,
                'ambiguous' => false,
                'notFound' => true,
                'extractedName' => $cleanName,
            ];
        }

        // If multiple students match, try filtering with class context if present
        if ($students->count() > 1 && $targetClass !== null) {
            $classFiltered = $students->filter(fn ($s) => stripos($s->class, $targetClass) !== false);
            if ($classFiltered->count() === 1) {
                return [
                    'student' => $classFiltered->first(),
                    'candidates' => $classFiltered,
                    'ambiguous' => false,
                    'notFound' => false,
                    'extractedName' => $cleanName,
                ];
            }
        }

        if ($students->count() > 1) {
            return [
                'student' => null,
                'candidates' => $students,
                'ambiguous' => true,
                'notFound' => false,
                'extractedName' => $cleanName,
            ];
        }

        return [
            'student' => $students->first(),
            'candidates' => $students,
            'ambiguous' => false,
            'notFound' => false,
            'extractedName' => $cleanName,
        ];
    }

    /**
     * Retrieve attendance / leave facts for a specific student.
     *
     * @return array{facts: string, fallbackReply: string, hasData: bool}
     */
    public function getStudentLeaveFacts(Student $student, string $rawQuery): array
    {
        $user = $student->user;
        $studentName = $user?->name ?? 'Siswa';
        $nis = $student->nis;
        $class = $student->class;
        $today = Carbon::today()->toDateString();
        $todayFormatted = Carbon::today()->translatedFormat('d F Y');

        $isAskingHistory = (bool) preg_match('/\briwayat\b/i', $rawQuery);
        $isAskingApproval = (bool) preg_match('/\b(disetujui|persetujuan|status pengajuan)\b/i', $rawQuery);
        $isAskingToday = (bool) preg_match('/\bhari ini\b/i', $rawQuery);
        $isAskingLateFrequency = (bool) preg_match('/\b(sering terlambat|sering telat|apakah.*?terlambat|riwayat keterlambatan)\b/i', $rawQuery);
        $isAskingMultiDay = (bool) preg_match('/(?:selama\s+)?(\d+|tiga|dua|empat|lima|enam|tujuh)\s+hari\s+terakhir/i', $rawQuery);
        $isAskingLeaveCount = (bool) preg_match('/berapa\s+kali.*?(tidak masuk|tidak hadir|absen|sakit|izin|ijin)/i', $rawQuery);
        $isAskingSpecificIllness = (bool) preg_match('/\b(sakit apa|penyakit|gejala|diagnosis)\b/i', $rawQuery);

        $targetDate = null;
        if (preg_match('/\bkemarin\b/i', $rawQuery)) {
            $targetDate = Carbon::yesterday()->toDateString();
        } elseif ($isAskingToday) {
            $targetDate = Carbon::today()->toDateString();
        } elseif (preg_match('/\btanggal\s+(\d{1,2})\s+([a-zA-Z]+)(?:\s+(\d{4}))?/i', $rawQuery, $dm)) {
            $day = (int) $dm[1];
            $monthStr = strtolower($dm[2]);
            $year = ! empty($dm[3]) ? (int) $dm[3] : (int) date('Y');
            $months = [
                'januari' => 1, 'jan' => 1, 'februari' => 2, 'feb' => 2, 'maret' => 3, 'mar' => 3,
                'april' => 4, 'apr' => 4, 'mei' => 5, 'may' => 5, 'juni' => 6, 'jun' => 6,
                'juli' => 7, 'jul' => 7, 'agustus' => 8, 'agu' => 8, 'agt' => 8, 'september' => 9, 'sep' => 9,
                'oktober' => 10, 'okt' => 10, 'november' => 11, 'nov' => 11, 'desember' => 12, 'des' => 12,
            ];
            if (isset($months[$monthStr])) {
                $targetDate = Carbon::createFromDate($year, $months[$monthStr], $day)->toDateString();
            }
        } elseif (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $rawQuery, $dm)) {
            $targetDate = $dm[1];
        }

        // 1. Check if asking about late frequency ("Apakah siswa ini sering terlambat?")
        if ($isAskingLateFrequency) {
            $totalLate = Attendance::where('user_id', $student->user_id)->where('status', 'late')->count();
            $startMonth = Carbon::now()->startOfMonth()->toDateString();
            $monthLate = Attendance::where('user_id', $student->user_id)
                ->where('status', 'late')
                ->whereDate('attendance_date', '>=', $startMonth)
                ->count();

            $isFrequent = $totalLate >= 3;
            $evalText = $isFrequent
                ? 'Berdasarkan catatan absensi, siswa ini tergolong cukup sering terlambat.'
                : 'Siswa ini tidak tergolong sering terlambat.';

            $facts = "Data Siswa: {$studentName} (Kelas: {$class}, NIS: {$nis})\n"
                ."Total Keterlambatan: {$totalLate} kali\n"
                ."Keterlambatan Bulan Ini: {$monthLate} kali\n"
                ."Evaluasi: {$evalText}";

            $fallbackReply = "Berdasarkan catatan absensi SmartAttendance, {$studentName} (Kelas {$class}) tercatat hadir terlambat sebanyak {$totalLate} kali secara keseluruhan (termasuk {$monthLate} kali pada bulan ini). {$evalText}";

            return ['facts' => $facts, 'fallbackReply' => $fallbackReply, 'hasData' => true];
        }

        // 2. Multi-day range query ("Apa alasan Siti tidak hadir selama tiga hari terakhir?")
        if ($isAskingMultiDay) {
            preg_match('/(?:selama\s+)?(\d+|tiga|dua|empat|lima|enam|tujuh)\s+hari\s+terakhir/i', $rawQuery, $hm);
            $daysWord = strtolower($hm[1] ?? '3');
            $numDays = match ($daysWord) {
                'dua' => 2,
                'tiga' => 3,
                'empat' => 4,
                'lima' => 5,
                'enam' => 6,
                'tujuh' => 7,
                default => (int) $daysWord,
            };
            if ($numDays <= 0) {
                $numDays = 3;
            }

            $startDate = Carbon::today()->subDays($numDays - 1)->toDateString();
            $recentAttendances = Attendance::where('user_id', $student->user_id)
                ->whereBetween('attendance_date', [$startDate, $today])
                ->orderBy('attendance_date', 'desc')
                ->get();

            $absentRecords = $recentAttendances->whereIn('status', ['sick', 'excused', 'absent']);

            if ($absentRecords->isEmpty()) {
                $facts = "Data Siswa: {$studentName} (Kelas: {$class}, NIS: {$nis})\nPeriode: {$numDays} hari terakhir\nKeterangan: Tidak ada catatan ketidakhadiran dalam periode ini.";
                $fallbackReply = "Dalam {$numDays} hari terakhir, {$studentName} (Kelas {$class}) tidak memiliki catatan ketidakhadiran karena izin, sakit, maupun alpa.";

                return ['facts' => $facts, 'fallbackReply' => $fallbackReply, 'hasData' => true];
            }

            $lines = [];
            foreach ($absentRecords as $rec) {
                $st = match ($rec->status) {
                    'sick' => 'Sakit',
                    'excused' => 'Izin',
                    default => 'Tidak Hadir',
                };
                $d = $rec->attendance_date?->translatedFormat('d M Y') ?? '-';
                $r = $rec->reason ?: 'tanpa keterangan spesifik';
                $app = $rec->getApprovalStatusLabel();
                $lines[] = "• {$d} - {$st}: {$r} (Status: {$app})";
            }

            $facts = "Data Siswa: {$studentName} (Kelas: {$class}, NIS: {$nis})\nPeriode: {$numDays} hari terakhir\nCatatan Ketidakhadiran:\n".implode("\n", $lines);
            $fallbackReply = "Catatan ketidakhadiran {$studentName} (Kelas {$class}) selama {$numDays} hari terakhir:\n".implode("\n", $lines);

            return ['facts' => $facts, 'fallbackReply' => $fallbackReply, 'hasData' => true];
        }

        // 3. Count / frequency check ("Berapa kali siswa tidak masuk karena izin atau sakit?")
        if ($isAskingLeaveCount) {
            $totalSick = Attendance::where('user_id', $student->user_id)->where('status', 'sick')->count();
            $totalExcused = Attendance::where('user_id', $student->user_id)->where('status', 'excused')->count();
            $totalAbsent = $totalSick + $totalExcused;

            $monthName = Carbon::now()->translatedFormat('F Y');
            $startMonth = Carbon::now()->startOfMonth()->toDateString();
            $monthSick = Attendance::where('user_id', $student->user_id)
                ->where('status', 'sick')
                ->whereDate('attendance_date', '>=', $startMonth)
                ->count();
            $monthExcused = Attendance::where('user_id', $student->user_id)
                ->where('status', 'excused')
                ->whereDate('attendance_date', '>=', $startMonth)
                ->count();
            $monthAbsent = $monthSick + $monthExcused;

            $facts = "Data Siswa: {$studentName} (Kelas: {$class}, NIS: {$nis})\n"
                ."Total Tidak Masuk: {$totalAbsent} kali (Sakit: {$totalSick} kali, Izin: {$totalExcused} kali)\n"
                ."Bulan Ini ({$monthName}): {$monthAbsent} kali (Sakit: {$monthSick} kali, Izin: {$monthExcused} kali)";

            $fallbackReply = "Berdasarkan data SmartAttendance, {$studentName} (Kelas {$class}) tercatat tidak masuk sebanyak {$totalAbsent} kali (Sakit: {$totalSick} kali, Izin: {$totalExcused} kali), termasuk {$monthAbsent} kali pada bulan {$monthName}.";

            return ['facts' => $facts, 'fallbackReply' => $fallbackReply, 'hasData' => true];
        }

        // 4. History Query
        if ($isAskingHistory) {
            $history = Attendance::where('user_id', $student->user_id)
                ->whereIn('status', ['excused', 'sick'])
                ->orderBy('attendance_date', 'desc')
                ->take(6)
                ->get();

            if ($history->isEmpty()) {
                $facts = "Data Siswa: {$studentName} (Kelas: {$class}, NIS: {$nis})\nRiwayat Izin/Sakit: Tidak ada riwayat izin atau sakit yang tercatat.";
                $fallbackReply = "Tidak ditemukan riwayat pengajuan izin maupun sakit untuk {$studentName} (Kelas {$class}, NIS: {$nis}).";

                return ['facts' => $facts, 'fallbackReply' => $fallbackReply, 'hasData' => false];
            }

            $list = [];
            foreach ($history as $idx => $item) {
                $t = $item->status === 'sick' ? 'Sakit' : 'Izin';
                $dateStr = $item->attendance_date?->translatedFormat('d M Y');
                $reasonStr = $item->reason ?: 'Tanpa alasan';
                $statusStr = $item->getApprovalStatusLabel();
                $num = $idx + 1;
                $list[] = "{$num}. {$dateStr} - {$t}: \"{$reasonStr}\" (Status: {$statusStr})";
            }

            $facts = "Data Siswa: {$studentName} (Kelas: {$class}, NIS: {$nis})\nRiwayat Pengajuan:\n".implode("\n", $list);
            $fallbackReply = "Riwayat pengajuan izin/sakit untuk {$studentName} (Kelas {$class}):\n".implode("\n", $list);

            return ['facts' => $facts, 'fallbackReply' => $fallbackReply, 'hasData' => true];
        }

        // 5. Retrieve target attendance record (either on explicit target date or latest leave/sick)
        $record = null;
        if ($targetDate !== null) {
            $record = Attendance::where('user_id', $student->user_id)
                ->whereDate('attendance_date', $targetDate)
                ->first();

            if (! $record) {
                $targetFormatted = Carbon::parse($targetDate)->translatedFormat('d F Y');
                $facts = "Data Siswa: {$studentName} (Kelas: {$class}, NIS: {$nis})\nTanggal: {$targetFormatted}\nCatatan: Tidak ada catatan absensi atau izin pada tanggal tersebut.";
                $fallbackReply = "Tidak ada catatan absensi atau pengajuan izin maupun sakit untuk {$studentName} (Kelas {$class}, NIS: {$nis}) pada tanggal {$targetFormatted}. Saya belum menemukan catatan izin yang sesuai untuk siswa tersebut. Silakan periksa nama atau tanggal yang dimaksud.";

                return ['facts' => $facts, 'fallbackReply' => $fallbackReply, 'hasData' => false];
            }
        } else {
            $record = Attendance::where('user_id', $student->user_id)
                ->whereIn('status', ['excused', 'sick'])
                ->orderBy('attendance_date', 'desc')
                ->first();

            if (! $record) {
                $facts = "Data Siswa: {$studentName} (Kelas: {$class}, NIS: {$nis})\nCatatan: Tidak ada pengajuan izin atau sakit yang tercatat di database.";
                $fallbackReply = "Tidak ada catatan absensi atau pengajuan izin maupun sakit untuk {$studentName} (Kelas {$class}, NIS: {$nis}) di dalam sistem. Saya belum menemukan catatan izin yang sesuai untuk siswa tersebut. Silakan periksa nama atau tanggal yang dimaksud.";

                return ['facts' => $facts, 'fallbackReply' => $fallbackReply, 'hasData' => false];
            }
        }

        // 6. Format facts and response for the found attendance record
        $typeLabel = match ($record->status) {
            'sick' => 'Sakit',
            'excused' => 'Izin',
            'present' => 'Hadir Tepat Waktu',
            'late' => 'Hadir Terlambat',
            default => $record->status,
        };

        $leaveDate = $record->attendance_date?->translatedFormat('d F Y') ?? $todayFormatted;
        $reason = $record->reason ?: 'Keterangan alasan tidak disertakan';
        $approvalLabel = $record->getApprovalStatusLabel();
        $approvalStatus = $record->approval_status;
        $approverName = $record->approver?->name;

        $approvalSentence = match ($approvalStatus) {
            'pending' => 'Status pengajuannya masih menunggu persetujuan guru (pending).',
            'approved' => 'Pengajuan tersebut telah disetujui oleh '.($approverName ? "guru {$approverName}" : 'guru').'.',
            'rejected' => 'Pengajuan tersebut telah ditolak guru'.($record->rejection_note ? " dengan catatan: \"{$record->rejection_note}\"" : '.'),
            default => "Status persetujuan: {$approvalLabel}.",
        };

        $facts = "Data Siswa: {$studentName} (Kelas: {$class}, NIS: {$nis})\n"
            ."Tanggal: {$leaveDate}\n"
            ."Status Kehadiran: {$typeLabel}\n"
            ."Alasan Tercatat: {$reason}\n"
            ."Status Persetujuan: {$approvalLabel} ({$approvalStatus})\n"
            .'Disetujui/Ditinjau Oleh: '.($approverName ?: 'Belum ditinjau');

        // Excused (Izin) formatting
        if ($record->status === 'excused') {
            $hasReason = ! empty($record->reason) && ! in_array(strtolower(trim($record->reason)), ['izin', 'ijin', '-', 'tidak ada', 'belum ada', 'tanpa keterangan'], true);
            $isAskingApprovalStatus = $isAskingApproval || (bool) preg_match('/\b(setuju|disetujui|status pengajuan|status persetujuan|persetujuan|acc|pending|menunggu)\b/i', $rawQuery);
            $approvalSuffix = $isAskingApprovalStatus ? " {$approvalSentence}" : '';
            if ($hasReason) {
                $fallbackReply = "{$studentName} tercatat izin tidak masuk pada {$leaveDate}. Keterangan yang tersimpan adalah {$record->reason}.{$approvalSuffix}";
            } else {
                $fallbackReply = "{$studentName} tercatat izin tidak masuk pada {$leaveDate}, tetapi alasan izin belum dicantumkan dalam data.{$approvalSuffix}";
            }

            return ['facts' => $facts, 'fallbackReply' => $fallbackReply, 'hasData' => true];
        }

        // Sick (Sakit) formatting
        if ($record->status === 'sick') {
            $hasDetailedReason = ! empty($record->reason) && ! in_array(strtolower(trim($record->reason)), ['sakit', '-', 'tidak ada', 'belum ada', 'tanpa keterangan'], true);
            if ($isAskingSpecificIllness) {
                if (! $hasDetailedReason) {
                    $fallbackReply = "Berdasarkan data sistem SmartAttendance, {$studentName} (Kelas {$class}) hanya tercatat berstatus \"Sakit\" pada tanggal {$leaveDate}, tanpa keterangan diagnosis atau penyebab spesifik yang tercatat dalam sistem. Sistem tidak membuat dugaan diagnosis medis.";
                } else {
                    $fallbackReply = "Berdasarkan catatan absensi pada tanggal {$leaveDate}, {$studentName} (Kelas {$class}) tercatat Sakit dengan alasan yang diajukan: \"{$record->reason}\". {$approvalSentence} Catatan ini merupakan keterangan absensi dan bukan bukti diagnosis medis resmi.";
                }
            } else {
                $fallbackReply = "Berdasarkan pengajuan Sakit yang tercatat pada tanggal {$leaveDate}, {$studentName} mengajukan Sakit karena {$reason}. {$approvalSentence}";
            }

            return ['facts' => $facts, 'fallbackReply' => $fallbackReply, 'hasData' => true];
        }

        // Present / Late formatting
        if (in_array($record->status, ['present', 'late'], true)) {
            $fallbackReply = "Berdasarkan data absensi pada {$leaveDate}, {$studentName} telah hadir di sekolah dengan status {$typeLabel} pada pukul {$record->attendance_time} WIB.";

            return ['facts' => $facts, 'fallbackReply' => $fallbackReply, 'hasData' => true];
        }

        $fallbackReply = "Berdasarkan catatan absensi pada tanggal {$leaveDate}, status {$studentName} tercatat: {$typeLabel}.";

        return ['facts' => $facts, 'fallbackReply' => $fallbackReply, 'hasData' => true];
    }

    /**
     * Build clarification reply for ambiguous students.
     *
     * @param  Collection<int, Student>  $candidates
     */
    public function buildClarificationReply(string $cleanName, Collection $candidates): string
    {
        $list = [];
        $i = 1;
        foreach ($candidates as $cand) {
            $cName = $cand->user?->name ?? 'Siswa';
            $cClass = $cand->class;
            $cNis = $cand->nis;
            $list[] = "{$i}. {$cName} (Kelas {$cClass}, NIS: {$cNis})";
            $i++;
        }

        return "Terdapat lebih dari satu siswa dengan nama '{$cleanName}':\n"
            .implode("\n", $list)."\n"
            .'Silakan sebutkan kelas atau NIS untuk memperjelas pertanyaan Anda.';
    }

    /**
     * Extract student name from common Indonesian question structures.
     */
    public function extractStudentName(string $text): ?string
    {
        $patterns = [
            // "Arya Kusuma Firdaus izin tidak masuk sekolah kenapa" / "Arya Kusuma Firdaus tidak masuk sekolah kenapa"
            '/^([a-zA-Z0-9\s\.\'\-]+?)\s+(?:izin|ijin|sakit|tidak masuk|tidak hadir|absen)(?:\s+tidak masuk|\s+tidak hadir)?(?:\s+sekolah)?\s*(?:kenapa|mengapa|karena apa|alasannya apa|apa alasannya)\b/i',

            // "Arya Kusuma Firdaus izin tidak masuk sekolah" / "Arya Kusuma Firdaus tidak masuk sekolah"
            '/^([a-zA-Z0-9\s\.\'\-]+?)\s+(?:izin|ijin|sakit|tidak masuk|tidak hadir|gak masuk|ga masuk)\s+sekolah\b/i',

            // "Arya Kusuma Firdaus kenapa tidak masuk / kenapa izin / kenapa sakit"
            '/^([a-zA-Z0-9\s\.\'\-]+?)\s+(?:kenapa|mengapa)\s+(?:tidak masuk|tidak hadir|izin|ijin|sakit|absen)/i',

            // "Arya Kusuma Firdaus izin kenapa" / "Arya Kusuma Firdaus sakit kenapa"
            '/^([a-zA-Z0-9\s\.\'\-]+?)\s+(?:izin|ijin|sakit)\s+(?:kenapa|mengapa|karena apa)/i',

            // "Arya Kusuma Firdaus izin tidak masuk" / "Arya Kusuma Firdaus sakit tidak masuk"
            '/^([a-zA-Z0-9\s\.\'\-]+?)\s+(?:izin|ijin|sakit)\s+(?:tidak masuk|tidak hadir)/i',

            // "Arya Kusuma Firdaus izin apa?" / "Arya Kusuma Firdaus sakit apa?"
            '/^([a-zA-Z0-9\s\.\'\-]+?)\s+(?:izin|ijin|sakit)\s+apa/i',

            // "Kenapa Arya tidak masuk hari ini?" / "Mengapa Siti tidak hadir"
            '/(?:kenapa|mengapa)\s+(?:siswa\s+)?([a-zA-Z0-9\s\.\'\-]+?)\s+(?:tidak masuk|tidak hadir|absen|sakit|izin|ijin|gak masuk|ga masuk)/i',

            // "Apa alasan Siti tidak hadir selama tiga hari terakhir?"
            '/(?:apa\s+)?alasan\s+([a-zA-Z0-9\s\.\'\-]+?)\s+(?:tidak hadir|tidak masuk|absen|sakit|izin|ijin)/i',

            // "Keterangan sakit Arya Kusuma Firdaus" / "Alasan izin Arya Kusuma Firdaus"
            '/(?:keterangan|alasan|penyebab|bukti)\s+(?:izin|ijin|sakit|tidak masuk|tidak hadir)\s+(?:siswa\s+)?([a-zA-Z0-9\s\.\'\-]+)/i',

            // "Arya sakit karena apa?"
            '/([a-zA-Z0-9\s\.\'\-]+?)\s+sakit\s+karena\s+apa/i',

            // "Arya sakit apa?" / "Andi sakit apa?"
            '/([a-zA-Z0-9\s\.\'\-]+?)\s+sakit\s+apa/i',

            // "Apakah Arya sering terlambat?"
            '/(?:apakah\s+)?([a-zA-Z0-9\s\.\'\-]+?)\s+(?:sering terlambat|sering telat)/i',

            // "Berapa kali Arya sakit bulan ini?"
            '/berapa\s+kali\s+([a-zA-Z0-9\s\.\'\-]+?)\s+(?:sakit|izin|ijin|terlambat|telat)/i',

            // "Apakah pengajuan sakit Arya sudah disetujui guru?"
            '/(?:apakah\s+)?(?:pengajuan\s+)?(?:izin|sakit|ijin)\s+([a-zA-Z0-9\s\.\'\-]+?)\s+(?:sudah|telah|belum|status|disetujui)/i',

            // "Tampilkan riwayat izin Arya"
            '/(?:tampilkan\s+)?riwayat\s+(?:izin|sakit|ijin)\s+([a-zA-Z0-9\s\.\'\-]+)/i',

            // "Apa alasan izin Arya?"
            '/(?:apa\s+)?alasan\s+(?:izin|sakit|ijin)\s+(?:siswa\s+)?([a-zA-Z0-9\s\.\'\-]+)/i',

            // "Status izin Arya"
            '/status\s+(?:izin|sakit|ijin|pengajuan)\s+([a-zA-Z0-9\s\.\'\-]+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $candidate = trim($matches[1]);
                $candidate = $this->cleanExtractedName($candidate);
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        // Fallback: extract candidate name by stripping question and attendance stop-words
        $fallback = $this->extractCandidateNameByFiltering($text);
        if ($fallback !== null && $fallback !== '') {
            return $fallback;
        }

        return null;
    }

    /**
     * Fallback extractor by removing question words and attendance stop-words.
     */
    public function extractCandidateNameByFiltering(string $text): ?string
    {
        $class = $this->extractClass($text);
        $cleaned = $text;
        if ($class !== null) {
            $cleaned = preg_replace('/\b(?:kelas|kls)\s+[a-zA-Z0-9\-\/\s]+/i', ' ', $cleaned);
        }

        $stopWords = [
            'kenapa', 'mengapa', 'bagaimana', 'apakah', 'siapakah', 'siapa', 'berapa', 'kali', 'apa',
            'alasan', 'alasannya', 'keterangan', 'penyebab', 'catatan', 'status', 'pengajuan', 'permohonan',
            'riwayat', 'laporan', 'rekap', 'rekapitulasi',
            'izin', 'ijin', 'sakit', 'tidak', 'masuk', 'hadir', 'absen', 'absensi', 'presensi', 'kehadiran',
            'gak', 'ga', 'tak', 'bukan', 'terlambat', 'telat', 'keterlambatan',
            'sekolah', 'kelas', 'kls', 'murid', 'siswa',
            'hari ini', 'kemarin', 'lusa', 'besok', 'minggu ini', 'bulan ini', 'pekan ini', 'terakhir', 'tadi',
            'karena', 'sebab', 'sudah', 'telah', 'belum', 'ada', 'tampilkan', 'lihat', 'cek', 'tolong', 'bisa',
            'kah', 'ya', 'kok', 'sih', 'dong', 'deh', 'pun',
            'tentang', 'dari', 'pada', 'di', 'ke', 'yang', 'dan', 'dengan', 'untuk', 'selama', 'oleh',
            'jam', 'pukul', 'waktu', 'batas', 'toleransi', 'aturan', 'cara', 'alur', 'panduan', 'bantuan', 'menu', 'portal', 'dashboard', 'fitur',
        ];

        foreach ($stopWords as $word) {
            $cleaned = preg_replace('/\b'.preg_quote($word, '/').'\b/i', ' ', $cleaned);
        }

        $cleaned = preg_replace('/[^\p{L}\p{N}\s\.\'\-]/u', ' ', $cleaned);
        $candidate = trim(preg_replace('/\s+/', ' ', $cleaned));

        if (mb_strlen($candidate) >= 2) {
            return $candidate;
        }

        return null;
    }

    /**
     * Extract class name (e.g. "XII-RPL 1", "X-A") from text.
     */
    protected function extractClass(string $text): ?string
    {
        if (preg_match('/\b(?:kelas|kls)\s+([a-zA-Z0-9\-\/]+(?:\s+[a-zA-Z0-9\-\/]+)?)/i', $text, $m)) {
            $class = trim($m[1]);
            // Remove trailing common question words if accidentally included
            $cleaned = preg_replace('/\s+(hari|kemarin|yang|saat|siapa|sakit|izin|ijin|karena|tidak|absen|kenapa).*$/i', '', $class);

            return trim($cleaned);
        }

        return null;
    }

    /**
     * Check if query is referring to the current authenticated user themselves.
     */
    protected function isSelfReferential(string $text): bool
    {
        return (bool) preg_match('/\b(saya|aku|diriku|pengajuan saya|izin saya|sakit saya|riwayat saya)\b/i', $text);
    }

    /**
     * Clean extracted name of trailing question particles.
     */
    protected function cleanExtractedName(string $name): string
    {
        // Remove explicit class references if accidentally captured
        $cleaned = preg_replace('/\b(?:kelas|kls)\s+[a-zA-Z0-9\-\/\s]+/i', '', $name);

        $stopWords = ['apakah', 'hari ini', 'kemarin', 'tadi', 'ini', 'itu', 'sudah', 'belum', 'kelas', 'kls', 'siswa', 'murid', 'sekolah'];

        foreach ($stopWords as $word) {
            $cleaned = preg_replace('/\b'.preg_quote($word, '/').'\b/i', '', $cleaned);
        }

        return trim(preg_replace('/\s+/', ' ', $cleaned));
    }
}
