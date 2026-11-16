<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add NVIDIA NIM as its own provider and relocate a misplaced NVIDIA key.
     *
     * The NVIDIA key must never be used against the Anthropic endpoint. Existing
     * Gemini/OpenAI credentials are left untouched.
     */
    public function up(): void
    {
        if (! Schema::hasTable('ai_settings')) {
            return;
        }

        $now = now();

        if (! DB::table('ai_settings')->where('provider', 'nvidia')->exists()) {
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
        }

        $nvidia = DB::table('ai_settings')->where('provider', 'nvidia')->first();
        $claude = DB::table('ai_settings')->where('provider', 'claude')->first();

        if ($claude !== null && $claude->api_key !== null) {
            $rawKey = (string) $claude->api_key;
            $wasEncrypted = true;

            try {
                $plainKey = Crypt::decryptString($rawKey);
            } catch (Throwable) {
                $wasEncrypted = false;
                $plainKey = $rawKey;
            }

            if (str_starts_with(trim($plainKey), 'nvapi-')) {
                if ($nvidia !== null && $nvidia->api_key === null) {
                    DB::table('ai_settings')->where('id', $nvidia->id)->update([
                        'api_key' => $wasEncrypted ? $rawKey : Crypt::encryptString(trim($plainKey)),
                        'updated_at' => $now,
                    ]);
                }

                // A NVIDIA key can never authenticate against Anthropic; keep it only in its own slot.
                DB::table('ai_settings')->where('id', $claude->id)->update([
                    'api_key' => null,
                    'last_tested_at' => null,
                    'last_test_status' => null,
                    'last_test_message' => null,
                    'updated_at' => $now,
                ]);
            }
        }

        $nvidiaModel = 'nvidia/nemotron-3.5-bolt-e4b';
        $supportedNvidiaModels = [
            'nvidia/nemotron-3.5-bolt-e4b',
            'nvidia/nemotron-3.5-lightning-30b-a3b',
            'minimaxai/minimax-m3.1-flash',
        ];

        $nvidia = DB::table('ai_settings')->where('provider', 'nvidia')->first();

        if ($nvidia === null) {
            return;
        }

        $updates = [];
        $model = $nvidia->model !== null ? trim((string) $nvidia->model) : null;

        if ($model !== null && ($model === '' || ! in_array($model, $supportedNvidiaModels, true))) {
            $updates['model'] = null;
            $updates['last_tested_at'] = null;
            $updates['last_test_status'] = null;
            $updates['last_test_message'] = null;
        }

        $baseUrl = trim((string) $nvidia->base_url);
        $host = $baseUrl !== '' ? (parse_url($baseUrl, PHP_URL_HOST) ?: '') : '';

        if ($baseUrl === '' || $host === '' || in_array($host, [
            'generativelanguage.googleapis.com',
            'api.openai.com',
            'api.anthropic.com',
        ], true)) {
            $updates['base_url'] = 'https://integrate.api.nvidia.com/v1';
            $updates['last_tested_at'] = null;
            $updates['last_test_status'] = null;
            $updates['last_test_message'] = null;
        }

        if ($updates !== []) {
            $updates['updated_at'] = $now;

            DB::table('ai_settings')->where('id', $nvidia->id)->update($updates);
        }

        $nvidiaAfterUpdate = DB::table('ai_settings')->where('id', $nvidia->id)->first();

        if ($nvidiaAfterUpdate !== null && $nvidiaAfterUpdate->model === null && $nvidiaAfterUpdate->is_active) {
            DB::table('ai_settings')
                ->where('id', $nvidia->id)
                ->update(['is_active' => false, 'updated_at' => $now]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('ai_settings')) {
            return;
        }

        $nvidia = DB::table('ai_settings')->where('provider', 'nvidia')->first();
        $claude = DB::table('ai_settings')->where('provider', 'claude')->first();

        if ($nvidia !== null && $nvidia->api_key !== null && $claude !== null && $claude->api_key === null) {
            DB::table('ai_settings')->where('id', $claude->id)->update([
                'api_key' => $nvidia->api_key,
                'updated_at' => now(),
            ]);
        }

        DB::table('ai_settings')->where('provider', 'nvidia')->delete();
    }
};
