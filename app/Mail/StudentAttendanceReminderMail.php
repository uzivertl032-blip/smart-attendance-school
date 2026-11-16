<?php

namespace App\Mail;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StudentAttendanceReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $student,
        public string $targetDate
    ) {
        $this->student->loadMissing('student');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[SmartAttendance] Pengingat: Anda Belum Melakukan Presensi Hari Ini',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $dateFormatted = Carbon::parse($this->targetDate)->translatedFormat('l, d F Y');
        $lateAfter = config('attendance.late_after', '07:15');

        return new Content(
            view: 'emails.student-attendance-reminder',
            with: [
                'student' => $this->student,
                'studentDetails' => $this->student->student,
                'dateFormatted' => $dateFormatted,
                'lateAfter' => $lateAfter,
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
