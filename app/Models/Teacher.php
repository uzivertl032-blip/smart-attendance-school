<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Teacher extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'nip',
        'face_data',
    ];

    /**
     * The attributes that should be hidden for serialization to protect biometric data privacy.
     *
     * @var list<string>
     */
    protected $hidden = [
        'face_data',
    ];

    /**
     * Get the user that owns the teacher profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if teacher has registered face biometrics.
     */
    public function hasFaceRegistered(): bool
    {
        return ! empty($this->face_data);
    }

    /**
     * Retrieve the model for a bound value.
     * Supports binding by Teacher ID or associated User ID (Account ID).
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        if ($field) {
            return parent::resolveRouteBinding($value, $field);
        }

        return $this->where('id', $value)->first()
            ?? $this->where('user_id', $value)->first();
    }
}
