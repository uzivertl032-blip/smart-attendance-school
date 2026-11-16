<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Face Recognition & Attendance Configurations
    |--------------------------------------------------------------------------
    |
    | Here you can configure default threshold values, metrics, and security
    | settings used by the Face Recognition Engine and Verification Service.
    |
    */

    'face_match_threshold' => (float) env('FACE_MATCH_THRESHOLD', 0.55),

    'min_confidence_percent' => (float) env('FACE_MIN_CONFIDENCE', 60.0),

    'max_faces_allowed' => 1,

    /*
    |--------------------------------------------------------------------------
    | School Hours Configuration
    |--------------------------------------------------------------------------
    |
    | Jam masuk sekolah dan batas waktu keterlambatan.
    | - opening_time: Jam mulai buka gerbang absensi (default 06:30)
    | - late_after: Batas jam masuk sebelum dihitung Terlambat (default 07:15)
    |
    */
    'opening_time' => env('ATTENDANCE_OPENING_TIME', '06:30'),

    'late_after' => env('ATTENDANCE_LATE_AFTER', '07:15'),

    'reminder_after' => env('ATTENDANCE_REMINDER_AFTER', '07:15'),

    /*
    |--------------------------------------------------------------------------
    | Administrator Notification Recipients
    |--------------------------------------------------------------------------
    |
    | Comma-separated email addresses that receive teacher leave request
    | notifications. When filled, this list is used instead of the email
    | addresses stored on the administrator accounts. Leave empty to keep
    | using every active administrator account from the database.
    |
    */
    'admin_notification_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ADMIN_NOTIFICATION_EMAILS', ''))
    ))),
];
