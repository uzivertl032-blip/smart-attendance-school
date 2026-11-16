<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'attendance_date',
        'attendance_time',
        'status',
        'verification_status',
        'reason',
        'approval_status',
        'approved_by',
        'approved_at',
        'rejection_note',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function (Attendance $attendance) {
            if (empty($attendance->attendance_time)) {
                $attendance->attendance_time = now()->toTimeString();
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the attendance record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the teacher who approved or rejected the request.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if request is pending teacher approval.
     */
    public function isPending(): bool
    {
        return $this->approval_status === 'pending';
    }

    /**
     * Check if request has been approved.
     */
    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    /**
     * Check if request has been rejected.
     */
    public function isRejected(): bool
    {
        return $this->approval_status === 'rejected';
    }

    /**
     * Get human-readable Indonesian label for approval status.
     */
    public function getApprovalStatusLabel(): string
    {
        return match ($this->approval_status) {
            'pending' => 'Menunggu Persetujuan',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            default => ucfirst((string) $this->approval_status),
        };
    }
}
