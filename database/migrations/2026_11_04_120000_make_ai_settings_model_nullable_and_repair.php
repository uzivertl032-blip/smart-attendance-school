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
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->string('model')->nullable()->change();
        });

        $supportedModels = [
            'gemini' => [
                'gemini-4-argon',
                'gemini-3.8-flash',
                'gemini-3.6-flash',
                'gemini-3.5-flash-lite',
                'gemini-3.1-flash-lite',
                'gemini-3.1-pro-preview',
            ],
            'openai' => [
                'gpt-6-astra',
                'gpt-6.1-sol',
                'gpt-6.1-terra',
                'gpt-6-luna',
            ],
            'claude' => [
                'claude-fable-5-1',
                'claude-opus-5-5',
                'claude-sonnet-5-5',
                'claude-haiku-5-5',
            ],
        ];

        $defaultBaseUrls = [
            'gemini' => 'https://generativelanguage.googleapis.com/v1beta',
            'openai' => 'https://api.openai.com/v1',
            'claude' => 'https://api.anthropic.com/v1',
        ];

        $officialHosts = [
            'gemini' => 'generativelanguage.googleapis.com',
            'openai' => 'api.openai.com',
            'claude' => 'api.anthropic.com',
        ];

        foreach (DB::table('ai_settings')->get() as $setting) {
            $provider = (string) $setting->provider;

            if (! isset($supportedModels[$provider])) {
                continue;
            }

            $updates = [];
            $model = $setting->model !== null ? trim((string) $setting->model) : null;

            // Clear a model that belongs to another provider, is empty, or is no longer available.
            if ($model !== null && ($model === '' || ! in_array($model, $supportedModels[$provider], true))) {
                $updates['model'] = null;
                $updates['last_tested_at'] = null;
                $updates['last_test_status'] = null;
                $updates['last_test_message'] = null;
            }

            $baseUrl = trim((string) $setting->base_url);
            $host = $baseUrl !== '' ? (parse_url($baseUrl, PHP_URL_HOST) ?: '') : '';

            foreach ($officialHosts as $otherProvider => $officialHost) {
                if ($otherProvider !== $provider && $host === $officialHost) {
                    $updates['base_url'] = $defaultBaseUrls[$provider];
                    $updates['last_tested_at'] = null;
                    $updates['last_test_status'] = null;
                    $updates['last_test_message'] = null;
                    break;
                }
            }

            if ($baseUrl === '') {
                $updates['base_url'] = $defaultBaseUrls[$provider];
            }

            if ($updates !== []) {
                $updates['updated_at'] = now();

                DB::table('ai_settings')->where('id', $setting->id)->update($updates);
            }
        }

        // A provider without a compatible model cannot serve requests, so it must not stay active.
        DB::table('ai_settings')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('model')->orWhere('model', '');
            })
            ->update(['is_active' => false, 'updated_at' => now()]);

        // Keep exactly one active provider when legacy data has multiple active rows.
        $activeSettings = DB::table('ai_settings')
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        if ($activeSettings->count() > 1) {
            DB::table('ai_settings')
                ->whereIn('id', $activeSettings->skip(1)->pluck('id'))
                ->update(['is_active' => false, 'updated_at' => now()]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->string('model')->nullable(false)->change();
        });
    }
};
