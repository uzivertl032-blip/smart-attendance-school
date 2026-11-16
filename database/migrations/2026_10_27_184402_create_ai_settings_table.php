<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique();
            $table->boolean('is_active')->default(false)->index();
            $table->string('model');
            $table->text('api_key')->nullable();
            $table->string('base_url')->nullable();
            $table->decimal('temperature', 3, 2)->default(0.70);
            $table->unsignedInteger('max_tokens')->default(1024);
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status')->nullable();
            $table->text('last_test_message')->nullable();
            $table->timestamps();
        });

        // Insert initial baseline configurations for the 3 supported providers
        \Illuminate\Support\Facades\DB::table('ai_settings')->insert([
            [
                'provider' => 'gemini',
                'is_active' => true,
                'model' => 'gemini-2.0-flash',
                'api_key' => null,
                'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
                'temperature' => 0.70,
                'max_tokens' => 1024,
                'last_tested_at' => null,
                'last_test_status' => null,
                'last_test_message' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'provider' => 'openai',
                'is_active' => false,
                'model' => 'gpt-4o-mini',
                'api_key' => null,
                'base_url' => 'https://api.openai.com/v1',
                'temperature' => 0.70,
                'max_tokens' => 1024,
                'last_tested_at' => null,
                'last_test_status' => null,
                'last_test_message' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'provider' => 'claude',
                'is_active' => false,
                'model' => 'claude-3-5-haiku-20241022',
                'api_key' => null,
                'base_url' => 'https://api.anthropic.com/v1',
                'temperature' => 0.70,
                'max_tokens' => 1024,
                'last_tested_at' => null,
                'last_test_status' => null,
                'last_test_message' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
    }
};
