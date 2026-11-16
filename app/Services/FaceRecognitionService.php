<?php

namespace App\Services;

use App\Contracts\FaceRecognitionEngineInterface;
use InvalidArgumentException;

class FaceRecognitionService implements FaceRecognitionEngineInterface
{
    /**
     * Default Euclidean distance threshold for matching.
     */
    protected float $defaultThreshold = 0.55;

    /**
     * Process raw face input (descriptor array or JSON) into a secure, standardized JSON structure.
     *
     * @throws InvalidArgumentException
     */
    public function processRegistration(mixed $rawFaceInput): string
    {
        $descriptor = is_string($rawFaceInput) ? json_decode($rawFaceInput, true) : $rawFaceInput;

        if (! $this->validateDescriptor($descriptor)) {
            throw new InvalidArgumentException('Data biometrik wajah tidak valid atau tidak terbaca.');
        }

        // Support both direct vector array [0.12, -0.05, ...] or structured object { descriptor: [...], landmarks: [...] }
        $vector = isset($descriptor['descriptor']) ? $descriptor['descriptor'] : $descriptor;

        $payload = [
            'engine' => 'DefaultVectorEngine_v1',
            'registered_at' => now()->toIso8601String(),
            'vector' => array_values(array_map('floatval', $vector)),
            'dimensions' => count($vector),
        ];

        return json_encode($payload);
    }

    /**
     * Validate structural integrity of input face descriptor data.
     */
    public function validateDescriptor(mixed $faceData): bool
    {
        if (is_string($faceData)) {
            $faceData = json_decode($faceData, true);
        }

        if (! is_array($faceData) || empty($faceData)) {
            return false;
        }

        $vector = isset($faceData['vector'])
            ? $faceData['vector']
            : (isset($faceData['descriptor']) ? $faceData['descriptor'] : $faceData);

        if (! is_array($vector) || count($vector) < 32 || count($vector) > 512) {
            return false;
        }

        foreach ($vector as $val) {
            if (! is_numeric($val)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Compare two face descriptors using Euclidean distance metric.
     *
     * @return array{matched: bool, distance: float, confidence: float}
     */
    public function compareDescriptors(string|array $storedDescriptor, string|array $inputDescriptor, float $threshold = 0.55): array
    {
        $vec1 = $this->extractVector($storedDescriptor);
        $vec2 = $this->extractVector($inputDescriptor);

        if (empty($vec1) || empty($vec2) || count($vec1) !== count($vec2)) {
            return [
                'matched' => false,
                'distance' => 999.0,
                'confidence' => 0.0,
            ];
        }

        $sumOfSquares = 0.0;
        $count = count($vec1);
        for ($i = 0; $i < $count; $i++) {
            $diff = $vec1[$i] - $vec2[$i];
            $sumOfSquares += $diff * $diff;
        }

        $distance = sqrt($sumOfSquares);
        $matched = $distance <= $threshold;
        $confidence = max(0.0, min(100.0, (1 - ($distance / ($threshold * 2))) * 100));

        return [
            'matched' => $matched,
            'distance' => round($distance, 4),
            'confidence' => round($confidence, 2),
        ];
    }

    /**
     * Extract raw float array vector from various JSON / payload formats.
     */
    protected function extractVector(string|array $data): array
    {
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        if (! is_array($data)) {
            return [];
        }

        if (isset($data['vector']) && is_array($data['vector'])) {
            return array_values(array_map('floatval', $data['vector']));
        }

        if (isset($data['descriptor']) && is_array($data['descriptor'])) {
            return array_values(array_map('floatval', $data['descriptor']));
        }

        return array_values(array_map('floatval', $data));
    }
}
