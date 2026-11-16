<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
            $table->unique(['email', 'role'], 'users_email_role_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $hasDuplicateEmails = DB::table('users')
            ->select('email')
            ->groupBy('email')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicateEmails) {
            throw new RuntimeException('Cannot restore unique user emails while multiple roles share an email address.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_role_unique');
            $table->unique('email', 'users_email_unique');
        });
    }
};
