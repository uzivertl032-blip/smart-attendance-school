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

class TeacherLeaveRequestStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Attendance $attendance
    ) {
        $this->attendance->loadMissing(['user.teacher', 'approver']);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $typeLabel = $this->attendance->status === 'sick' ? 'Sakit' : 'Izin';
        $statusDecision = $this->attendance->approval_status === 'approved' ? 'Disetujui' : 'Ditolak';

        return new Envelope(
            subject: "[SmartAttendance] Pengajuan {$typeLabel} Guru Telah {$statusDecision}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $dateFormatted = Carbon::parse($this->attendance->attendance_date)->translatedFormat('l, d F Y');
        $approvedAtFormatted = $this->attendance->approved_at
            ? Carbon::parse($this->attendance->approved_at)->translatedFormat('l, d F Y H:i').' WIB'
            : null;

        $submittedAt = $this->attendance->created_at
            ?? ($this->attendance->attendance_time ? Carbon::parse($this->attendance->attendance_date->format('Y-m-d').' '.$this->attendance->attendance_time) : null)
            ?? Carbon::now();
        $submittedAtFormatted = Carbon::parse($submittedAt)->translatedFormat('l, d F Y H:i').' WIB';

        return new Content(
            view: 'emails.teacher-leave-request-status',
            with: [
                'attendance' => $this->attendance,
                'teacher' => $this->attendance->user,
                'teacherDetails' => $this->attendance->user->teacher,
                'approver' => $this->attendance->approver,
                'dateFormatted' => $dateFormatted,
                'approvedAtFormatted' => $approvedAtFormatted,
                'submittedAtFormatted' => $submittedAtFormatted,
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

