<?php

namespace App\Http\Controllers;

use App\Services\ChatbotPlusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected ChatbotPlusService $chatbotService
    ) {}

    /**
     * Process incoming message for Chatbot+.
     */
    public function ask(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ], [
            'message.required' => 'Pertanyaan tidak boleh kosong.',
            'message.string' => 'Format pertanyaan tidak valid.',
            'message.max' => 'Pertanyaan maksimal 1000 karakter.',
        ]);

        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'reply' => 'Sesi login telah berakhir. Silakan login kembali.',
                'intent' => 'unauthenticated',
            ], 401);
        }

        try {
            $result = $this->chatbotService->handle($user, $validated['message']);

            return response()->json([
                'success' => $result['success'],
                'reply' => $result['reply'],
                'intent' => $result['intent'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Chatbot+ execution error: '.$e->getMessage(), [
                'user_id' => $user->id,
                'role' => $user->role,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'reply' => 'Mohon maaf, terjadi kendala saat memproses pertanyaan Anda. Silakan coba beberapa saat lagi.',
                'intent' => 'system_error',
            ], 500);
        }
    }
}
