<?php

namespace Tests\Feature;

use App\Models\AiSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed all provider rows without pretending any model is configured.
        AiSetting::firstOrCreate(['provider' => 'gemini'], [
            'is_active' => false,
            'model' => null,
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'temperature' => 0.70,
            'max_tokens' => 1024,
        ]);
        AiSetting::firstOrCreate(['provider' => 'openai'], [
            'is_active' => false,
            'model' => null,
            'base_url' => 'https://api.openai.com/v1',
            'temperature' => 0.70,
            'max_tokens' => 1024,
        ]);
        AiSetting::firstOrCreate(['provider' => 'nvidia'], [
            'is_active' => false,
            'model' => null,
            'base_url' => 'https://integrate.api.nvidia.com/v1',
            'temperature' => 0.70,
            'max_tokens' => 1024,
        ]);
    }

    /**
     * Test guest cannot access AI configuration.
     */
    public function test_guest_cannot_access_ai_configuration(): void
    {
        $response = $this->get(route('admin.ai-configuration.index'));
        $response->assertRedirect(route('login'));

        $postResponse = $this->post(route('admin.ai-configuration.update'), []);
        $postResponse->assertRedirect(route('login'));

        $testResponse = $this->postJson(route('admin.ai-configuration.test'), []);
        $testResponse->assertStatus(401);
    }

    /**
     * Test non-admin (teacher, student) cannot access or modify AI configuration.
     */
    public function test_non_admin_cannot_access_or_modify_ai_configuration(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        // Teacher attempt
        $resTeacherGet = $this->actingAs($teacher)->get(route('admin.ai-configuration.index'));
        $resTeacherGet->assertStatus(403);

        $resTeacherPost = $this->actingAs($teacher)->post(route('admin.ai-configuration.update'), [
            'active_provider' => 'openai',
            'model' => 'gpt-6-luna',
            'temperature' => 0.7,
            'max_tokens' => 1024,
        ]);
        $resTeacherPost->assertStatus(403);

        // Student attempt
        $resStudentGet = $this->actingAs($student)->get(route('admin.ai-configuration.index'));
        $resStudentGet->assertStatus(403);

        $resStudentPost = $this->actingAs($student)->post(route('admin.ai-configuration.update'), [
            'active_provider' => 'openai',
            'model' => 'gpt-6-luna',
            'temperature' => 0.7,
            'max_tokens' => 1024,
        ]);
        $resStudentPost->assertStatus(403);
    }

    /**
     * Test admin can view AI configuration page.
     */
    public function test_admin_can_view_ai_configuration_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($admin)->get(route('admin.ai-configuration.index'));

        $response->assertStatus(200);
        $response->assertSee('Konfigurasi Multi-Provider AI');
        $response->assertSee('Google Gemini');
        $response->assertSee('OpenAI');
        $response->assertSee('NVIDIA NIM');
        $response->assertDontSee('Anthropic Claude');
    }

    /**
     * Test an unconfigured provider is never shown with a fake default model.
     */
    public function test_unconfigured_provider_shows_belum_dikonfigurasi_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($admin)->get(route('admin.ai-configuration.index'));

        $response->assertOk();
        $response->assertSee('Belum dikonfigurasi');
        $this->assertNull(AiSetting::getActive());
    }

    /**
     * Test legacy cross-provider model and endpoint values are repaired on page load.
     */
    public function test_cross_provider_configuration_is_repaired_when_page_loads(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        AiSetting::where('provider', 'gemini')->update([
            'model' => 'nvidia/nemotron-3.5-bolt-e4b',
            'base_url' => 'https://integrate.api.nvidia.com/v1',
            'last_tested_at' => now(),
            'last_test_status' => 'success',
            'last_test_message' => 'Hasil tes lama yang menyesatkan.',
        ]);

        $this->actingAs($admin)->get(route('admin.ai-configuration.index'))->assertOk();

        $gemini = AiSetting::where('provider', 'gemini')->first();

        $this->assertNull($gemini->model);
        $this->assertSame('https://generativelanguage.googleapis.com/v1beta', $gemini->base_url);
        $this->assertNull($gemini->last_tested_at);
        $this->assertNull($gemini->last_test_status);
    }

    /**
     * Test a model belonging to another provider cannot be saved.
     */
    public function test_saving_model_from_another_provider_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($admin)->post(route('admin.ai-configuration.update'), [
            'active_provider' => 'gemini',
            'model' => 'nvidia/nemotron-3.5-bolt-e4b',
            'api_key' => 'AIzaSyFakeKeyTest123',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'temperature' => 0.70,
            'max_tokens' => 1024,
        ]);

        $response->assertSessionHasErrors('model');

        $gemini = AiSetting::where('provider', 'gemini')->first();
        $this->assertNull($gemini->model);
        $this->assertFalse($gemini->is_active);
        $this->assertNull(AiSetting::getActive());
    }

    /**
     * Test connection testing uses the encrypted key that is already stored.
     */
    public function test_test_connection_uses_stored_api_key_when_none_is_submitted(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $storedKey = 'AIzaSyStoredKeyForTesting1234567890';

        AiSetting::where('provider', 'gemini')->first()->update([
            'model' => 'gemini-3.6-flash',
            'api_key' => $storedKey,
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'Pong']]]],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.ai-configuration.test'), [
            'provider' => 'gemini',
            'model' => 'gemini-3.6-flash',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
        ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'status' => 'success',
            'key_source' => 'database',
        ]);

        Http::assertSent(function ($request) use ($storedKey) {
            return $request->hasHeader('x-goog-api-key', $storedKey)
                && ! str_contains($request->url(), $storedKey);
        });

        $this->assertStringNotContainsString($storedKey, $response->getContent());
        $this->assertNotNull(AiSetting::where('provider', 'gemini')->first()->last_tested_at);
    }

    /**
     * Test quota exhaustion is reported as a quota failure.
     */
    public function test_test_connection_reports_quota_exhausted(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'error' => [
                    'message' => 'You exceeded your current quota, please check your plan and billing details.',
                    'type' => 'insufficient_quota',
                    'code' => 'insufficient_quota',
                ],
            ], 429),
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.ai-configuration.test'), [
            'provider' => 'openai',
            'model' => 'gpt-6-luna',
            'api_key' => 'sk-test-key-with-quota-problem',
            'base_url' => 'https://api.openai.com/v1',
        ]);

        $response->assertOk()->assertJson([
            'success' => false,
            'status' => 'failed',
        ]);

        $this->assertStringContainsString('Kuota', $response->json('message'));
        $this->assertSame('failed', AiSetting::where('provider', 'openai')->first()->last_test_status);
    }

    /**
     * Test an unavailable model is reported as a model failure, not a key failure.
     */
    public function test_test_connection_reports_model_unavailable(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        Http::fake([
            'https://integrate.api.nvidia.com/*' => Http::response([
                'error' => [
                    'type' => 'not_found_error',
                    'message' => 'model nvidia/nemotron-3.5-lightning-30b-a3b is not available for this account',
                ],
            ], 404),
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.ai-configuration.test'), [
            'provider' => 'nvidia',
            'model' => 'nvidia/nemotron-3.5-lightning-30b-a3b',
            'api_key' => 'nvapi-test-key-1234567890',
            'base_url' => 'https://integrate.api.nvidia.com/v1',
        ]);

        $response->assertOk()->assertJson([
            'success' => false,
            'status' => 'failed',
        ]);

        $this->assertStringContainsString('tidak tersedia', $response->json('message'));
    }

    /**
     * Test admin can save configuration and switch active provider and model.
     */
    public function test_admin_can_save_configuration_and_switch_provider(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($admin)->post(route('admin.ai-configuration.update'), [
            'active_provider' => 'openai',
            'model' => 'gpt-6-luna',
            'api_key' => 'sk-test-secret-key-1234567890',
            'base_url' => 'https://api.openai.com/v1',
            'temperature' => 0.50,
            'max_tokens' => 2048,
        ]);

        $response->assertRedirect(route('admin.ai-configuration.index'));
        $response->assertSessionHas('success');

        // Verify active provider in database
        $active = AiSetting::getActive();
        $this->assertNotNull($active);
        $this->assertEquals('openai', $active->provider);
        $this->assertTrue($active->is_active);
        $this->assertEquals('gpt-6-luna', $active->model);
        $this->assertEquals(0.50, $active->temperature);
        $this->assertEquals(2048, $active->max_tokens);

        // Verify gemini was deactivated
        $gemini = AiSetting::where('provider', 'gemini')->first();
        $this->assertFalse($gemini->is_active);
    }

    /**
     * Test API key is encrypted in database and never leaked to frontend views.
     */
    public function test_api_key_is_encrypted_in_database_and_never_leaked_to_frontend(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $secretKey = 'super-secret-production-ai-key-xyz999';

        $this->actingAs($admin)->post(route('admin.ai-configuration.update'), [
            'active_provider' => 'gemini',
            'model' => 'gemini-3.6-flash',
            'api_key' => $secretKey,
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'temperature' => 0.70,
            'max_tokens' => 1024,
        ]);

        // 1. Raw DB check: must NOT be stored in plain text
        $rawRow = DB::table('ai_settings')->where('provider', 'gemini')->first();
        $this->assertNotNull($rawRow);
        $this->assertNotEquals($secretKey, $rawRow->api_key);
        $this->assertStringNotContainsString('super-secret-production', $rawRow->api_key);

        // Decryption check: must match original via app key
        $decrypted = Crypt::decryptString($rawRow->api_key);
        $this->assertEquals($secretKey, $decrypted);

        // 2. View check: full key must NOT appear in HTML response
        $viewResponse = $this->actingAs($admin)->get(route('admin.ai-configuration.index'));
        $viewResponse->assertStatus(200);
        $viewResponse->assertDontSee($secretKey);
        // Only masked snippet should appear
        $viewResponse->assertSee('••••••••z999');
    }

    /**
     * Test saving with blank API key retains the existing encrypted key.
     */
    public function test_blank_api_key_retains_existing_encrypted_key(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $initialKey = 'nvapi-secret-key-initial-4444';

        // 1. First save with key
        $this->actingAs($admin)->post(route('admin.ai-configuration.update'), [
            'active_provider' => 'nvidia',
            'model' => 'nvidia/nemotron-3.5-bolt-e4b',
            'api_key' => $initialKey,
            'base_url' => 'https://integrate.api.nvidia.com/v1',
            'temperature' => 0.70,
            'max_tokens' => 1024,
        ]);

        // 2. Second save with blank key, but updating model
        $this->actingAs($admin)->post(route('admin.ai-configuration.update'), [
            'active_provider' => 'nvidia',
            'model' => 'nvidia/nemotron-3.5-lightning-30b-a3b',
            'api_key' => '', // Left blank!
            'base_url' => 'https://integrate.api.nvidia.com/v1',
            'temperature' => 0.80,
            'max_tokens' => 1024,
        ]);

        $nvidia = AiSetting::where('provider', 'nvidia')->first();
        $this->assertEquals('nvidia/nemotron-3.5-lightning-30b-a3b', $nvidia->model);
        $this->assertEquals($initialKey, $nvidia->api_key);
        $this->assertTrue($nvidia->hasApiKey());
    }

    /**
     * Test admin can explicitly remove stored API key.
     */
    public function test_admin_can_explicitly_remove_api_key(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        // Save key
        $this->actingAs($admin)->post(route('admin.ai-configuration.update'), [
            'active_provider' => 'openai',
            'model' => 'gpt-6-luna',
            'api_key' => 'sk-will-be-deleted-1234',
            'base_url' => 'https://api.openai.com/v1',
            'temperature' => 0.70,
            'max_tokens' => 1024,
        ]);

        $this->assertTrue(AiSetting::where('provider', 'openai')->first()->hasApiKey());

        // Update with remove_api_key = 1
        $this->actingAs($admin)->post(route('admin.ai-configuration.update'), [
            'active_provider' => 'openai',
            'model' => 'gpt-6-luna',
            'api_key' => '',
            'remove_api_key' => 1,
            'base_url' => 'https://api.openai.com/v1',
            'temperature' => 0.70,
            'max_tokens' => 1024,
        ]);

        $openai = AiSetting::where('provider', 'openai')->first();
        $this->assertFalse($openai->hasApiKey());
        $this->assertNull($openai->api_key);
    }

    /**
     * Test connection test endpoint works with mocked HTTP.
     */
    public function test_test_connection_endpoint_succeeds_with_mocked_provider(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'Pong']]]],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.ai-configuration.test'), [
            'provider' => 'gemini',
            'model' => 'gemini-3.6-flash',
            'api_key' => 'AIzaSyFakeKeyTest123',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status' => 'success',
            ]);

        $this->assertStringContainsString('Koneksi Google Gemini berhasil', $response->json('message'));

        // Verify test timestamp in database
        $setting = AiSetting::where('provider', 'gemini')->first();
        $this->assertNotNull($setting->last_tested_at);
        $this->assertEquals('success', $setting->last_test_status);
    }

    /**
     * Test connection test endpoint fails gracefully when provider returns error.
     */
    public function test_test_connection_endpoint_handles_invalid_credentials_gracefully(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'error' => ['message' => 'Incorrect API key provided.'],
            ], 401),
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.ai-configuration.test'), [
            'provider' => 'openai',
            'model' => 'gpt-6-luna',
            'api_key' => 'sk-invalid-key',
            'base_url' => 'https://api.openai.com/v1',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'status' => 'failed',
            ]);

        $this->assertStringContainsString('tidak valid', $response->json('message'));

        $setting = AiSetting::where('provider', 'openai')->first();
        $this->assertEquals('failed', $setting->last_test_status);
    }

    /**
     * Test OpenAI content returned as content parts is parsed into plain text.
     */
    public function test_openai_connection_test_parses_array_content_parts(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => [
                                ['type' => 'text', 'text' => 'pong'],
                            ],
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.ai-configuration.test'), [
            'provider' => 'openai',
            'model' => 'gpt-6-luna',
            'api_key' => 'sk-test-key-1234567890',
            'base_url' => 'https://api.openai.com/v1',
        ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'status' => 'success',
        ]);

        $this->assertStringContainsString('Koneksi OpenAI berhasil', $response->json('message'));
    }

    /**
     * Test an HTTP 200 OpenAI response without text is not reported as success.
     */
    public function test_openai_empty_content_response_is_not_reported_as_success(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => ''], 'finish_reason' => 'length'],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.ai-configuration.test'), [
            'provider' => 'openai',
            'model' => 'gpt-6-luna',
            'api_key' => 'sk-test-key-1234567890',
            'base_url' => 'https://api.openai.com/v1',
        ]);

        $response->assertOk()->assertJson([
            'success' => false,
            'status' => 'failed',
            'http_status' => 200,
            'error_category' => 'empty_response',
        ]);

        $this->assertStringContainsString('tidak mengembalikan teks', $response->json('message'));
    }

    /**
     * Test Gemini thought parts are skipped and all answer parts are joined.
     */
    public function test_gemini_connection_test_joins_answer_parts_and_skips_thoughts(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'thinking...', 'thought' => true],
                                ['text' => 'pong'],
                            ],
                        ],
                        'finishReason' => 'STOP',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.ai-configuration.test'), [
            'provider' => 'gemini',
            'model' => 'gemini-3.6-flash',
            'api_key' => 'AIzaSyFakeKeyTest123',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
        ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'status' => 'success',
        ]);

        $this->assertStringContainsString('Koneksi Google Gemini berhasil', $response->json('message'));
    }

    /**
     * Test NVIDIA connection test succeeds with mocked NVIDIA API response.
     */
    public function test_nvidia_connection_test_succeeds_with_mocked_response(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        Http::fake([
            'https://integrate.api.nvidia.com/*' => Http::response([
                'id' => 'chatcmpl_test',
                'object' => 'chat.completion',
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => 'pong'], 'finish_reason' => 'stop'],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
            ], 200),
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.ai-configuration.test'), [
            'provider' => 'nvidia',
            'model' => 'nvidia/nemotron-3.5-bolt-e4b',
            'api_key' => 'nvapi-test-key-1234567890',
            'base_url' => 'https://integrate.api.nvidia.com/v1',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status' => 'success',
            ]);

        $this->assertStringContainsString('Koneksi NVIDIA NIM berhasil', $response->json('message'));

        $setting = AiSetting::where('provider', 'nvidia')->first();
        $this->assertNotNull($setting->last_tested_at);
        $this->assertEquals('success', $setting->last_test_status);
    }

    /**
     * Test NVIDIA connection test returns proper error for an invalid API key.
     */
    public function test_nvidia_connection_test_fails_gracefully_on_invalid_key(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        Http::fake([
            'https://integrate.api.nvidia.com/*' => Http::response([
                'type' => 'error',
                'error' => ['type' => 'authentication_error', 'message' => 'invalid api key'],
            ], 401),
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.ai-configuration.test'), [
            'provider' => 'nvidia',
            'model' => 'nvidia/nemotron-3.5-bolt-e4b',
            'api_key' => 'nvapi-invalid-key-xyz1234567890',
            'base_url' => 'https://integrate.api.nvidia.com/v1',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'status' => 'failed',
            ]);

        $this->assertStringContainsString('tidak valid', $response->json('message'));
    }

    /**
     * Test admin can switch to NVIDIA NIM as active provider.
     */
    public function test_admin_can_switch_to_nvidia_as_active_provider(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($admin)->post(route('admin.ai-configuration.update'), [
            'active_provider' => 'nvidia',
            'model' => 'nvidia/nemotron-3.5-bolt-e4b',
            'api_key' => 'nvapi-test-nvidia-key-1234567890',
            'base_url' => 'https://integrate.api.nvidia.com/v1',
            'temperature' => 0.70,
            'max_tokens' => 1024,
        ]);

        $response->assertRedirect(route('admin.ai-configuration.index'));
        $response->assertSessionHas('success');

        $active = AiSetting::getActive();
        $this->assertNotNull($active);
        $this->assertEquals('nvidia', $active->provider);
        $this->assertTrue($active->is_active);
        $this->assertEquals('nvidia/nemotron-3.5-bolt-e4b', $active->model);

        // Verify gemini and openai were deactivated
        $this->assertFalse(AiSetting::where('provider', 'gemini')->first()->is_active);
        $this->assertFalse(AiSetting::where('provider', 'openai')->first()->is_active);
    }

    /**
     * Test NVIDIA is a first-class provider with its own model catalog and endpoint.
     */
    public function test_nvidia_provider_has_its_own_models_and_endpoint(): void
    {
        $this->assertTrue(AiSetting::isSupportedProvider('nvidia'));
        $this->assertFalse(AiSetting::isSupportedProvider('claude'));
        $this->assertSame('NVIDIA NIM', AiSetting::providerLabel('nvidia'));
        $this->assertSame('https://integrate.api.nvidia.com/v1', AiSetting::defaultBaseUrl('nvidia'));
        $this->assertTrue(AiSetting::isModelSupported('nvidia', 'nvidia/nemotron-3.5-bolt-e4b'));
        $this->assertFalse(AiSetting::isModelSupported('nvidia', 'gpt-6-luna'));
        $this->assertFalse(AiSetting::isModelSupported('openai', 'nvidia/nemotron-3.5-bolt-e4b'));

        // Only models that the configured account can actually use are offered.
        $this->assertFalse(AiSetting::isModelSupported('gemini', 'gemini-4-argon'));
        $this->assertFalse(AiSetting::isModelSupported('gemini', 'gemini-3.8-flash'));
        $this->assertFalse(AiSetting::isModelSupported('openai', 'gpt-6-astra'));
        $this->assertFalse(AiSetting::isModelSupported('openai', 'gpt-6.1-sol'));
    }

    /**
     * Test the NVIDIA connection test uses only the stored NVIDIA key and endpoint.
     */
    public function test_nvidia_connection_test_uses_stored_nvidia_key_and_endpoint(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $storedKey = 'nvapi-stored-nvidia-key-1234567890';

        AiSetting::where('provider', 'nvidia')->first()->update([
            'model' => 'nvidia/nemotron-3.5-bolt-e4b',
            'api_key' => $storedKey,
        ]);

        Http::fake([
            'https://integrate.api.nvidia.com/*' => Http::response([
                'id' => 'chatcmpl-test',
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => 'pong'], 'finish_reason' => 'stop'],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 2, 'total_tokens' => 12],
            ], 200),
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.ai-configuration.test'), [
            'provider' => 'nvidia',
            'model' => 'nvidia/nemotron-3.5-bolt-e4b',
            'base_url' => 'https://integrate.api.nvidia.com/v1',
        ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'status' => 'success',
            'provider' => 'nvidia',
            'provider_label' => 'NVIDIA NIM',
            'model' => 'nvidia/nemotron-3.5-bolt-e4b',
            'http_status' => 200,
            'error_category' => 'success',
            'key_source' => 'database',
        ]);

        Http::assertSent(function ($request) use ($storedKey) {
            return $request->url() === 'https://integrate.api.nvidia.com/v1/chat/completions'
                && $request->hasHeader('Authorization', "Bearer {$storedKey}")
                && ! str_contains($request->url(), $storedKey);
        });

        $this->assertStringNotContainsString($storedKey, $response->getContent());
    }

    /**
     * Test an HTTP 200 NVIDIA response without content is not reported as success.
     */
    public function test_nvidia_empty_content_response_is_not_reported_as_success(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        Http::fake([
            'https://integrate.api.nvidia.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => ['role' => 'assistant', 'content' => ''],
                        'finish_reason' => 'length',
                    ],
                ],
                'usage' => [
                    'completion_tokens' => 512,
                    'completion_tokens_details' => ['reasoning_tokens' => 512],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.ai-configuration.test'), [
            'provider' => 'nvidia',
            'model' => 'nvidia/nemotron-3.5-lightning-30b-a3b',
            'api_key' => 'nvapi-test-key-1234567890',
            'base_url' => 'https://integrate.api.nvidia.com/v1',
        ]);

        $response->assertOk()->assertJson([
            'success' => false,
            'status' => 'failed',
            'http_status' => 200,
            'error_category' => 'empty_response',
        ]);

        $this->assertStringContainsString('tidak mengembalikan teks', $response->json('message'));
        $this->assertSame('failed', AiSetting::where('provider', 'nvidia')->first()->last_test_status);
    }

    /**
     * Test a key that clearly belongs to another provider is never sent anywhere.
     */
    public function test_connection_test_blocks_key_from_another_provider_before_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        Http::fake();

        $response = $this->actingAs($admin)->postJson(route('admin.ai-configuration.test'), [
            'provider' => 'openai',
            'model' => 'gpt-6-luna',
            'api_key' => 'AQ.gemini-style-key-1234567890',
            'base_url' => 'https://api.openai.com/v1',
        ]);

        $response->assertOk()->assertJson([
            'success' => false,
            'status' => 'failed',
            'provider' => 'openai',
            'http_status' => null,
            'error_category' => 'key_provider_mismatch',
        ]);

        $this->assertStringContainsString('Google Gemini', $response->json('message'));
        $this->assertStringNotContainsString('AQ.gemini-style-key', $response->getContent());
        Http::assertNothingSent();
    }

    /**
     * Test a NVIDIA key is never sent to the OpenAI endpoint.
     */
    public function test_nvidia_key_is_never_sent_to_openai_endpoint(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        Http::fake();

        $response = $this->actingAs($admin)->postJson(route('admin.ai-configuration.test'), [
            'provider' => 'openai',
            'model' => 'gpt-6-luna',
            'api_key' => 'nvapi-test-key-1234567890',
            'base_url' => 'https://api.openai.com/v1',
        ]);

        $response->assertOk()->assertJson([
            'success' => false,
            'status' => 'failed',
            'provider' => 'openai',
            'error_category' => 'key_provider_mismatch',
        ]);

        $this->assertStringContainsString('NVIDIA', $response->json('message'));
        Http::assertNothingSent();
    }

    /**
     * Test a cross-provider key cannot be persisted through the configuration form.
     */
    public function test_saving_key_from_another_provider_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($admin)->post(route('admin.ai-configuration.update'), [
            'active_provider' => 'openai',
            'model' => 'gpt-6-luna',
            'api_key' => 'AQ.gemini-style-key-1234567890',
            'base_url' => 'https://api.openai.com/v1',
            'temperature' => 0.70,
            'max_tokens' => 1024,
        ]);

        $response->assertSessionHasErrors('api_key');

        $openai = AiSetting::where('provider', 'openai')->first();
        $this->assertNull($openai->api_key);
        $this->assertFalse($openai->is_active);
    }

    /**
     * Test a foreign official endpoint cannot be saved for another provider.
     */
    public function test_saving_foreign_official_endpoint_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($admin)->post(route('admin.ai-configuration.update'), [
            'active_provider' => 'gemini',
            'model' => 'gemini-3.6-flash',
            'api_key' => 'AIzaSyFakeKeyTest123',
            'base_url' => 'https://integrate.api.nvidia.com/v1',
            'temperature' => 0.70,
            'max_tokens' => 1024,
        ]);

        $response->assertSessionHasErrors('base_url');

        $gemini = AiSetting::where('provider', 'gemini')->first();
        $this->assertNotSame('https://integrate.api.nvidia.com/v1', $gemini->base_url);
        $this->assertFalse($gemini->is_active);
    }

    /**
     * Test the diagnostic payload distinguishes provider, model, HTTP status, and category.
     */
    public function test_connection_test_reports_diagnostic_metadata_without_leaking_key(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $key = 'sk-openai-invalid-key-1234567890';

        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'error' => [
                    'message' => 'Incorrect API key provided: sk-openai-invalid-key-1234567890',
                    'type' => 'invalid_request_error',
                    'code' => 'invalid_api_key',
                ],
            ], 401),
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.ai-configuration.test'), [
            'provider' => 'openai',
            'model' => 'gpt-6-luna',
            'api_key' => $key,
            'base_url' => 'https://api.openai.com/v1',
        ]);

        $response->assertOk()->assertJson([
            'success' => false,
            'provider' => 'openai',
            'provider_label' => 'OpenAI',
            'model' => 'gpt-6-luna',
            'endpoint_host' => 'api.openai.com',
            'http_status' => 401,
            'error_category' => 'authentication_error',
        ]);

        $this->assertStringNotContainsString($key, $response->getContent());
        $this->assertStringNotContainsString('Google Gemini', $response->json('message'));
    }

    /**
     * Test known key prefixes are classified without rejecting unknown formats.
     */
    public function test_key_prefix_detection_is_provider_specific(): void
    {
        $this->assertSame('gemini', AiSetting::detectKeyProvider('AIzaSyExample'));
        $this->assertSame('gemini', AiSetting::detectKeyProvider('AQ.example'));
        $this->assertSame('openai', AiSetting::detectKeyProvider('sk-proj-example'));
        $this->assertSame('nvidia', AiSetting::detectKeyProvider('nvapi-example'));
        $this->assertNull(AiSetting::detectKeyProvider('custom-enterprise-key'));
    }
}
