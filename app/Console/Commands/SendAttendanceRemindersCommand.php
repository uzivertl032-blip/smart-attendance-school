<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\User;
use App\Services\AttendanceNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendAttendanceRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:send-reminders
                            {--date= : Tanggal presensi dalam format YYYY-MM-DD (default hari ini)}
                            {--force : Abaikan validasi jam batas absensi untuk pengujian}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kirim email pengingat otomatis kepada siswa yang belum melakukan presensi hari ini setelah batas waktu sekolah';

    /**
     * Execute the console command.
     */
    public function handle(AttendanceNotificationService $notificationService): int
    {
        $now = Carbon::now();
        $targetDate = $this->option('date') ?: $now->toDateString();
        $isToday = ($targetDate === $now->toDateString());

        $lateAfter = config('attendance.late_after', '07:15');
        $force = (bool) $this->option('force');

        // Condition 1: Jangan mengirim pengingat sebelum batas waktu absensi (jika memeriksa hari ini)
        if ($isToday && ! $force) {
            $currentTime = $now->format('H:i');
            if ($currentTime < $lateAfter) {
                $this->warn("Waktu saat ini ({$currentTime} WIB) belum mencapai batas waktu keterlambatan ({$lateAfter} WIB).");
                $this->info('Pengingat absensi hanya dikirimkan setelah batas waktu terlewati. Gunakan opsi --force jika ingin mengabaikan batasan jam saat pengujian.');

                return self::SUCCESS;
            }
        }

        $this->info("Memeriksa siswa yang belum presensi untuk tanggal: {$targetDate}");

        // Condition 2: Dapatkan ID siswa yang SUDAH memiliki catatan absensi hari ini (present, late, excused, sick)
        $attendedUserIds = Attendance::whereDate('attendance_date', $targetDate)
            ->pluck('user_id')
            ->all();

        // Dapatkan siswa aktif yang BELUM absen
        $unattendedStudents = User::where('role', 'student')
            ->where('status', 'active')
            ->whereNotIn('id', $attendedUserIds)
            ->with('student')
            ->get();

        if ($unattendedStudents->isEmpty()) {
            $this->info('Semua siswa aktif sudah melakukan absensi atau tidak ada siswa yang perlu diingatkan.');

            return self::SUCCESS;
        }

        $this->line("Ditemukan {$unattendedStudents->count()} siswa yang belum melakukan absensi.");

        $sentCount = 0;
        $skippedCount = 0;

        foreach ($unattendedStudents as $student) {
            $sent = $notificationService->sendAttendanceReminderNotification($student, $targetDate);
            if ($sent) {
                $sentCount++;
                $this->line("  ✓ Pengingat dikirim ke: {$student->name} ({$student->email})");
            } else {
                $skippedCount++;
                $this->line("  - Dilewati / sudah pernah dikirim: {$student->name} ({$student->email})");
            }
        }

        $this->info("Selesai. Berhasil dikirim: {$sentCount}, Dilewati/sudah dikirim sebelumnya: {$skippedCount}.");

        return self::SUCCESS;
    }
}
