<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Move stored AI models that the current API keys cannot use to verified defaults.
     *
     * This keeps an active provider usable without touching API keys, endpoints,
     * or any non-AI data.
     */
    public function up(): void
    {
        if (! Schema::hasTable('ai_settings')) {
            return;
        }

        $removedModels = [
            'gemini' => [
                'gemini-4-argon',
                'gemini-3.8-flash',
                'gemini-3.1-pro-preview',
            ],
            'openai' => [
                'gpt-6-astra',
                'gpt-6.1-sol',
                'gpt-6.1-terra',
            ],
        ];

        $defaults = [
            'gemini' => 'gemini-3.6-flash',
            'openai' => 'gpt-6-luna',
        ];

        $now = now();

        foreach ($removedModels as $provider => $models) {
            $affected = DB::table('ai_settings')
                ->where('provider', $provider)
                ->whereIn('model', $models)
                ->update([
                    'model' => $defaults[$provider],
                    'last_tested_at' => null,
                    'last_test_status' => null,
                    'last_test_message' => null,
                    'updated_at' => $now,
                ]);

            if ($affected > 0) {
                logger()->info('Model AI lama dipindahkan ke model yang terverifikasi.', [
                    'provider' => $provider,
                    'rows' => $affected,
                    'model' => $defaults[$provider],
                ]);
            }
        }
    }

    /**
     * Reverse the migration.
     *
     * Model lama tidak dikembalikan karena tidak tersedia untuk API Key saat ini.
     */
    public function down(): void
    {
        // No reversible data change.
    }
};
