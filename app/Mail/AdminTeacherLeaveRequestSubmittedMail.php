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

class AdminTeacherLeaveRequestSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Attendance $attendance,
        public User $admin
    ) {
        $this->attendance->loadMissing(['user.teacher']);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $typeLabel = $this->attendance->status === 'sick' ? 'Sakit' : 'Izin';
        $teacherName = $this->attendance->user->name ?? 'Guru';
        $nip = $this->attendance->user->teacher?->nip ? " (NIP: {$this->attendance->user->teacher->nip})" : '';

        return new Envelope(
            subject: "[SmartAttendance] Pengajuan {$typeLabel} Baru dari Guru {$teacherName}{$nip}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $dateFormatted = Carbon::parse($this->attendance->attendance_date)->translatedFormat('l, d F Y');

        $submittedAt = $this->attendance->created_at
            ?? ($this->attendance->attendance_time ? Carbon::parse($this->attendance->attendance_date->format('Y-m-d').' '.$this->attendance->attendance_time) : null)
            ?? Carbon::now();
        $submittedAtFormatted = Carbon::parse($submittedAt)->translatedFormat('l, d F Y H:i').' WIB';

        return new Content(
            view: 'emails.admin-teacher-leave-request-submitted',
            with: [
                'attendance' => $this->attendance,
                'admin' => $this->admin,
                'teacher' => $this->attendance->user,
                'teacherDetails' => $this->attendance->user->teacher,
                'dateFormatted' => $dateFormatted,
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

