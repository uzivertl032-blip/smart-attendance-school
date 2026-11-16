<?php

namespace App\Mail;

use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StudentAttendanceRecordedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Attendance $attendance
    ) {
        $this->attendance->loadMissing(['user.student']);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $statusLabel = match ($this->attendance->status) {
            'present' => 'Hadir Tepat Waktu',
            'late' => 'Hadir Terlambat',
            default => 'Kehadiran Dicatat',
        };

        $studentName = $this->attendance->user->name ?? 'Siswa';

        return new Envelope(
            subject: "[SmartAttendance] Konfirmasi Presensi: {$studentName} ({$statusLabel})",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $dateFormatted = Carbon::parse($this->attendance->attendance_date)->translatedFormat('l, d F Y');

        return new Content(
            view: 'emails.student-attendance-recorded',
            with: [
                'attendance' => $this->attendance,
                'student' => $this->attendance->user,
                'studentDetails' => $this->attendance->user->student,
                'dateFormatted' => $dateFormatted,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
