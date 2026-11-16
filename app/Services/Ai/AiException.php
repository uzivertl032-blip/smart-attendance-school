<?php

namespace App\Services\Ai;

use RuntimeException;
use Throwable;

class AiException extends RuntimeException
{
    /**
     * Create a new AI exception with a user-safe message.
     */
    public function __construct(
        string $message,
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
