<?php

namespace App\Services;

use App\Contracts\FaceAntiSpoofingInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LivenessDetectionService implements FaceAntiSpoofingInterface
{
    /**
     * Maximum allowed age of capture timestamp in seconds (prevents replay of old capture payloads).
     */
    protected int $maxCaptureAgeSeconds = 30;

    /**
     * Evaluate candidate face capture metadata for liveness and anti-spoofing indicators.
     *
     * IMPORTANT SECURITY NOTE:
     * This implementation performs software-based temporal freshness and heuristic consistency checks.
     * It mitigates simple replay and outdated captured payloads, but cannot physically replace
     * hardware-level 3D depth sensors or dedicated infrared (IR) cameras.
     *
     * @return array{passed: bool, score: float, reason: string|null}
     */
    public function verifyLiveness(array $metadata): array
    {
        // 1. If no metadata provided, allow basic pass with neutral score (backward compatibility)
        if (empty($metadata)) {
            return [
                'passed' => true,
                'score' => 0.5,
                'reason' => 'Mode kompatibilitas standar (metadata liveness tidak disertakan).',
            ];
        }

        // 2. Check Replay Attack via Timestamp Freshness
        if (isset($metadata['captured_at'])) {
            try {
                $capturedAt = Carbon::parse($metadata['captured_at']);
                $age = abs(now()->diffInSeconds($capturedAt));

                if ($age > $this->maxCaptureAgeSeconds) {
                    Log::warning('Security: Potensi replay attack terdeteksi. Timestamp frame kedaluwarsa.', [
                        'payload_age_seconds' => $age,
                        'max_allowed' => $this->maxCaptureAgeSeconds,
                    ]);

                    return [
                        'passed' => false,
                        'score' => 0.1,
                        'reason' => 'Sampel wajah kedaluwarsa atau terdeteksi potensi replay video.',
                    ];
                }
            } catch (\Throwable $e) {
                return [
                    'passed' => false,
                    'score' => 0.0,
                    'reason' => 'Format timestamp metadata biometrik tidak valid.',
                ];
            }
        }

        // 3. Check for Static Frozen Image (Zero Variance / Spoofed Synthetic Vector)
        if (isset($metadata['variance']) && is_numeric($metadata['variance'])) {
            if ((float) $metadata['variance'] <= 0.0001) {
                return [
                    'passed' => false,
                    'score' => 0.2,
                    'reason' => 'Gambar terdeteksi statis tanpa variasi alami kulit/cahaya.',
                ];
            }
        }

        return [
            'passed' => true,
            'score' => 0.85,
            'reason' => 'Verifikasi konsistensi temporal lolos.',
        ];
    }
}
