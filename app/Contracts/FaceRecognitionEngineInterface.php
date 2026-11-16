<?php

namespace App\Contracts;

interface FaceRecognitionEngineInterface
{
    /**
     * Process and normalize raw face data input into stored biometric descriptor.
     *
     * @return string Normalized JSON string of biometric descriptor
     *
     * @throws \InvalidArgumentException
     */
    public function processRegistration(mixed $rawFaceInput): string;

    /**
     * Validate structural integrity of input face descriptor data.
     */
    public function validateDescriptor(mixed $faceData): bool;

    /**
     * Compare two face descriptors and return match boolean + confidence score.
     *
     * @return array{matched: bool, distance: float, confidence: float}
     */
    public function compareDescriptors(string|array $storedDescriptor, string|array $inputDescriptor, float $threshold = 0.6): array;
}
