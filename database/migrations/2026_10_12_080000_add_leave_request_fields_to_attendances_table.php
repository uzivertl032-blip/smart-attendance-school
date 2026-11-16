<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds leave request (izin / sakit) fields and teacher approval tracking
     * to attendances table without altering existing attendance records.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->text('reason')->nullable()->after('status');
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('approved')->after('reason');
            $table->foreignId('approved_by')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('rejection_note')->nullable()->after('approved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['reason', 'approval_status', 'approved_by', 'approved_at', 'rejection_note']);
        });
    }
};
