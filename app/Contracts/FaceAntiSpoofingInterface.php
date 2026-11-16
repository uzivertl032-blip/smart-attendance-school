<?php

namespace App\Contracts;

interface FaceAntiSpoofingInterface
{
    /**
     * Evaluate face input temporal/liveness metadata for anti-spoofing verification.
     * Note: This provides basic motion and temporal consistency checking.
     * It is not a hardware-grade passive 3D infrared sensor.
     *
     * @param  array  $metadata  Contains temporal timestamps, bounding box delta, confidence
     * @return array{passed: bool, score: float, reason: string|null}
     */
    public function verifyLiveness(array $metadata): array;
}
