<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add database-level unique constraint on (user_id, attendance_date).
     *
     * This prevents duplicate attendance records even if the application-level
     * race condition check in AttendanceService is bypassed (e.g., concurrent
     * simultaneous requests). Additive migration — no data is deleted.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->unique(['user_id', 'attendance_date'], 'attendances_user_date_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropUnique('attendances_user_date_unique');
        });
    }
};
