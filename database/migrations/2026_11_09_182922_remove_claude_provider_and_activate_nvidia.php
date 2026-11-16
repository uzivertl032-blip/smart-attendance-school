<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retire the Anthropic Claude provider.
     *
     * If Claude was the active provider, NVIDIA NIM takes over when it already
     * has a usable model. Otherwise no provider is left active so the chatbot
     * reports a clear configuration error instead of using a removed provider.
     * Gemini, OpenAI, and NVIDIA credentials are never deleted.
     */
    public function up(): void
    {
        if (! Schema::hasTable('ai_settings')) {
            return;
        }

        $now = now();

        $nvidia = DB::table('ai_settings')->where('provider', 'nvidia')->first();

        if ($nvidia === null) {
            DB::table('ai_settings')->insert([
                'provider' => 'nvidia',
                'is_active' => false,
                'model' => null,
                'api_key' => null,
                'base_url' => 'https://integrate.api.nvidia.com/v1',
                'temperature' => 0.70,
                'max_tokens' => 1024,
                'last_tested_at' => null,
                'last_test_status' => null,
                'last_test_message' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $nvidia = DB::table('ai_settings')->where('provider', 'nvidia')->first();
        }

        $claudeWasActive = DB::table('ai_settings')
            ->where('provider', 'claude')
            ->where('is_active', true)
            ->exists();

        if ($claudeWasActive) {
            $nvidiaHasModel = $nvidia !== null
                && in_array(trim((string) $nvidia->model), [
                    'nvidia/nemotron-3.5-bolt-e4b',
                    'nvidia/nemotron-3.5-lightning-30b-a3b',
                    'minimaxai/minimax-m3.1-flash',
                ], true);

            DB::table('ai_settings')
                ->where('is_active', true)
                ->update(['is_active' => false, 'updated_at' => $now]);

            if ($nvidiaHasModel) {
                DB::table('ai_settings')
                    ->where('provider', 'nvidia')
                    ->update(['is_active' => true, 'updated_at' => $now]);
            }
        }

        DB::table('ai_settings')->where('provider', 'claude')->delete();
    }

    /**
     * Reverse the migration by restoring an inactive Claude row.
     */
    public function down(): void
    {
        if (! Schema::hasTable('ai_settings')) {
            return;
        }

        if (DB::table('ai_settings')->where('provider', 'claude')->exists()) {
            return;
        }

        $now = now();

        DB::table('ai_settings')->insert([
            'provider' => 'claude',
            'is_active' => false,
            'model' => null,
            'api_key' => null,
            'base_url' => 'https://api.anthropic.com/v1',
            'temperature' => 0.70,
            'max_tokens' => 1024,
            'last_tested_at' => null,
            'last_test_status' => null,
            'last_test_message' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
