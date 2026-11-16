<?php

namespace App\Mail;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminTeacherLeaveDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Attendance $attendance,
        public User $admin
    ) {
        $this->attendance->loadMissing(['user.teacher', 'approver']);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $typeLabel = $this->attendance->status === 'sick' ? 'Sakit' : 'Izin';
        $decision = $this->attendance->approval_status === 'approved' ? 'Disetujui' : 'Ditolak';
        $teacherName = $this->attendance->user->name ?? 'Guru';

        return new Envelope(
            subject: "[SmartAttendance] Pengajuan {$typeLabel} Guru {$teacherName} {$decision}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $dateFormatted = Carbon::parse($this->attendance->attendance_date)->translatedFormat('l, d F Y');
        $decidedAtFormatted = $this->attendance->approved_at
            ? Carbon::parse($this->attendance->approved_at)->translatedFormat('l, d F Y H:i').' WIB'
            : null;

        return new Content(
            view: 'emails.admin-teacher-leave-decision',
            with: [
                'attendance' => $this->attendance,
                'admin' => $this->admin,
                'teacher' => $this->attendance->user,
                'teacherDetails' => $this->attendance->user->teacher,
                'approver' => $this->attendance->approver,
                'dateFormatted' => $dateFormatted,
                'decidedAtFormatted' => $decidedAtFormatted,
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
